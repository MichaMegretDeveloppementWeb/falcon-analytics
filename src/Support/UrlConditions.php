<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * The URL conditions a campaign or an ad is matched on, as its form edits
 * them · one validation and one cleaning for both forms, so the two never ask
 * for different things.
 *
 * A form keeps its rows as `array<int, …>` and never as `list<…>`: removing a
 * row unsets it without reindexing, so a field's `wire:key` never moves onto
 * another row, and the rows carry holes until they are cleaned.
 *
 * @internal
 */
final class UrlConditions
{
    /** A row with nothing typed in it yet. */
    public const BLANK = ['param' => '', 'value' => ''];

    /**
     * The rows a form opens on · an empty one rather than none, the column
     * being nullable and an empty array saying the same thing as a null.
     *
     * @param  array<int, array{param: string, value: string}>|null  $conditions
     * @return array<int, array{param: string, value: string}>
     */
    public static function toEdit(?array $conditions): array
    {
        return $conditions === null || $conditions === [] ? [self::BLANK] : $conditions;
    }

    /**
     * The rows worth saving, trimmed and in order · a row missing either half
     * is dropped.
     *
     * @param  array<int, array{param: string, value: string}>  $conditions
     * @return list<array{param: string, value: string}>
     */
    public static function cleaned(array $conditions): array
    {
        $cleaned = [];

        foreach ($conditions as $condition) {
            $param = trim($condition['param']);
            $value = trim($condition['value']);

            if ($param !== '' && $value !== '') {
                $cleaned[] = ['param' => $param, 'value' => $value];
            }
        }

        return $cleaned;
    }

    /**
     * @param  string  $field  the form property holding the rows
     * @return array<string, list<string>>
     */
    public static function rules(string $field): array
    {
        return [
            $field => ['required', 'array', 'min:1'],
            $field.'.*.param' => ['required', 'string', 'max:100'],
            $field.'.*.value' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field): array
    {
        return [
            $field.'.required' => __('Ajoutez au moins une condition.'),
            $field.'.min' => __('Ajoutez au moins une condition.'),
            $field.'.*.param.required' => __('Le paramètre est obligatoire.'),
            $field.'.*.param.max' => __('Le paramètre ne doit pas dépasser :max caractères.'),
            $field.'.*.value.required' => __('La valeur est obligatoire.'),
            $field.'.*.value.max' => __('La valeur ne doit pas dépasser :max caractères.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(string $field): array
    {
        return [
            $field.'.*.param' => __('paramètre'),
            $field.'.*.value' => __('valeur'),
        ];
    }
}
