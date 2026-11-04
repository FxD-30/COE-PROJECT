<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared query-string handling for list endpoints:
 *   ?page=2&per_page=25        pagination (per_page 1-100)
 *   ?q=phone                   free-text search (each controller decides which columns)
 *   ?sort=-price,name          comma-separated sort fields, "-" prefix for descending
 */
trait ListsResources
{
    protected const DEFAULT_PER_PAGE = 10;

    protected const MAX_PER_PAGE = 100;

    /**
     * Validation rules shared by every list endpoint.
     */
    protected function listRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    protected function perPage(Request $request): int
    {
        return $request->integer('per_page', self::DEFAULT_PER_PAGE);
    }

    /**
     * Apply ?sort=... using only whitelisted fields. $allowed maps the public field
     * name to the column (or select alias) to order by.
     *
     * @param  array<string, string>  $allowed
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default): Builder
    {
        $sort = $request->string('sort')->trim()->value() ?: $default;

        foreach (explode(',', $sort) as $field) {
            $field = trim($field);
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $name = ltrim($field, '-');

            if (! array_key_exists($name, $allowed)) {
                throw ValidationException::withMessages([
                    'sort' => "Cannot sort by \"{$name}\". Allowed fields: ".implode(', ', array_keys($allowed)).'.',
                ]);
            }

            $query->orderBy($allowed[$name], $direction);
        }

        // Tie-breaker so pages are stable when the sort values repeat.
        return $query->orderBy($query->getModel()->qualifyColumn('id'));
    }

    /**
     * Read a true/false query flag. Accepts true/false/1/0.
     */
    protected function booleanRule(): array
    {
        return ['sometimes', 'nullable', 'in:true,false,1,0'];
    }
}
