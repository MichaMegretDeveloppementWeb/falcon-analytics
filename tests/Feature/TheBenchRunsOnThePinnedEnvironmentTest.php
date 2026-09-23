<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The tests run on the environment pinned in `phpunit.xml`, not on the one a
 * stray file gives them.
 *
 * `vendor/bin/testbench` drops its `.env.example` into the bench skeleton as
 * `.env`, which then applies to the whole suite. The pinned variables are set
 * before the application boots and are never replaced, so that file is
 * harmless · these tests watch the guarantee, not one way of breaking it.
 */
final class TheBenchRunsOnThePinnedEnvironmentTest extends TestCase
{
    /**
     * The pinned values, which nothing else may decide.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function pinned(): array
    {
        return [
            'le pilote de session' => ['SESSION_DRIVER', 'array', 'session.driver'],
            'le magasin de cache' => ['CACHE_STORE', 'array', 'cache.default'],
            'la file d’attente' => ['QUEUE_CONNECTION', 'sync', 'queue.default'],
            'la langue' => ['APP_LOCALE', 'fr', 'app.locale'],
        ];
    }

    /**
     * Read from the booted application, so a pinned value overridden further on
     * shows here.
     */
    #[DataProvider('pinned')]
    public function test_the_running_application_uses_what_we_chose(string $variable, string $value, string $key): void
    {
        $this->assertSame(
            $value,
            config($key),
            "L’application tourne avec un autre {$key} que celui qu’on a épinglé. ".
            'Si un `.env` traîne dans le squelette du banc, c’est qu’il n’est plus neutralisé.',
        );
    }

    public function test_the_dates_speak_the_pinned_language(): void
    {
        $this->assertSame('juil.', CarbonImmutable::parse('2026-07-09')->translatedFormat('M'));
    }

    /**
     * The test above also passes without a pin while no stray file exists · this
     * one fails as soon as the line leaves `phpunit.xml`.
     */
    #[DataProvider('pinned')]
    public function test_the_configuration_pins_it_before_anything_else_can(string $variable, string $value, string $key): void
    {
        $declaration = (string) file_get_contents(dirname(__DIR__, 2).'/phpunit.xml');

        $this->assertStringContainsString(
            sprintf('<env name="%s" value="%s"/>', $variable, $value),
            $declaration,
            "phpunit.xml n’épingle plus {$variable}. Ce qui y est déclaré est posé AVANT que ".
            'l’application démarre, et le chargeur du cadre ne remplace jamais une variable déjà '.
            'posée · sans cette ligne, un `.env` déposé dans le squelette du banc décide à sa place, '.
            'pour toute la suite, et les essais qui tombent ne parlent pas de lui.',
        );
    }
}
