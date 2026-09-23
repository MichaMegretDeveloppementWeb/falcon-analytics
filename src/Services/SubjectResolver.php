<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Falcon\Analytics\DTOs\Dashboard\SubjectName;
use Falcon\Analytics\Repositories\SubjectReadRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves a human label and display name for a tracked subject (guard + id),
 * driven entirely by config so the package stays host-agnostic. Names are read
 * from the guard's own model at render time and are never stored.
 *
 * @internal it serves the screens, and a host reaches it through its
 *           configuration, never by its name. Not to be confused with
 *           `Analytics::resolveSubjectUsing()`, which says WHO is being
 *           tracked · this one only puts a readable name on the answer.
 */
final class SubjectResolver
{
    /**
     * The DB reads default so a plain `new SubjectResolver` still works (tests,
     * ad-hoc use); the container injects the shared repository otherwise.
     */
    public function __construct(private SubjectReadRepository $subjects = new SubjectReadRepository) {}

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

        return $this->subjects->matchingIds($table, $key, $columns, $term);
    }

    /**
     * Batch-resolve display names for several ids of one guard in a single query.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(string $guard, array $ids): array
    {
        // An identifier is 1 or more: zero designates nobody, and letting it
        // through would query a key that does not exist.
        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (int $id): bool => $id > 0,
        )));

        $source = $ids === [] ? null : $this->nameSource($guard);

        if ($source === null) {
            return [];
        }

        ['table' => $table, 'key' => $key, 'columns' => $columns, 'fallback' => $fallback] = $source;

        $rows = $this->subjects->rows($table, $key, $ids, array_values(array_unique([$key, ...$columns, ...$fallback])));

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
     * Where a guard's names are read · its table and key, the columns that make
     * up a name and those that stand in for one · null when the configuration
     * names nothing readable.
     *
     * @return array{table: string, key: string, columns: list<string>, fallback: list<string>}|null
     */
    private function nameSource(string $guard): ?array
    {
        $config = config("analytics.identity.subjects.{$guard}");
        $config = is_array($config) ? $config : [];

        $columns = $this->columns($config['name'] ?? []);
        $fallback = $this->columns($config['fallback'] ?? []);

        $source = $columns === [] && $fallback === [] ? null : $this->source($guard, $config);

        if ($source === null) {
            return null;
        }

        return ['table' => $source[0], 'key' => $source[1], 'columns' => $columns, 'fallback' => $fallback];
    }

    /**
     * The name when resolvable, otherwise the label followed by the id.
     */
    public function display(string $guard, ?int $id): string
    {
        if ($id === null) {
            return $this->label($guard);
        }

        return $this->shownNames([[$guard, $id]])[$guard.':'.$id]->name;
    }

    /**
     * How each subject is shown, read in one query per guard.
     *
     * @param  list<array{string, int}>  $subjects  guard and id pairs
     * @return array<string, SubjectName> keyed by "guard:id"
     */
    public function shownNames(array $subjects): array
    {
        $idsByGuard = [];
        foreach ($subjects as [$guard, $id]) {
            $idsByGuard[$guard][] = $id;
        }

        $shown = [];
        foreach ($idsByGuard as $guard => $ids) {
            $label = $this->label($guard);
            $names = $this->names($guard, $ids);

            foreach ($ids as $id) {
                $shown[$guard.':'.$id] = isset($names[$id])
                    ? new SubjectName($names[$id], $label, true)
                    : new SubjectName($label.' #'.$id, $label, false);
            }
        }

        return $shown;
    }

    /**
     * Where a guard's names are read from, for whoever needs to check it.
     *
     * The diagnostic asks this to say whether the columns a host named exist.
     * It goes through the same derivation the reads use, so the diagnostic
     * never approves an installation whose reads fail.
     *
     * @return array{0: string, 1: string}|null
     */
    public function sourceFor(string $guard): ?array
    {
        $config = config("analytics.identity.subjects.{$guard}");

        return $this->source($guard, is_array($config) ? $config : []);
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

            // `auth.providers.*.model` may name any class, and one that is not
            // an Eloquent model would die on `getTable()` below.
            if (! $instance instanceof Model) {
                return null;
            }

            return [$instance->getTable(), $instance->getKeyName()];
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Analytics subject source resolution failed.', ['exception' => $e]);

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
        // Read as an array, not through variable property names, so static analysis can follow it.
        $values = (array) $row;
        $parts = [];

        foreach ($columns as $column) {
            $part = $values[$column] ?? null;

            if (is_string($part) && trim($part) !== '') {
                $parts[] = trim($part);
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}
