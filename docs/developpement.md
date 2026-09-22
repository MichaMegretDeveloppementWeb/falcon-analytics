# Travailler sur Falcon Analytics

---

## Ce qu'il faut installer

```bash
cd packages/falcon-analytics
composer install
npm ci
cp .env.test.example .env.test
mysql -u root -p -e "CREATE DATABASE falcon_analytics_test"
```

PHP 8.5 ou plus · c'est le plancher de toute la suite. Node 22, sa version exacte
dans `.nvmrc`. Et **MySQL**, qui n'est pas négociable · voir plus bas.

`.env.test` **n'est pas facultatif** · sans lui la suite ne démarre pas du tout,
et c'est voulu · elle ne se rabat jamais en silence sur un autre moteur.

> **Deux réglages du manifeste n'existent que pour l'atelier**, et ils sortent le
> jour où le kit reçoit sa première étiquette ·
>
> - un dépôt de type `path` vers `../falcon-ui-kit`, avec `symlink` · il rend une
>   modification du kit vivante ici, sans rien republier ;
> - `"minimum-stability": "dev"`, sans lequel composer refuserait `dev-main`, le
>   kit n'ayant pas encore de version publiée.
>
> **Un clone isolé fonctionne quand même** · le dépôt GitHub du kit est déclaré
> juste après le premier, et composer y retombe quand le dossier voisin manque.
>
> Laissés dans un paquet étiqueté, ces deux réglages feraient chercher à
> l'intégrateur une version qui n'existe que sur le poste de l'auteur.

---

## La chaîne de contrôle

**Elle se lance depuis le dossier du paquet, avec son propre `vendor/`.** Jamais
depuis l'application qui l'installe · celle-ci porte sa propre version des
dépendances, et un outil qui juge un code sur les mauvaises dépendances rend des
verdicts faux dans les deux sens.

```bash
composer qa            # pint --test, phpstan niveau 8, paratest
npm run lint           # eslint sur les sources, pas sur la compilation
npm run check-assets   # la compilation rend-elle deux fois le même fichier ?
```

**Et `composer qa:lowest`**, qui résout les versions les plus basses que le
paquet annonce, puis relance les essais. Une contrainte annoncée qu'on n'a jamais
installée est une promesse invérifiée.

> **Rien ne s'abaisse pour faire passer la chaîne.** L'analyse est au niveau 8,
> avec les règles strictes, et **il n'y a pas de ligne de base**. Les trois
> exceptions qui subsistent sont écrites dans `phpstan.neon`, chacune avec sa
> raison et sa condition de retrait · deux sont limitées aux essais et naissent
> de deux outils qui ont chacun raison ; la troisième est la règle qui interdit
> d'appeler une méthode statique sur une instance, ce qu'Eloquent fait par
> construction — 153 signalements, pas un seul défaut.
>
> `reportUnmatchedIgnoredErrors` reste actif · le jour où l'une cesse de
> s'apparier, l'analyse le dit plutôt que de la garder par inertie.

### Ce qui manque, et qui est dit plutôt que caché

**Il n'y a pas d'essais du script.** Le collecteur fait 308 lignes qui tournent
sur chaque page de chaque visiteur de chaque hôte — le fichier le plus exécuté
que la suite livre — et rien ne l'éprouve. Le linter, arrivé le 2026-09-13, est
tout ce qui le lit mécaniquement.

Le kit a le sien, sous jsdom, et la même chose est faisable ici · c'est écrit
pour qu'on ne prenne pas l'absence pour un choix.

---

## La base d'essai

**MySQL, et seulement MySQL.** Pas de SQLite, même en mémoire, même « juste pour
aller plus vite » · c'est une décision, pas une négligence.

La raison tient au paquet · **ses écrans sont des agrégations**. `GROUP BY`,
fonctions de date, index composites — c'est exactement là que les deux moteurs
cessent d'être d'accord. Une suite tenue en vingt secondes sous SQLite ne
prouverait rien de ce que le schéma promet.

**Le nom de la base doit finir par `_test`, et le code refuse tout le reste.**
C'est tout le filet de sécurité, et il est mécaniquement bête à dessein · la
suite migre à neuf, donc elle **supprime chaque table de la base qu'on lui
désigne**. Un mauvais nom ne fait pas échouer un essai · il détruit un site qui
marchait.

