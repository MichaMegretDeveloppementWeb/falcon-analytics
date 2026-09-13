# Installer Falcon Analytics

Une commande, puis trois choses à écrire vous-même. Comptez un quart d'heure.

---

## Ce qu'il vous faut

| | |
|---|---|
| **PHP** | 8.5 ou plus |
| **Laravel** | 13 |
| **Livewire** | 4.2 ou plus · dépendance partagée, jamais embarquée |
| **Une base** | MySQL, MariaDB ou PostgreSQL · le paquet crée huit tables préfixées `falcon_analytics_` |
| **L'ordonnanceur** | `schedule:run` déclenché chaque minute · sans lui, les sessions ne se ferment pas et les vieux événements ne s'effacent jamais |
| **Node** | **non** · le paquet livre ses fichiers déjà compilés |

`falcon/ui-kit` vient avec, et vous n'avez pas à l'installer séparément.

> **Un hôte déjà en Livewire 4 n'a rien à faire.** Un hôte en Livewire 3 obtient
> un conflit de version franc · montez-le d'abord, rien ne casse en silence.

---

## 1 · Dire à Composer où trouver les paquets

Le paquet est distribué depuis un dépôt git privé. **Composer ne lit les
`repositories` que dans le `composer.json` racine**, donc votre application doit
déclarer celui du paquet **et** celui de sa dépendance `falcon/ui-kit`.

```jsonc
// composer.json de l'application hôte
"repositories": [
    { "type": "vcs", "url": "https://github.com/MichaMegretDeveloppementWeb/falcon-analytics.git" },
    { "type": "vcs", "url": "https://github.com/MichaMegretDeveloppementWeb/falcon-ui-kit.git" }
]
```

En monodépôt, un dépôt `path` avec `"symlink": true` pointant sur le dossier du
paquet fonctionne de la même manière.

### S'authentifier

Les deux dépôts sont privés · Composer a besoin d'un identifiant GitHub en
lecture, sans quoi `composer require` échoue sur « Could not find package ». **Un
seul jeton couvre les deux.**

- **Sur votre machine** · lancez la commande et suivez l'invite. Composer vous
  renvoie vers `https://github.com/settings/tokens/new?scopes=repo`, vous collez
  le jeton une fois, et il est retenu pour toute la machine dans
  `~/.composer/auth.json` — **jamais dans le projet**. Ou réglez-le d'avance ·
  `composer config --global github-oauth.github.com ghp_VOTRE_JETON`.
- **Sur un serveur** · la même commande en SSH. Un serveur dont le git système
  s'authentifie déjà auprès de GitHub par clé SSH fonctionne aussi.
- **En intégration continue** · le jeton en variable d'environnement au moment
  de l'installation ·
  `COMPOSER_AUTH='{"github-oauth":{"github.com":"<jeton>"}}'`.

---

## 2 · Installer

```bash
composer require falcon/analytics
php artisan analytics:install
```

**Ce que la commande fait**, dans cet ordre ·

1. elle installe le kit · `ui-kit:install`, sans question ;
2. elle publie `config/analytics.php` ;
3. elle publie la feuille et le collecteur **compilés**, en écrasant · ce sont
   des fichiers générés, il n'y a rien de vôtre à préserver, et une copie périmée
   est pire qu'aucune — le kit la compare à ce que le paquet livre et lève à
   l'affichage plutôt que de servir celle du mois dernier ;
4. elle ajoute sept des huit variables `ANALYTICS_*` à `.env` et `.env.example` ·
   seules celles qui manquent, groupées et commentées, **jamais une valeur
   existante**, et sans rien créer si le fichier est absent ;
5. elle lance les migrations.

Elle n'accepte qu'une option · `--force`, qui réécrit la configuration déjà
publiée. **Elle se rejoue sans dommage.**

> **Elle ne pose aucune question**, et elle n'écrit dans aucun de vos fichiers de
> style ou de script. Elle finit en disant ce qui reste à votre charge, et c'est
> la section suivante.

---

## 3 · Les trois choses à écrire

### La directive, sur les pages à mesurer

