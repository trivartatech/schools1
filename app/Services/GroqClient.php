<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqClient
{
    public const PRESETS = [
        'analyst'  => ['temperature' => 0.4, 'max_tokens' => 3000, 'timeout' => 60],
        'creative' => ['temperature' => 0.5, 'max_tokens' => 600,  'timeout' => 45],
        'fast'     => ['temperature' => 0.3, 'max_tokens' => 400,  'timeout' => 25],
        'long'     => ['temperature' => 0.7, 'max_tokens' => 2000, 'timeout' => 60],
        'paper'    => ['temperature' => 0.7, 'max_tokens' => 8192, 'timeout' => 90],
    ];

    public function chat(array $messages, string $preset = 'creative', array $opts = []): array
    {
        $cfg = $this->resolveConfig($preset, $opts);

        try {
            $response = Http::withToken(config('services.groq.key'))
                ->timeout($cfg['timeout'])
                ->post(config('services.groq.endpoint'), array_filter([
                    'model'       => config('services.groq.model'),
                    'messages'    => $messages,
                    'temperature' => $cfg['temperature'],
                    'max_tokens'  => $cfg['max_tokens'],
                    'tools'       => $opts['tools']       ?? null,
                    'tool_choice' => $opts['tool_choice'] ?? null,
                ], fn($v) => $v !== null));
        } catch (ConnectionException $e) {
            Log::warning('Groq connection timeout', ['preset' => $preset, 'error' => $e->getMessage()]);
            return ['ok' => false, 'status' => 503, 'error' => 'The AI service took too long to respond. Please try again.'];
        } catch (\Throwable $e) {
            Log::error('Groq unexpected error', ['preset' => $preset, 'error' => $e->getMessage()]);
            return ['ok' => false, 'status' => 503, 'error' => 'AI service is unavailable.'];
        }

        if ($response->failed()) {
            $msg = $response->json('error.message') ?? 'AI unavailable.';
            Log::warning('Groq request failed', ['preset' => $preset, 'status' => $response->status(), 'body' => $msg]);
            return ['ok' => false, 'status' => $response->status() ?: 503, 'error' => $msg];
        }

        $usage   = $response->json('usage') ?? [];
        $message = $response->json('choices.0.message') ?? [];

        Log::info('Groq usage', [
            'preset'        => $preset,
            'prompt_tokens' => $usage['prompt_tokens']     ?? null,
            'output_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens'  => $usage['total_tokens']      ?? null,
            'model'         => config('services.groq.model'),
        ]);

        return [
            'ok'         => true,
            'content'    => $message['content']    ?? '',
            'tool_calls' => $message['tool_calls'] ?? [],
            'usage'      => $usage,
            'raw'        => $message,
        ];
    }

    public function complete(string $prompt, string $preset = 'creative', array $opts = []): array
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $preset, $opts);
    }

    /**
     * Stream a chat completion. The $onDelta callable is called once per
     * incoming text token. The optional $onToolCalls callable is invoked
     * with the array of tool_calls when the model finalises any (non-stream
     * tool-calls path).
     */
    public function streamLive(array $messages, string $preset, callable $onDelta, array $opts = []): void
    {
        $cfg    = $this->resolveConfig($preset, $opts);
        $buffer = '';

        $payload = array_filter([
            'model'       => config('services.groq.model'),
            'messages'    => $messages,
            'temperature' => $cfg['temperature'],
            'max_tokens'  => $cfg['max_tokens'],
            'stream'      => true,
            'tools'       => $opts['tools']       ?? null,
            'tool_choice' => $opts['tool_choice'] ?? null,
        ], fn($v) => $v !== null);

        $ch = curl_init(config('services.groq.endpoint'));
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . config('services.groq.key'),
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => $cfg['timeout'],
            CURLOPT_WRITEFUNCTION  => function ($_, $chunk) use (&$buffer, $onDelta) {
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event  = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    foreach (explode("\n", $event) as $line) {
                        if (!str_starts_with($line, 'data: ')) continue;
                        $data = trim(substr($line, 6));
                        if ($data === '' || $data === '[DONE]') continue;

                        $json  = json_decode($data, true);
                        $delta = $json['choices'][0]['delta']['content'] ?? null;
                        if ($delta !== null && $delta !== '') {
                            $onDelta($delta);
                        }
                    }
                }
                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        if ($err = curl_error($ch)) {
            Log::warning('Groq stream curl error', ['error' => $err]);
        }
        curl_close($ch);
    }

    private function resolveConfig(string $preset, array $opts): array
    {
        $base = self::PRESETS[$preset] ?? self::PRESETS['creative'];
        return [
            'temperature' => $opts['temperature'] ?? $base['temperature'],
            'max_tokens'  => $opts['max_tokens']  ?? $base['max_tokens'],
            'timeout'     => $opts['timeout']     ?? $base['timeout'],
        ];
    }
}