| Variable | Défaut |
|---|---|
| `ANALYTICS_TEST_MYSQL_DATABASE` | **aucun** · la suite ne devine jamais une base |
| `ANALYTICS_TEST_MYSQL_HOST` | `127.0.0.1` |
| `ANALYTICS_TEST_MYSQL_PORT` | `3306` |
| `ANALYTICS_TEST_MYSQL_USERNAME` | `root` |
| `ANALYTICS_TEST_MYSQL_PASSWORD` | vide |

La connexion est créée **en InnoDB, dit à voix haute** · une installation locale
réglée sur MyISAM refuse les index composites que ce paquet pose sur ses
événements, et l'erreur sort au milieu d'une migration.

### Les données d'essai

Trois gardes, trois modèles, dans `tests/Fixtures/Models/` · `TestAdmin`
(exclu du suivi), `TestClient` et `TestLessor` (sujets suivis). Ils donnent
les trois cas que le bloc `identity` distingue, et un écran s'ouvre en une
ligne ·

```php
$this->actingAs(TestAdmin::create([]), 'admin');
$this->get(route('analytics.admin.overview'))->assertSuccessful();
```

---

## Compiler

```bash
npm run build
```

**Deux producteurs, un seul dossier de sortie, et l'ordre compte.**

```
vite build                          ← le collecteur · c'est LUI qui vide le dossier
tailwindcss -i analytics.css        ← la feuille des écrans
node scripts/fingerprint.mjs        ← l'empreinte des sources
```

**Une seule étape a le droit de vider `public/`, et c'est celle qui passe en
premier.** Inverser l'ordre efface ce qui vient d'être produit, et rien ne le
signale — le fichier manque, simplement.

### Le nom de la source et le nom du produit diffèrent, exprès

`resources/js/collector.js` devient `public/analytics.js`. La source est nommée
pour **ce qu'elle contient**, à qui l'ouvre ; le produit pour **le paquet qui le
livre**, à qui le publie. Renommer l'un ou l'autre perdrait une des deux
lectures.

C'est aussi la ligne où revenir le jour où l'on cherche qui écrit
`public/analytics.js` · elle est dans `vite.config.js`.

### Une fonction immédiatement appelée, jamais un module

Le kit émet une balise **classique** pour le fichier d'un paquet ·
`<script src … defer>`. Un module chargé ainsi est traité comme un script
ordinaire, et ses déclarations d'export lèveraient dans le navigateur. Le format
`iife` n'est donc pas une préférence.

### Les deux contrôles, et ce que chacun dit

| Contrôle | Ce qu'il attrape |
|---|---|
| **l'empreinte des sources** (`sources.sha`, relue par `AssetsAreUpToDateTest`) | on a modifié une vue, une feuille ou le script, et oublié de recompiler |
| **`npm run check-assets`** | la compilation ne rend pas deux fois le même fichier · une date glissée dedans, un ordre qui dépend du disque, une version d'outil différente |

Ce sont deux défauts distincts, et **aucun des deux ne se voit à l'œil**.

**L'empreinte compte les vues**, et pas seulement le script et les feuilles · le
générateur d'utilitaires les lit, donc une classe ajoutée à un écran change la
feuille livrée aussi sûrement qu'une règle écrite à la main.

Les fichiers compilés sont **commités**. C'est ce que le paquet livre · un hôte
n'a pas de Node.

---

## L'organisation du domaine

**Seize classes sont publiques** · toutes les autres portent `@internal`, et
`TheSurfaceIsDeclaredTest` refuse que cette frontière bouge sans qu'on le dise.
La liste tient dans ce fichier d'essai, qui est l'endroit où la lire.

### Le chemin de lecture · ce qui dessine un écran

```
Http/Controllers/Dashboard/…Controller   une ligne · il rend la vue
        ↓
Livewire/Admin/…Page                     l'écran, et ses filtres
        ↓
Livewire/Admin/Widgets/…                 un bloc qui se charge pour son compte
        ↓
Repositories/Dashboard/…ReadRepository   la requête, et rien qu'elle
        ↓
Services/Dashboard/…Calculator|Builder   le calcul, sur des données déjà lues
        ↓
DTOs/Dashboard/…                         ce qui arrive à la vue
```

**Les requêtes sont d'un côté, les calculs de l'autre**, et c'est ce qui rend le
tout éprouvable · un calculateur se teste sans base, un dépôt se teste sans
écran.

