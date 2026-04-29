<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

class AiToolRegistry
{
    /** @var array<string, AiTool> */
    private array $tools = [];

    public function __construct()
    {
        foreach ($this->defaultTools() as $tool) {
            $this->register($tool);
        }
    }

    public function register(AiTool $tool): self
    {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    /** @return array<string, AiTool> */
    public function all(): array
    {
        return $this->tools;
    }

    public function get(string $name): ?AiTool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return array Specifications in OpenAI tool-calling format */
    public function specs(): array
    {
        return array_values(array_map(fn(AiTool $t) => $t->toOpenAiTool(), $this->tools));
    }

    /**
     * Dispatch a tool call. Returns ['name', 'tool_call_id', 'content']
     * suitable for appending as a `role: tool` message.
     */
    public function dispatch(string $toolCallId, string $name, string $argsJson): array
    {
        $tool = $this->get($name);
        if (!$tool) {
            return [
                'tool_call_id' => $toolCallId,
                'name'         => $name,
                'content'      => json_encode(['error' => "Unknown tool: {$name}"]),
            ];
        }

        $args = json_decode($argsJson, true) ?: [];

        try {
            $result = $tool->run($args);
        } catch (\Throwable $e) {
            Log::warning('AI tool failed', ['tool' => $name, 'error' => $e->getMessage(), 'args' => $args]);
            $result = ['error' => 'Tool failed: ' . $e->getMessage()];
        }

        return [
            'tool_call_id' => $toolCallId,
            'name'         => $name,
            'content'      => json_encode($result),
        ];
    }

    /** @return array<int, AiTool> */
    private function defaultTools(): array
    {
        return [
            new Tools\SearchStudentByNameTool(),
            new Tools\CountAttendanceTool(),
            new Tools\GetFeeDefaultersTool(),
            new Tools\GetStudentDetailsTool(),
            new Tools\GetExamScheduleTool(),
            new Tools\GetStaffOnLeaveTool(),
            new Tools\GetTransportRouteTool(),
            new Tools\GetHostelOccupancyTool(),
            new Tools\SearchRoutesTool(),
        ];
    }
}