```blade
@analyticsCollector
```

Dans le gabarit public que vous voulez suivre, n'importe où. **Sans elle, aucune
page n'est mesurée** · le collecteur n'est même pas téléchargé.

Elle ne rend **rien du tout** quand le suivi est coupé ou que le contexte est
exclu — pas même le chargement du fichier. C'est la meilleure façon de ne pas
mesurer.

> **Sur les pages d'administration, ne la posez pas** · on ne mesure pas les
> visites de qui consulte le tableau de bord.

### Votre identité

Dans `config/analytics.php`, le bloc `identity` · quels gardes désignent le sujet
suivi, lesquels sont exclus, et le cookie de consentement s'il y en a un. Le
détail est dans [configuration.md](configuration.md).

**Si vous employez un cookie de consentement**, nommez-le dans `consent_cookie`
et **c'est tout** · le paquet le sort lui-même du chiffrement de Laravel. Une
bannière écrit ce cookie en JavaScript, donc en clair ; relu à travers le
chiffrement il vaudrait `null`, le consentement ne serait jamais vu, et **tous
vos visiteurs resteraient anonymes sans un mot**.

Le paquet ne reconnaît le consentement qu'à la valeur exacte `"1"`.

### Derrière un proxy

Réglez les proxies de confiance de Laravel pour que la véritable adresse du
client parvienne au paquet. **C'est le réglage qui fait mentir un tableau de bord
plutôt que de le laisser vide**, et c'est ce qui le rend cher à trouver · sans
lui, toutes les visites portent l'adresse du proxy, donc **un seul pays et une
seule ville pour tout le site**, et `exclude_ips` qui exclut tout le monde ou
personne.

Les comptages de visiteurs, eux, ne bougent pas · un visiteur est un cookie ou
une session, jamais une adresse.

C'est un réglage de Laravel, pas du paquet, et **sans proxy il n'y a rien à
faire**.

---

## Ce que vous n'avez **pas** à faire

Trois choses qu'on croit souvent devoir écrire, et qui sont déjà faites ·

| | |
|---|---|
| **importer une feuille de style à nous** | non · le paquet **compile ses propres écrans** et livre `analytics.css` déjà fait. Le kit le sert. Votre compilation n'a jamais à lire nos vues |
| **importer le collecteur dans votre script** | non · `@analyticsCollector` déclare le fichier compilé au kit, qui le pose. Un import en plus vous donnerait **deux collecteurs** |
| **recompiler après une mise à jour du paquet** | non, pour nos écrans · republiez, c'est tout. Votre `npm run build` ne concerne que votre propre HTML |

> **La ligne que `ui-kit:install` vous donne est une autre affaire** · elle sert
> au HTML que **vous** écrivez, si vous y employez les noms du kit comme
> `bg-surface`. Elle n'a rien à voir avec nos écrans. Voir la notice du kit.

---

## 4 · Monter les écrans

Au défaut, les écrans se rendent dans **la coquille autonome du paquet** · sa
barre latérale, sa barre du haut, sa bascule de thème. Une administration prête
à l'emploi, et vous n'avez rien à écrire.

Pour les mettre dans votre propre habillage, nommez votre composant Blade dans
`admin.layout`, puis liez les écrans depuis votre navigation ·

```blade
<a href="{{ route('analytics.admin.overview') }}">Vue d'ensemble</a>
<a href="{{ route('analytics.admin.realtime') }}">Temps réel</a>
```

**Les noms de routes ne bougent jamais ; seules les adresses se règlent.** La
liste complète est dans [fonctionnalites.md](fonctionnalites.md).

`admin.layout` attend le nom **du composant tel que vous l'écririez** ·
`'layout.admin'` pour `<x-layout.admin>`. Il vaut pour toute l'administration,
marketing compris · c'est la même, et on n'en habille pas la moitié.

Votre composant doit ·

1. **déclarer une propriété `title`** · l'écran la lui passe. Sans `@props`, le
   titre part dans les attributs et finit écrit sur votre balise racine ;
