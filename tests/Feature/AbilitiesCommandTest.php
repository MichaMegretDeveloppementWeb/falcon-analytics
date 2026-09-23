<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

/**
 * `analytics:abilities` · the tree a host restricts, who answers each ability,
 * and what they answer for one account.
 */
final class AbilitiesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_draws_every_ability_and_who_answers_it(): void
    {
        Gate::define(Ability::Marketing, fn (TestAdmin $admin): bool => false);

        $output = $this->outputOf([]);

        $this->assertMatchesRegularExpression('/\| analytics\s+\| par défaut · tout compte connecté/u', $output);
        $this->assertMatchesRegularExpression('/\|\s+analytics\.overview\s+\| suit analytics\.audience/u', $output);
        $this->assertMatchesRegularExpression('/\|\s+analytics\.marketing\s+\| votre règle/u', $output);
        $this->assertMatchesRegularExpression('/\|\s+analytics\.campaigns\.delete\s+\| suit analytics\.campaigns\.edit/u', $output);
        $this->assertSame(count(Ability::cases()), preg_match_all('/\|\s+analytics[.a-z-]*\s+\|/', $output));
    }

    public function test_it_says_what_each_ability_answers_one_account(): void
    {
        $admin = TestAdmin::create([]);
        Gate::define(Ability::Marketing, fn (TestAdmin $account): bool => false);

        $output = $this->outputOf(['--user' => (string) $admin->id]);

        $this->assertMatchesRegularExpression('/\|\s+analytics\.overview\s+\| suit analytics\.audience\s+\| oui/u', $output);
        $this->assertMatchesRegularExpression('/\|\s+analytics\.ads\.delete\s+\| suit analytics\.ads\.edit\s+\| non/u', $output);
        $this->assertStringContainsString('Réponses données sans objet', $output);
    }

    public function test_it_refuses_an_account_that_does_not_exist(): void
    {
        $this->artisan('analytics:abilities', ['--user' => '999'])
            ->expectsOutputToContain('Aucun compte 999 pour le garde admin')
            ->assertFailed();
    }

    /** @param  array<string, string>  $options */
    private function outputOf(array $options): string
    {
        $this->assertSame(0, Artisan::call('analytics:abilities', $options));

        return Artisan::output();
    }
}
