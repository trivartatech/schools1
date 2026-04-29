<?php

namespace App\Services\Ai;

abstract class AiTool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * JSON Schema fragment for the tool's parameters.
     * Should return: ['type' => 'object', 'properties' => [...], 'required' => [...]]
     */
    abstract public function parameters(): array;

    /**
     * Execute with the LLM-provided arguments. Implementations MUST scope
     * by `app('current_school_id')` regardless of the supplied args.
     */
    abstract public function run(array $args): array;

    public function toOpenAiTool(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name'        => $this->name(),
                'description' => $this->description(),
                'parameters'  => $this->parameters(),
            ],
        ];
    }

    protected function schoolId(): int
    {
        return (int) app('current_school_id');
    }

    protected function academicYearId(): ?int
    {
        return app()->bound('current_academic_year_id') ? (int) app('current_academic_year_id') : null;
    }
}
