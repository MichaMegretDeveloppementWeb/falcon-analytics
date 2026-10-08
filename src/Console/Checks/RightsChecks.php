<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console\Checks;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Support\BranchMiddleware;
use Falcon\Analytics\Support\PersistentMiddlewareResolver;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The three points of `analytics:check` about rights · the door of the two
 * screen groups, the names rules are written under, and the steps laid on a
 * branch of the ability tree.
 *
 * @internal
 */
final class RightsChecks
{
    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function all(Config $config): array
    {
        return [
            $this->moduleMiddleware($config),
            $this->abilityNames(),
            $this->branchMiddleware($config),
        ];
    }

    /**
     * The two screen groups and what guards them. An explicitly empty list
     * mounts them with neither session nor authentication; the log says so at
     * boot, where nobody reads it. A list without authentication lets nobody
     * be recognised, unless the host's rule on the root opens the screens to
     * people who are not signed in.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function moduleMiddleware(Config $config): array
    {
        $exposed = [];
        $unauthenticated = [];

        foreach (self::screenGroups() as $key => $label) {
            $door = $config->get($key.'.middleware', ['web', 'auth']);

            if ($door === []) {
                $exposed[] = $label.' ('.$key.'.middleware)';
            } elseif (! $this->authenticates((array) $door)) {
                $unauthenticated[] = $label.' ('.$key.'.middleware)';
            }
        }

        if ($exposed !== []) {
            return ['Protection', 'KO', 'Monté sans aucune protection, donc publiquement joignable · '.implode(' · ', $exposed)];
        }

        if ($unauthenticated === []) {
            return ['Protection', 'OK', 'Les deux groupes d’écrans sont montés derrière une authentification.'];
        }

        if (Gate::forUser(null)->allows(Ability::Analytics)) {
            return ['Protection', 'À voir', 'Sans authentification, et ouvert aux personnes non connectées par votre règle sur analytics · '.implode(' · ', $unauthenticated)];
        }

        return [
            'Protection',
            'KO',
            'Aucune authentification sur la porte, donc personne n’y est reconnu et chaque écran répond 403 · '
            .implode(' · ', $unauthenticated).'. Ajoutez auth, ou auth:{garde}.',
        ];
    }

    /**
     * A rule written under a name no ability carries: the restriction it was
     * meant for never applies, and nothing else says so.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function abilityNames(): array
    {
        $unknown = array_values(array_filter(
            array_keys(Gate::abilities()),
            fn (string $name): bool => ($name === 'analytics' || str_starts_with($name, 'analytics.')) && Ability::tryFrom($name) === null,
        ));

        if ($unknown === []) {
            return ['Règles de droits', 'OK', 'Chaque règle écrite sous un nom d’analytics désigne une capacité du paquet.'];
        }

        return ['Règles de droits', 'KO', 'Ces noms ne désignent aucune capacité, leur règle ne s’applique donc jamais · '.implode(', ', $unknown)];
    }

    /**
     * The middleware a host lays on a branch of the ability tree. A key must
     * name an ability some screen asks or sits under, a gesture having no
     * address, and every middleware must be known to the router.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function branchMiddleware(Config $config): array
    {
        $problems = [];
        $asked = $this->abilitiesTheScreensAsk();

        foreach ((array) $config->get('analytics.admin.middleware_for', []) as $key => $listed) {
            $branch = Ability::tryFrom((string) $key);

            if ($branch === null) {
                $problems[] = $key.' ne désigne aucune capacité';
            } elseif (array_filter($asked, fn (Ability $ability): bool => $ability->isWithin($branch)) === []) {
                $problems[] = $key.' ne couvre aucun écran, un geste n’ayant pas d’adresse';
            }

            foreach (array_filter((array) $listed, fn (mixed $middleware): bool => ! $this->routerKnows($middleware)) as $unknown) {
                $problems[] = (is_string($unknown) ? $unknown : gettype($unknown)).', sous '.$key.', est inconnu du routeur';
            }
        }

        if ($problems === []) {
            return ['Étapes par branche', 'OK', 'Chaque étape de analytics.admin.middleware_for couvre des écrans et existe.'];
        }

        return ['Étapes par branche', 'KO', implode(' · ', $problems)];
    }

    /** @param  array<array-key, mixed>  $door */
    private function authenticates(array $door): bool
    {
        $router = app(Router::class);
        $resolved = (new PersistentMiddlewareResolver(aliases: $router->getMiddleware(), groups: $router->getMiddlewareGroups()))
            ->resolve(array_values(array_filter($door, is_string(...))));

        return array_filter($resolved, fn (string $class): bool => is_a($class, Authenticate::class, true)) !== [];
    }

    /** @return list<Ability> */
    private function abilitiesTheScreensAsk(): array
    {
        $asked = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'analytics.admin.') && ($ability = BranchMiddleware::abilityAskedBy($route)) !== null) {
                $asked[] = $ability;
            }
        }

        return $asked;
    }

    private function routerKnows(mixed $middleware): bool
    {
        if (! is_string($middleware) || $middleware === '') {
            return false;
        }

        $name = Str::before($middleware, ':');
        $router = app(Router::class);

        return isset($router->getMiddleware()[$name]) || isset($router->getMiddlewareGroups()[$name]) || class_exists($name);
    }

    /**
     * The two groups of screens the administration holds, by config block.
     *
     * Marketing sits inside the admin block: it is a second entity of the same
     * area, with its own address and its own guard.
     * The layout is not among them — it belongs to the area, and both entities
     * of the administration are drawn by the same one.
     *
     * @return array<string, string>
     */
    private static function screenGroups(): array
    {
        return [
            'analytics.admin' => 'Tableau de bord',
            'analytics.admin.marketing' => 'Marketing',
        ];
    }
}