**Une page de détail remet une fiche à sa vue, jamais un modèle.** Elle ne
garde que le numéro de ce qu'elle montre, verrouillé, et le relit une fois par
requête · la vue ne peut donc rien lire que la page n'ait prévu.
`TheDetailScreensHandOverValuesTest` le tient, pour tous les composants.

### Le chemin d'un formulaire · ce qui écrit depuis un écran

```
Livewire/Admin/…Form     un composant à lui, posé une fois sur son écran
        ↓
Actions/Save…Action      l'écriture, dans sa transaction
        ↓
an-…-changed             l'annonce · l'écran qui l'écoute se relit
```

**Ouvrir un formulaire, y ajouter une ligne ou le voir refusé ne redessine que
lui**, quelle que soit la longueur de la liste à côté ·
`TheListsDrawOnlyWhatChangesTest` le tient. L'écran l'appelle par sa référence,
`$wire.$refs.…Form.$wire.edit…()`, et le formulaire ne s'ouvre que si la lecture
a réussi.

### Le chemin d'écriture · ce qui enregistre une visite

```
resources/js/collector.js        dans le navigateur, en ES5
        ↓
Http/Middleware/EnsureAnalyticsAccepts    l'interrupteur, la même origine, les exclusions
        ↓
Http/Controllers/IngestController         + Http/Requests · la validation
        ↓
Actions/IngestEventsAction                le seul endroit qui décide
        ↓
Repositories/…WriteRepository             visiteur, session, événement
```

### La conservation · ce qui s'efface, et ce qui le rend indolore

Le troisième chemin, et le moins évident. **Le volume et la valeur ne sont pas
au même endroit** · les pages vues et les clics anonymes sont la quasi-totalité
des lignes et ne servent qu'à deux blocs de la vue d'ensemble et au pas à pas
d'une session ; les événements **nommés** sont rares et portent l'écran des
événements, les tunnels et les conversions marketing.

```
DailyCountArchiver         résume un jour clos · avance depuis le dernier traité
        ↓
ArchiveClosedDaysAction    les jours en attente, une transaction par jour
Maintenance                l'effacement, et le refus d'un jour non résumé
        ↓                              ↑
ArchiveCommand · PruneCommand    CatchesUpTheMaintenance (au chargement d'un écran)
```

Cinq règles tiennent l'ensemble, et chacune a son essai ·

| | |
|---|---|
| **le résumé dit la même chose que le brut** | la lecture brute d'un jour est prise, puis le jour est résumé, et les deux sont comparés · `TheSummaryAgreesWithTheDetail` |
| **l'effacement refuse un jour non résumé** | ce qui rend une panne d'ordonnanceur inoffensive · pas de résumé, pas d'effacement |
| **la lecture coupe sur le dernier jour résumé**, que l'archivage enregistre, jamais déduit de la conservation | l'archivage avance sans laisser de trou, donc tout jour jusqu'à celui-là a son résumé · une ligne au mauvais endroit doublerait un chiffre ou en perdrait un |
| **un jour résumé se lit dans son résumé, et nulle part ailleurs** | les lignes ne sont lues que pour la journée en cours, donc une période de 90 jours coûte un jour de trafic et non 90 · et un effacement interrompu au milieu ne coûte rien, le résumé tenant la journée entière |
| **effacer ne change aucun chiffre** | `ThePurgeChangesNoFigure` mesure sept lectures, efface, remesure, et nomme le bloc qui a bougé — y compris sur un effacement interrompu |

> **Le piège de cette partie** · un jour résumé garde ses lignes — toutes tant
> qu'il n'est pas vidé, les **nommées** ensuite — que le résumé a comptées
> aussi. Lire les deux compterait deux fois, d'où une coupure stricte plutôt
> qu'un recouvrement.

### Le reste

| Dossier | Ce qu'on y trouve |
|---|---|
| `Models/` | les dix tables, et leurs relations · huit portent vos mesures, deux la mécanique de la conservation |
| `Enums/` | les types d'événement et d'objectif |
| `Funnels/` | les tunnels · déclarés par l'hôte, évalués ici |
| `Events/` | les événements nommés, déclarés par l'hôte |
| `Services/SearchConsole/` | l'authentification OAuth, le client, la synchronisation |
| `Support/` | ce qui ne tient à aucune couche · géolocalisation, agent utilisateur, expurgation d'URL, palette |
| `Console/` | les dix commandes |
| `View/` | ce que la directive du collecteur a besoin de savoir |

