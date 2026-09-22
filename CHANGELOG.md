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
- **dix commandes** · installation, diagnostic, deux pour les événements, deux
  pour la géolocalisation, une pour la Search Console et trois d'entretien —
  **dont cinq que le paquet planifie lui-même**, sans que vous ayez de tâche à
  créer ;
- **une conservation qui ne fausse aucun chiffre** · le pas à pas d'une session
  s'efface au bout de 90 jours, et c'est tout ce qui s'efface · les pages et
  clics les plus vus sont comptés d'avance chaque nuit, et **tout ce qui porte un
  nom est gardé pour toujours**. Aucune période maximale d'affichage ;
- **trente-sept réglages**, tous facultatifs · le paquet fonctionne sans qu'on en
  touche un seul, et un essai le tient ;
- **des fichiers déjà compilés** · aucun Node n'est requis chez l'hôte. Le
  script des écrans, `analytics-admin.js`, ne part que vers les écrans
  d'administration · une page publique ne reçoit que le collecteur. La carte du
  monde du temps réel est un fichier de plus, `world-map.svg`, que le navigateur
  garde · l'écran se rafraîchit toutes les dix secondes sans la renvoyer.
  **Rien n'est à faire pour cela**, la republication du point 5 plus bas les
  livre ;
- **des listes dont une ligne ne coûte rien à dessiner** · la source, la page,
  le pays et les conditions d'une campagne passent par le raccourci du kit, et
  une page de vingt sessions coûte autant qu'une seule ;
- **des écrans qui ne se publient pas** · ils appartiennent au paquet, qui les
  renouvelle à chaque version. Votre gabarit les habille tous, et peut en
  distinguer un par son nom de route · voir
  [installation.md](docs/installation.md#un-écran-en-particulier).

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
> Sans lui les sessions inactives ne se ferment jamais, et les jours clos ne
> sont plus résumés. **Aucune tâche propre à analytics n'est à créer** · le
> paquet inscrit les siennes dans l'ordonnanceur de Laravel.
>
> **Et s'il s'arrête, rien n'est perdu** · l'effacement refuse un jour que le
> résumé n'a pas traité, donc les deux s'arrêtent ensemble. Ouvrir un écran
> d'analytique rattrape le retard, et `analytics:check` dit où on en est.

### Les migrations

Le paquet crée **dix tables** préfixées `falcon_analytics_`, chargées
automatiquement · un `php artisan migrate` suffit, et `analytics:install` le
lance pour vous. Huit portent vos mesures ; les deux autres sont la mécanique de
la conservation, et ne sont pas une interface.

Elles sont toutes réversibles. Ce qui ne veut pas dire qu'il faille les
redescendre · voir [mise-a-jour.md](docs/mise-a-jour.md#le-schéma-qui-est-la-vraie-question).

> **⚠️ Un projet installé avant cette version ne se monte pas par `migrate`.**
> Le schéma a été repris à neuf · les vingt-deux migrations qui le construisaient
> par touches successives sont devenues dix, une par table. **Les tables du
> paquet se suppriment et se rejouent**, par `php artisan analytics:refresh`, et
> ce qu'il avait mesuré est perdu.
>
> **La procédure, en local puis en production** ·
> [reprise-du-schema.md](docs/reprise-du-schema.md). Ce fichier est temporaire
> et disparaîtra quand tous les projets existants seront montés.
>
> Ce que la reprise ferme · les instants quittent un type que le moteur
> convertit contre le fuseau du serveur, qui faisait lire au disque une heure
> décalée, et refusait l'heure que la pendule locale saute au passage à l'heure
> d'été.

### Ce qu'il exige

PHP 8.5, Laravel 13, Livewire 4.2, et une base **MySQL ou MariaDB**.
`falcon/ui-kit` vient avec le paquet.

Les visites sont mesurées sur **tout navigateur sorti depuis 2018** · Chrome 39,
Firefox 31, Safari 11.1, Edge 14 ou plus récents. Le script de mesure est
compilé pour eux, et un essai le relit.

> **Le plancher est celui de toute la suite**, pas une exigence de ce paquet
> seul · l'hôte et les autres paquets l'annoncent à l'identique. Une suite dont
> les membres réclament trois versions différentes ne promet rien de vérifiable.

### La vie privée

**Sans consentement, aucun identifiant ne survit à la session** — et c'est le
comportement par défaut, pas une option à activer. Les données restent dans
votre base ; aucun tiers, aucun service externe, aucun démon.

**L'adresse IP est tronquée par défaut**, et le cookie d'un visiteur qui a
consenti dure **treize mois au plus**, le plafond que la CNIL fixe · sans que
la localité y perde, puisqu'elle est lue avant la troncature.

**Le pas à pas d'une session ne se consulte que quatre-vingt-dix jours** par
défaut · au-delà, les pages vues et les clics anonymes sont effacés. Ce qui
porte un nom est gardé, les chiffres des écrans ne bougent pas, et l'écran de
cette session dit ce qu'il a perdu. Écrivez `null` pour ne jamais rien effacer.
