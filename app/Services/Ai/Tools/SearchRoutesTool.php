<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AiTool;

class SearchRoutesTool extends AiTool
{
    public function name(): string
    {
        return 'search_routes';
    }

    public function description(): string
    {
        return 'Search the ERP navigation for pages matching a keyword. Use when the user asks "where is X" or "how do I get to X". Returns matching pages with title, route URL and group.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Keyword to search for in page titles (e.g. "fee", "attendance", "exam")'],
                'limit' => ['type' => 'integer', 'description' => 'Max results (default 8, max 25)'],
            ],
            'required' => ['query'],
        ];
    }

    public function run(array $args): array
    {
        $query = trim(strtolower((string) ($args['query'] ?? '')));
        $limit = max(1, min(25, (int) ($args['limit'] ?? 8)));

        if ($query === '') return ['matches' => []];

        $groups = config('sidebar.groups', []);
        $matches = [];

        foreach ($groups as $groupName => $items) {
            foreach ($items as $item) {
                if (str_contains(strtolower($item['title']), $query) ||
                    str_contains(strtolower($item['route']), $query) ||
                    str_contains(strtolower($groupName), $query)) {
                    $matches[] = [
                        'title' => $item['title'],
                        'route' => $item['route'],
                        'group' => $groupName,
                    ];
                    if (count($matches) >= $limit) break 2;
                }
            }
        }

        return [
            'count'   => count($matches),
            'matches' => $matches,
        ];
    }
}