---

## Les essais

**Ils ne testent pas des classes, ils testent des garanties.** Les noms de
fichiers le disent · `TheRouteNamesDoNotMove`, `TheErasureKeepsWhatItCannotDelete`,
`TheKitKnowsWhoItDrawsFor`, `TheConsentCookieIsReadable`.

```bash
vendor/bin/phpunit --filter TheSurfaceIsDeclared
```

> **Un vert de premier coup ne prouve rien tout seul.** Chaque garantie portante
> a été **montrée capable d'échouer** — en cassant volontairement le mécanisme
> qu'elle surveille — avant d'être gardée. C'est ce qui distingue un essai d'une
> décoration.

La façon de faire · on modifie le code pour retirer la garantie, on vérifie que
l'essai tombe **avec le bon message**, et on remet. Si l'essai passe encore,
c'est lui qu'il faut corriger.

**Ce n'est pas une précaution théorique.** Le 2026-09-13, trois essais de
`falcon/ui-kit` fraîchement écrits sont passés au vert **sans rien surveiller du
tout** · l'un comparait deux valeurs qui s'accordaient par construction, les deux
autres éprouvaient une page où le mécanisme en cause n'était même pas ouvert.
Retirer la garantie ne les faisait pas tomber. Seul le sabotage l'a dit.

### Le banc publie avant de commencer

Chaque essai compare les fichiers livrés à ceux que l'application d'essai sert,
et republie dès qu'un octet diffère. C'est ce que fait aussi le garde du kit, et
c'est pour la même raison.

**« Absent » ne suffisait pas, et ça a coûté une après-midi** · tant que la
condition était « le fichier n'est pas là », un `npm run build` laissait le banc
avec la feuille précédente et seize essais tombaient sur le garde du kit, au
milieu d'essais qui parlaient d'autre chose. **Chaque fichier que l'un ou l'autre
paquet livre est dans la liste**, ou rien ne marche.

### Chaque processus a son propre cache de démarrage

**L'échec intermittent de la suite en parallèle, pris sur le fait le
2026-09-14.** Les quatre processus de paratest démarrent la même application
d'essai, dont les manifestes `bootstrap/cache/services.php` et `packages.php`
sont un seul fichier chacun. Quand un manifeste est périmé — un
`vendor/bin/testbench` l'a écrit avec d'autres fournisseurs, un `composer update`
a changé la liste — chaque processus le réécrit au démarrage, par un fichier
temporaire et un `rename()`. **Sous Windows, renommer par-dessus un fichier
qu'un autre processus lit est refusé** · un essai tombait dans son `setUp` avec
« Accès refusé (code: 5) », sur rien de ce qu'il éprouvait. Une fois tous les
processus d'accord avec le fichier, dix passages verts suivaient, ce qui lui
donnait l'air du hasard.

Le cadre laisse nommer ces deux chemins par l'environnement
(`APP_SERVICES_CACHE`, `APP_PACKAGES_CACHE`), et paratest nomme ses processus
dans `TEST_TOKEN` · le banc donne donc à chacun sa paire, avant de démarrer.
`EachWorkerKeepsItsOwnBootstrapCache` le tient.

---

## Ce qu'on ne teste pas automatiquement

**La documentation.** Elle reste à la relecture humaine. En revanche, **chaque
exemple qu'elle contient est exécuté avant d'être publié** · un exemple qui ne
fonctionne pas est pire que pas d'exemple, parce qu'il fait perdre une heure
avant qu'on soupçonne le paquet plutôt que soi-même.

**Ce que seul un navigateur dit** · la cascade, l'ordre des couches, le moment où
le collecteur arrive dans la page, et le responsive.

---

## Avant de publier une version

- [ ] **les deux réglages d'atelier retirés du manifeste**, et en premier · le
      dépôt `path` vers le kit et `"minimum-stability": "dev"` · les retirer
      change la résolution, donc tout ce qui suit s'éprouve après ;
- [ ] `composer qa`, `composer qa:lowest`, `npm run lint`, `npm run check-assets` ;
- [ ] `npm run build` et les fichiers compilés commités ;
- [ ] le journal des versions à jour, **avec les actions de l'intégrateur** ;
- [ ] chaque réglage public décrit dans [configuration.md](configuration.md), et
      son défaut conforme au fichier ;