2. **rendre `{{ $slot }}`**, comme n'importe quel composant ;
3. écrire `{{ falcon_theme_class() }}` **dans l'attribut `class`** de l'élément
   qui contient nos écrans, s'il veut le mode sombre.

Et c'est tout · **nos feuilles ne sont pas à charger**. L'écran déclare la
sienne avant de vous appeler, le kit la pose, et votre compilation à vous ne
concerne que votre propre habillage.

```blade
{{-- resources/views/components/layout/admin.blade.php --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="fr" class="h-full {{ falcon_theme_class() }}">
<head>
    <title>{{ $title }}</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="h-full bg-gray-50 dark:bg-gray-950">
    {{-- votre navigation --}}
    <main>{{ $slot }}</main>
</body>
</html>
```

> **Du Tailwind ordinaire dans cet exemple, à dessein.** Écrire `bg-page` ou
> `border-base` ici demande que **votre** compilation connaisse les noms du kit —
> c'est la ligne que `ui-kit:install` vous donne, et sans elle une classe
> inconnue ne lève rien du tout · un fond qui manque, et aucune explication.

> **Un nom qui ne désigne aucun composant fait tomber les écrans à la première
> visite**, et c'est le genre d'erreur qu'on découvre en production.
> `analytics:check` le vérifie pour vous, sans attendre cette visite.

**Protégez les écrans** avec votre middleware d'administration ·
`admin.middleware`, et `admin.marketing.middleware` pour les écrans marketing,
qui peuvent répondre à un autre garde.

---

## 5 · Vérifier

```bash
php artisan analytics:check
```

Il rend un tableau de **dix points** et s'arrête en échec s'il en trouve un
bloquant · les migrations passées, l'interrupteur général, la directive posée
dans vos vues, le point de collecte joignable, le middleware des écrans, le
gabarit que vous avez nommé, la feuille publiée à jour, l'identité, le proxy et
la géolocalisation.

**Lancez-le après chaque déploiement.** Analytics échoue en silence · un
collecteur jamais rendu, un point de collecte derrière le mauvais middleware ou
l'interrupteur resté à `false` donnent tous des écrans qui marchent et un tableau
de bord vide — et un tableau de bord vide se lit « personne n'est venu » plutôt
que « rien n'a été mesuré ».

Puis chargez une page publique portant la directive · un visiteur doit apparaître
dans les secondes qui suivent, sur l'écran temps réel.

**Facultatif, et recommandé** · la [géolocalisation](fonctionnalites.md#la-géolocalisation),
une clé gratuite et une commande.

---

## 6 · Déployer

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan vendor:publish --tag=laravel-assets --force
php artisan view:cache
```

**La republication n'est pas optionnelle.** Le kit compare la copie que vous
servez au fichier que le paquet livre, et **refuse de servir une copie
périmée** · vos pages lèveraient une erreur nommant le fichier et la commande.

Et assurez-vous que l'ordonnanceur tourne ·

```
* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
```

**Aucune tâche propre à analytics n'est à créer** · le paquet inscrit les
siennes dans l'ordonnanceur de Laravel.

---

## Désinstaller

**Retirez `@analyticsCollector` de vos gabarits d'abord.** C'est une directive
Blade, et une directive que plus personne ne déclare **s'affiche en clair dans la
page**, telle quelle, sans la moindre erreur. Un défaut qui se voit sur le site
public et qui ne se signale nulle part.

Ensuite ·

```bash
composer remove falcon/analytics
rm -rf public/vendor/falcon/analytics resources/views/vendor/analytics
rm config/analytics.php
```

Les variables `ANALYTICS_*` de `.env` et `.env.example` sont à retirer à la main.

**Les huit tables `falcon_analytics_*` restent**, et aucune commande ne les
supprime · le paquet n'efface pas des données qu'on ne lui a pas demandé
d'effacer. Elles se retirent quand vous êtes sûr de ne plus vouloir l'historique,
et une sauvegarde d'ici là ne coûte rien.

> **Le kit ne part pas avec.** S'il ne sert plus à rien d'autre, il a sa propre
> commande · `php artisan ui-kit:uninstall`, qui dit ce qui reste à défaire.
