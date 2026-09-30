<?php

declare(strict_types=1);

/**
 * Fails when the lowest resolution did not install the floor each requirement
 * announces · run from the package root, after the lowest `composer update`.
 *
 * Only `require` is read: it is what a host must satisfy. `falcon/*` is left
 * out, taken from a sibling directory by the workshop.
 */
$manifest = json_decode((string) file_get_contents('composer.json'), true, 512, JSON_THROW_ON_ERROR);
$installed = json_decode((string) file_get_contents('vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);

$versions = [];

foreach ($installed['packages'] ?? $installed as $package) {
    $versions[$package['name']] = $package['version'];
}

$lifted = [];
$checked = [];

foreach ($manifest['require'] ?? [] as $name => $constraint) {
    if (! str_contains($name, '/') || str_starts_with($name, 'falcon/')) {
        continue;
    }

    $floor = floorOf($constraint);

    if ($floor === null) {
        fwrite(STDERR, "  {$name} · « {$constraint} » n'est pas de la forme ^X.Y, il n'est pas vérifié.\n");

        continue;
    }

    $version = $versions[$name] ?? null;
    $checked[] = "{$name} {$floor}";

    if ($version === null || threeParts($version) !== $floor) {
        $lifted[] = sprintf('  %s · annoncé %s, installé %s', $name, $floor, $version ?? 'rien');
    }
}

if ($lifted !== []) {
    fwrite(STDERR, "\n  Le plancher annoncé n'est pas celui qui a été installé · un avis de sécurité ou une dépendance l'a relevé :\n\n");
    fwrite(STDERR, implode("\n", $lifted)."\n\n");
    fwrite(STDERR, "  La ligne basse doit passer --no-blocking. Si elle le fait déjà, une dépendance exige plus que ce qui est annoncé · montez ce plancher-là.\n\n");

    exit(1);
}

echo "\n  Plancher éprouvé · ".implode(', ', $checked).".\n\n";

/** The lowest version a caret range allows, as « X.Y.Z », or null for any other form. */
function floorOf(string $constraint): ?string
{
    $floors = [];

    foreach (explode('||', $constraint) as $alternative) {
        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', trim($alternative), $parts) !== 1) {
            return null;
        }

        $floors[] = sprintf('%d.%d.%d', $parts[1], $parts[2] ?? 0, $parts[3] ?? 0);
    }

    usort($floors, 'version_compare');

    return $floors[0] ?? null;
}

/** « v13.12.0 » or « 13.12.0.0 » read as « 13.12.0 ». */
function threeParts(string $version): string
{
    $numbers = array_pad(explode('.', ltrim($version, 'v')), 3, '0');

    return implode('.', array_slice($numbers, 0, 3));
}