- [ ] chaque nom de route comparé à `route:list`, pas à la documentation ;
- [ ] chaque exemple de la documentation exécuté ;
- [ ] **l'épreuve** · installer sur une application Laravel neuve en ne suivant
      que [installation.md](installation.md), et ouvrir un écran.

---

## Faire tourner le paquet dans une vraie application

```bash
vendor/bin/testbench schedule:list
vendor/bin/testbench analytics:archive
```

La suite d'essais force InnoDB, une base jetable et une horloge figée.
**Certaines choses ne se voient que sans ce confort** · une migration refusée
par le moteur par défaut de la machine, une commande qui suppose une
configuration que seul le banc fournit, le planificateur tel qu'un intégrateur
le voit.

Le 2026-09-13, c'est comme ça qu'on a découvert qu'un index unique de cinq
colonnes dépassait la limite de MyISAM · la suite d'essais ne pouvait pas le
voir, puisqu'elle demande InnoDB.

La base vient de l'environnement · exportez `DB_*` avant d'appeler, ou laissez
SQLite en mémoire pour ce qui n'a pas besoin de persister.

> **Attention · ça dépose un fichier d'environnement dans le squelette du banc**,
> et ce fichier s'applique ensuite à toute la suite. Voir le troisième piège
> ci-dessous.

---

## Quatre pièges d'outillage à connaître

**Après avoir changé la forme d'appel d'un composant, videz les vues
compilées.** Elles pointent sur l'ancien nom, et les essais rapportent un
résultat qui n'a plus de rapport avec le code.

```bash
rm -rf vendor/orchestra/testbench-core/laravel/storage/framework/views/*
```

**`eslint --fix` casserait le collecteur.** Il est écrit en ES5 à dessein, rien
ne le transpile, et il emploie quatre fois `x != null` — le test délibéré de
« ni `null` ni `undefined` ». Réécrit en `!==`, il laisserait passer `undefined`.
La configuration exempte ce fichier de deux règles, avec la raison écrite ·
**c'est le bloc à changer le jour où l'on décide quels navigateurs ce collecteur
doit atteindre**, et non avant.

**`vendor/bin/testbench` dépose un `.env` dans le squelette du banc**, et ce
fichier a cassé la suite pendant des semaines. Il porte `SESSION_DRIVER=cookie`
là où la configuration du banc vaut `array`, et **il s'applique à tous les
essais** · `withSession()` hors d'une requête ne peut alors plus écrire, et des
essais qui n'ont rien à voir tombent sur *« Attempt to read property "cookies"
on null »*.

Il est sous `vendor/`, donc **un `git stash` ne l'emporte pas** · c'est ce qui
fait croire, de façon très convaincante, à un défaut du code. Une après-midi
perdue le 2026-09-13, et le candidat le plus sérieux à l'échec intermittent que
cette suite montrait depuis des semaines.

**Il est désormais sans effet, et vous n'avez rien à faire.** `phpunit.xml`
épingle le pilote de session, le magasin de cache et la file d'attente ·
**ce qui y est déclaré est posé avant que l'application démarre**, et le
chargeur du cadre ne remplace jamais une variable déjà posée. Le fichier arrive
donc trop tard. Mesuré le 2026-09-14 · avec lui en place, la suite entière
passe.

`TheBenchRunsOnThePinnedEnvironment` garde cet épinglage — et il tombe le jour
où la ligne disparaît de `phpunit.xml`, c'est-à-dire au moment où la protection
part, pas des semaines plus tard sur un essai qui parle d'autre chose.

> **Un essai précédent, `TheBenchIsNotPolluted`, interdisait ce fichier.** Il
> détectait la cause sans rien réparer, et laissait la suite rouge après chaque
> `vendor/bin/testbench` — une pratique qu'on recommande par ailleurs. Retiré le
> 2026-09-14, remplacé par le garde ci-dessus · **un essai surveille la
> garantie, pas une des façons de la casser.**

**Le paquet n'offre aucune de ses vues à la publication**, et
`NoViewIsOfferedForPublicationTest` le tient · ses écrans lui appartiennent, et
une copie chez un hôte cesserait d'être mise à jour sans un mot. L'hôte les
habille tous par son gabarit.
