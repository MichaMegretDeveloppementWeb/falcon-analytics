<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Support\AbilityDefaults;
use Illuminate\Auth\AuthManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Gate;

/**
 * Draws the tree of abilities, says who answers each one, and, for a given
 * account, what they answer.
 *
 * @internal
 */
final class AbilitiesCommand extends Command
{
    protected $signature = 'analytics:abilities
        {--user= : L’identifiant d’un compte, pour voir ce que chaque capacité lui répond}
        {--guard= : Le garde de ce compte, celui de la porte des écrans quand il n’est pas nommé}';

    protected $description = 'Affiche les capacités d’analytics, qui répond à chacune, et ce qu’elles répondent à un compte.';

    public function handle(AbilityDefaults $defaults, AuthManager $auth, Config $config): int
    {
        $id = $this->option('user');
        $account = null;

        if (is_string($id) && $id !== '') {
            $guard = $this->guardOfTheAccount($config);
            $account = $this->accountOf($auth, $config, $guard, $id);

            if ($account === null) {
                $this->components->error("Aucun compte {$id} pour le garde {$guard}.");

                return self::FAILURE;
            }
        }

        $this->table(
            $account === null ? ['Capacité', 'Qui répond'] : ['Capacité', 'Qui répond', 'Réponse'],
            array_map(fn (Ability $ability): array => $this->rowOf($ability, $defaults, $account), Ability::cases()),
        );

        if ($account !== null) {
            $this->components->info('Réponses données sans objet : une règle qui regarde une campagne, une publicité ou un visiteur peut répondre autrement sur l’un d’eux.');
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function rowOf(Ability $ability, AbilityDefaults $defaults, ?Authenticatable $account): array
    {
        $row = [str_repeat('  ', $this->depthOf($ability)).$ability->value, $this->answeredBy($ability, $defaults)];

        if ($account !== null) {
            $row[] = Gate::forUser($account)->allows($ability) ? 'oui' : 'non';
        }

        return $row;
    }

    private function answeredBy(Ability $ability, AbilityDefaults $defaults): string
    {
        $parent = $ability->parent();

        return match (true) {
            ! $defaults->answeredByDefault($ability) => 'votre règle',
            $parent === null => 'par défaut · tout compte connecté',
            default => 'suit '.$parent->value,
        };
    }

    private function depthOf(Ability $ability): int
    {
        $depth = 0;

        for ($above = $ability->parent(); $above !== null; $above = $above->parent()) {
            $depth++;
        }

        return $depth;
    }

    /** The guard named, or the one the screens' door authenticates with, or the application's default. */
    private function guardOfTheAccount(Config $config): string
    {
        $named = $this->option('guard');

        if (is_string($named) && $named !== '') {
            return $named;
        }

        foreach ((array) $config->get('analytics.admin.middleware', []) as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'auth:')) {
                return explode(',', substr($middleware, 5))[0];
            }
        }

        return (string) $config->get('auth.defaults.guard');
    }

    private function accountOf(AuthManager $auth, Config $config, string $guard, string $id): ?Authenticatable
    {
        $provider = $config->get("auth.guards.{$guard}.provider");

        if (! is_string($provider)) {
            return null;
        }

        return $auth->createUserProvider($provider)?->retrieveById($id);
    }
}
