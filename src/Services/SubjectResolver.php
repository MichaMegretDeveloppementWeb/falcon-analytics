<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves a human label and display name for a tracked subject (guard + id),
 * driven entirely by config so the package stays host-agnostic. Names are read
 * from the guard's own model at render time and are never stored.
 */
final class SubjectResolver
{
    public function label(string $guard): string
    {
        $label = config("analytics.identity.subjects.{$guard}.label");

        return is_string($label) && $label !== '' ? $label : Str::headline($guard);
    }

    public function name(string $guard, int $id): ?string
    {
        return $this->names($guard, [$id])[$id] ?? null;
    }

    /**
     * The subject guards declared in config.
     *
     * @return list<string>
     */
    public function guards(): array
    {
        $subjects = config('analytics.identity.subjects', []);

        return is_array($subjects) ? array_map('strval', array_keys($subjects)) : [];
    }

    /**
     * Ids of a guard whose name (or fallback) columns match the term, so the
     * session list can be searched by visitor name.
     *
     * @return list<int>
     */
    public function matchIds(string $guard, string $term): array
    {
        $config = config("analytics.identity.subjects.{$guard}");
        $config = is_array($config) ? $config : [];

        $columns = array_values(array_unique([
            ...$this->columns($config['name'] ?? []),
            ...$this->columns($config['fallback'] ?? []),
        ]));

        $source = $columns === [] ? null : $this->source($guard, $config);

        if ($source === null) {
            return [];
        }

        [$table, $key] = $source;

        try {
            return DB::table($table)
                ->where(function ($query) use ($columns, $term): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%'.$term.'%');
                    }
                })
                ->limit(200)
                ->pluck($key)
                ->map(fn ($value): int => (int) $value)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Batch-resolve display names for several ids of one guard in a single query.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(string $guard, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        $config = config("analytics.identity.subjects.{$guard}");
        $config = is_array($config) ? $config : [];

        $columns = $this->columns($config['name'] ?? []);
        $fallback = $this->columns($config['fallback'] ?? []);

        if ($columns === [] && $fallback === []) {
            return [];
        }

        $source = $this->source($guard, $config);

        if ($source === null) {
            return [];
        }

        [$table, $key] = $source;

        try {
            $rows = DB::table($table)
                ->whereIn($key, $ids)
                ->get(array_values(array_unique([$key, ...$columns, ...$fallback])));
        } catch (Throwable) {
            return [];
        }

        $names = [];
        foreach ($rows as $row) {
            $name = $this->join($row, $columns) ?? $this->join($row, $fallback);

            if ($name !== null) {
                $names[(int) $row->{$key}] = $name;
            }
        }

        return $names;
    }

    /**
     * The name when resolvable, otherwise the label followed by the id.
     */
    public function display(string $guard, ?int $id): string
    {
        $label = $this->label($guard);

        if ($id === null) {
            return $label;
        }

        return $this->name($guard, $id) ?? $label.' #'.$id;
    }

    /**
     * The table and key for a guard, from an explicit config override or derived
     * from the guard's auth provider model.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: string}|null
     */
    private function source(string $guard, array $config): ?array
    {
        if (isset($config['table'])) {
            return [(string) $config['table'], (string) ($config['key'] ?? 'id')];
        }

        $model = $config['model'] ?? null;

        if (! is_string($model)) {
            $provider = config("auth.guards.{$guard}.provider");
            $model = is_string($provider) ? config("auth.providers.{$provider}.model") : null;
        }

        if (! is_string($model) || ! class_exists($model)) {
            return null;
        }

        try {
            $instance = new $model;

            return [$instance->getTable(), $instance->getKeyName()];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function columns(mixed $columns): array
    {
        if (is_string($columns)) {
            return [$columns];
        }

        return is_array($columns) ? array_values(array_filter($columns, 'is_string')) : [];
    }

    /**
     * @param  list<string>  $columns
     */
    private function join(object $row, array $columns): ?string
    {
        $parts = [];

        foreach ($columns as $column) {
            $part = $row->{$column} ?? null;

            if (is_string($part) && trim($part) !== '') {
                $parts[] = trim($part);
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}
