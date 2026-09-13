# Journal des versions

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et la
numérotation suit [SemVer](https://semver.org/lang/fr/).

**Ce qu'une entrée doit toujours contenir** · ce qui a changé, et **ce que
l'intégrateur a à faire**. Une version qui demande une action et ne le dit pas
est une version qui casse un projet un lundi matin.

---

## [1.0.0] — à paraître

**La première version de la suite Falcon.**

Ce qui précède ce numéro n'est pas documenté ici, et c'est délibéré · la suite a
été rebâtie sur un socle commun, et son histoire antérieure décrivait une
architecture qui n'existe plus.

### Ce que le paquet apporte

- **Quatorze écrans d'administration** · vue d'ensemble, temps réel, visiteurs
  et sessions avec leur détail, événements, tunnels, intégrations, et cinq
  écrans marketing dont trois en écriture ;
- **un collecteur** qui capture seul les pages vues et les clics — et seulement
  sur ce qui est réellement interactif — posé par **une directive**,
  `@analyticsCollector` ;
- **des événements nommés**, déclarés dans un fichier de l'hôte, marquables
  comme conversions ;
- **des tunnels**, y compris à branches parallèles, évalués sur ces événements ;
- **l'attribution publicitaire** au rapport, sur les paramètres d'URL capturés à
  l'arrivée · une campagne créée après coup retrouve ses sessions ;
- **l'identité de vos visiteurs** · le paquet lit vos gardes d'authentification,
  donc un visiteur est *ce client-là*, ce qu'aucun outil externe ne peut faire ;
- **Google Search Console**, pour les vraies requêtes organiques ;
- **la géolocalisation locale**, par base MaxMind téléchargée chez vous ;
- **neuf commandes** · installation, diagnostic, deux pour les événements, deux
  pour la géolocalisation, une pour la Search Console, et deux d'entretien
  qu'un ordonnanceur déclenche seul ;
- **quarante-cinq réglages**, tous facultatifs · le paquet fonctionne sans
  qu'on en touche un seul ;
- **des fichiers déjà compilés** · aucun Node n'est requis chez l'hôte.

### Ce que l'intégrateur a à faire

1. déclarer les deux dépôts — le paquet **et** `falcon/ui-kit` —, installer,
   lancer `php artisan analytics:install` ;
2. poser `@analyticsCollector` dans le gabarit public à mesurer · **sans elle,
   aucune page n'est mesurée**, et le collecteur n'est même pas téléchargé ;
3. renseigner ses gardes dans le bloc `identity` de `config/analytics.php` ;
4. derrière un proxy, déclarer ses proxies de confiance · sinon toutes les
   visites portent la même adresse ;
5. republier les fichiers **à chaque déploiement** ·
   `php artisan vendor:publish --tag=laravel-assets --force`.

**Rien à écrire dans une feuille de style ni dans un script.** Le paquet compile
ses propres écrans et livre `analytics.css` déjà fait ; le kit le sert. Un import
du collecteur dans votre bundle en donnerait deux.

> **Un point mérite l'attention** · `schedule:run` doit tourner chaque minute.
> Sans lui les sessions inactives ne se ferment jamais, et les événements bruts
> ne s'effacent pas. **Aucune tâche propre à analytics n'est à créer** · le
> paquet inscrit les siennes dans l'ordonnanceur de Laravel.

### Les migrations

Le paquet crée **huit tables** préfixées `falcon_analytics_`, chargées
automatiquement · un `php artisan migrate` suffit, et `analytics:install` le
lance pour vous.

Elles sont toutes réversibles. Ce qui ne veut pas dire qu'il faille les
redescendre · voir [mise-a-jour.md](docs/mise-a-jour.md#le-schéma-qui-est-la-vraie-question).

### Ce qu'il exige

PHP 8.5, Laravel 13, Livewire 4.2, et une base MySQL, MariaDB ou PostgreSQL.
`falcon/ui-kit` vient avec le paquet.

> **Le plancher est celui de toute la suite**, pas une exigence de ce paquet
> seul · l'hôte et les autres paquets l'annoncent à l'identique. Une suite dont
> les membres réclament trois versions différentes ne promet rien de vérifiable.

### La vie privée

**Sans consentement, aucun identifiant ne survit à la session** — et c'est le
comportement par défaut, pas une option à activer. Les données restent dans
votre base ; aucun tiers, aucun service externe, aucun démon.

Les événements bruts s'effacent au bout de quatre-vingt-dix jours par défaut ;
les agrégats restent.
