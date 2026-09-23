# Ce que Falcon Analytics fait, écran par écran

Quatorze écrans, douze commandes, un point de collecte. Cette page dit ce que
chacun fait, ce qu'il attend et ce qu'il rend.

Pour les réglages, une seule autorité · [configuration.md](configuration.md).

---

## Les noms de routes ne bougent jamais

**C'est le contrat.** Un hôte les écrit dans son menu et dans ses redirections ;
un nom qui suivrait la configuration ne pourrait être écrit nulle part.

Les **adresses**, elles, se changent · `admin.route_prefix` et
`admin.marketing.route_prefix`. Le nom reste.

```blade
<a href="{{ route('analytics.admin.overview') }}">Vue d'ensemble</a>
<a href="{{ route('analytics.admin.realtime') }}">Temps réel</a>
<a href="{{ route('analytics.admin.visitors') }}">Visiteurs</a>
<a href="{{ route('analytics.admin.sessions') }}">Sessions</a>
<a href="{{ route('analytics.admin.events') }}">Événements</a>
<a href="{{ route('analytics.admin.funnels') }}">Tunnels</a>
<a href="{{ route('analytics.admin.marketing.dashboard') }}">Marketing</a>
```

> **`analytics.admin.` puis le nom de l'écran**, et `analytics.admin.marketing.`
> pour les écrans marketing. Le mot `admin` est l'espace · il est dans le nom
> même quand vous changez l'adresse.

---

## Les filtres sont dans l'adresse, et vous pouvez les écrire

**C'est ce qui permet de lier vers une vue déjà filtrée** depuis votre propre
interface · « voir les sessions mobiles des 7 derniers jours » est un lien, pas
un parcours de clics.

Ce sont des paramètres de requête ordinaires, et ils s'ajoutent à l'adresse que
`route()` vous rend ·

```blade
<a href="{{ route('analytics.admin.sessions') }}?period=7&device=mobile">
    Sessions mobiles de la semaine
</a>
```

| Paramètre | Où | Valeurs | Défaut |
|---|---|---|---|
| `period` | les huit écrans à période | **`7`, `30` ou `90`** · toute autre valeur retombe sur le défaut, sans erreur | `30` |
| `subject` | les mêmes | un de vos gardes suivis · **vide veut dire tous**, visiteurs anonymes compris | vide |
| `search` | sessions, visiteurs | texte libre | vide |
| `device` | sessions | `desktop`, `mobile`, `tablet` · vide veut dire tous | vide |
| `source` | sessions | une source d'acquisition · vide veut dire toutes | vide |
| `sort` | sessions, visiteurs | une colonne triable de la liste | `started_at` · `last_seen_at` |
| `direction` | sessions, visiteurs | `asc` ou `desc` | `desc` |

**Les huit écrans à période** · vue d'ensemble, événements, tunnels, sessions,
visiteurs, synthèse marketing, détail d'une campagne, détail d'une publicité.

> **Une période de 30 jours, c'est aujourd'hui et les 29 jours qui précèdent**,
> aujourd'hui étant compté jusqu'à l'heure où vous lisez. Les flèches de
> variation comparent à **la période précédente** · les 30 jours entiers juste
> avant, sans trou entre les deux. Elle est faite de journées entières parce que
> c'est ce que tout outil de ce genre compare, et parce qu'une fenêtre qui
> s'arrêterait en milieu de journée ne pourrait pas se lire dans les compteurs
> quotidiens une fois son détail effacé.

**Les six qui n'en ont pas** · temps réel, qui lit sa propre fenêtre récente ;
intégrations ; campagnes et publicités, qui listent des définitions et non du
trafic ; et les deux détails, visiteur et session, qui montrent tout ce qu'ils
ont.

> **Ce ne sont pas des noms de routes**, et ils n'ont pas la même garantie ·
> voir [ce qui compte comme rupture](mise-a-jour.md#ce-qui-compte-comme-rupture).

---

## Ce que toutes les fiches partagent

On ne le répète donc pas, et chaque exception est dite dans sa fiche ·

| | |
|---|---|
| **méthode** | `GET` |
| **permissions** | ce que `admin.middleware` contient · `['web', 'auth']` au défaut |
| **layout** | ce que `layouts.admin` nomme · la coquille du paquet au défaut |
| **mode d'usage** | une page, dans votre layout ou dans notre coquille. **Jamais un composant à poser dans une de vos pages** · un écran attend une page entière autour de lui |
| **lecture seule** | sauf **trois** écrans marketing et l'effacement RGPD, tous signalés dans leur fiche |
| **paramètres** | **ceux de l'adresse**, quand l'écran en porte un · les filtres, eux, sont dans la section ci-dessus et ne sont pas répétés fiche par fiche |

Les écrans marketing prennent `admin.marketing.middleware`, qui peut désigner un
autre garde.

---

## Un écran qui n'arrive pas à lire ne tombe pas

Les écrans se composent de blocs qui **chargent chacun pour leur compte** ·
l'en-tête et les filtres paraissent tout de suite, le reste arrive quand il est
prêt. Une lecture lente n'en retient donc pas une autre.

Et **si une lecture échoue**, le bloc concerné affiche un état d'erreur compact
à sa place · le reste de l'écran continue de fonctionner, votre habillage reste
debout, et il n'y a pas d'erreur 500.

> **Ce n'est pas la panne qui est cachée, c'est la page qui est sauvée.** La
> cause part dans le canal de journal du paquet, `log_channel` · c'est là qu'on
> regarde, et c'est la raison de lui donner un canal à lui.

---

## Les écrans d'analytique

### Vue d'ensemble

| | |
|---|---|
| **route** | `analytics.admin.overview` |
| **adresse** | `{admin.route_prefix}` · `/admin/analytics` |
| **paramètres** | aucun dans l'adresse · période et sujet par la barre de filtres |

L'en-tête et les filtres paraissent tout de suite ; les indicateurs de tête avec
leurs courbes, puis l'audience, l'acquisition, le contenu et le résumé des
événements arrivent chacun quand il est prêt. **Une page lente ne retient pas la
suivante.**

> **« Les pages les plus vues » compte des pages, pas des adresses.** Une page,
> c'est le chemin de sa route — sans hôte, sans paramètres, sans ancre · donc
> `/tarifs`, `/tarifs?fbclid=…`, `/tarifs#prix` et `www.exemple.test/tarifs` sont
> la même ligne. Sans cela une page d'atterrissage de campagne se diviserait en
> autant de lignes qu'elle a eu de visites — `fbclid` est unique à chaque clic —
> et n'atteindrait jamais le haut de la liste. Le temps réel compte de la même
> façon.
>
> **L'adresse reste stockée entière**, elle · le parcours d'une session montre
> le lien exact qui a été ouvert, et l'attribution des campagnes se calcule sur
> ces paramètres.

### Temps réel

| | |
|---|---|
| **route** | `analytics.admin.realtime` |
| **adresse** | `{admin.route_prefix}/realtime` |
| **paramètres** | aucun |

Qui est en ligne maintenant, et l'activité de la fenêtre récente · indicateurs,
graphique par minute, répartitions par appareil et par source, fil d'activité,
pages consultées, et une **carte du monde en SVG livré avec le paquet** —
aucun serveur de tuiles, aucune bibliothèque cartographique, aucune requête
sortante. Son tracé, `world-map.svg`, est publié avec les autres fichiers
compilés et gardé par le navigateur · un rafraîchissement ne renvoie que les
points.

Elle se rafraîchit par sondage ordinaire de Livewire, **suspendu tant que
l'onglet est caché, et tant que l'écran est sorti du champ de vision**. Aucun
service permanent n'est requis · voir `realtime.*` dans la configuration.

> **Un onglet en arrière-plan ne demande rien**, ce qui est le comportement
> voulu · un écran temps réel laissé ouvert une nuit ne doit pas interroger le
> serveur huit mille fois pour personne.

La carte a besoin de la [géolocalisation](#la-géolocalisation) pour placer ses
points ; sans base, les sessions comptent comme non localisées.

### Visiteurs

| | |
|---|---|
| **route** | `analytics.admin.visitors` |
| **adresse** | `{admin.route_prefix}/visitors` |
| **paramètres** | aucun |

Les indicateurs de la période en tête, puis **la liste de tous les profils depuis
toujours** — chaque ligne résumant les sessions, la première et la dernière
visite, la localité et la source d'acquisition. Le filtre de période ne réduit
volontairement pas la liste · un annuaire tronqué par une période n'est plus un
annuaire.

### Détail d'un visiteur

| | |
|---|---|
| **route** | `analytics.admin.visitors.show` |
| **adresse** | `{admin.route_prefix}/visitors/{visitor}` |
| **paramètres** | `visitor` · l'identifiant du profil |

Son identité, ses chiffres, et la liste de ses sessions menant chacune à son
détail. **Lecture seule, sauf l'effacement RGPD** · voir
[la vie privée](#la-vie-privée-et-le-rgpd).

### Sessions

| | |
|---|---|
| **route** | `analytics.admin.sessions` |
| **adresse** | `{admin.route_prefix}/sessions` |
| **paramètres** | aucun |

Les statistiques d'engagement de la période, et la liste paginée et filtrable de
**toutes les sessions non robotisées**, chacune menant à son parcours.

### Détail d'une session

| | |
|---|---|
| **route** | `analytics.admin.sessions.show` |
| **adresse** | `{admin.route_prefix}/sessions/{session}` |
| **paramètres** | `session` · l'identifiant de la session |

Ses informations principales et **son parcours chronologique** · les pages
visitées, les clics rangés sous la page où ils ont eu lieu, et le temps passé.

> **Au-delà de la conservation, le pas à pas n'est plus là, et l'écran le dit.**
> Il affiche alors ce que la session sait encore · ses pages vues, ses clics,
> ses conversions, et la liste de ses événements nommés, qui ne sont jamais
> effacés. **C'est le seul écran que la conservation change** · voir
> [`retention_days`](configuration.md#la-durée-de-vie-des-données).

### Événements

| | |
|---|---|
| **route** | `analytics.admin.events` |
| **adresse** | `{admin.route_prefix}/events` |
| **paramètres** | aucun |

Les volumes, les scores et les conversions de vos [événements
nommés](#les-événements-nommés-et-les-conversions) · les chiffres de tête avec
leurs courbes, la tendance, et le détail par événement.

### Tunnels

| | |
|---|---|
| **route** | `analytics.admin.funnels` |
| **adresse** | `{admin.route_prefix}/funnels` |
| **paramètres** | aucun |

Pour chaque [tunnel déclaré](#les-tunnels) · les volumes par étape, les taux de
conversion entre étapes, et le score de chaque étape et du tunnel sur la période.

**Sans fichier de tunnels, l'écran est vide et le dit** · ce n'est pas une
panne.

### Intégrations

| | |
|---|---|
| **route** | `analytics.admin.integrations` |
| **adresse** | `{admin.route_prefix}/integrations` |
| **paramètres** | aucun |

Aujourd'hui, la carte Google Search Console · connecter, choisir la propriété à
rattacher, déconnecter. **Tant que l'hôte n'a pas fourni d'identifiants OAuth,
la carte dit ce qui manque** et la connexion refuse.

---

## Les écrans marketing

Ils prennent `admin.marketing.route_prefix` et `admin.marketing.middleware` · un
hôte peut les monter ailleurs que les écrans d'analytique, derrière un autre
garde, **ou ne pas les proposer du tout**.

> **Ne pas les proposer, c'est simplement ne pas mettre les liens.** Les routes
> existent de toute façon — c'est ce qui permet d'ouvrir le marketing plus tard,
> ou à quelques personnes seulement, sans rien changer au paquet. Elles restent
> protégées par `admin.marketing.middleware` comme les autres.

### Synthèse marketing

| | |
|---|---|
| **route** | `analytics.admin.marketing.dashboard` |
| **adresse** | `{admin.marketing.route_prefix}` · `/admin/marketing` |
| **paramètres** | aucun |

La performance des campagnes et des publicités sur la période · portée,
conversions et taux de conversion. **Les dépenses ne sont pas suivies** · le
paquet n'appelle aucune régie.

### Campagnes · **écriture**

| | |
|---|---|
| **route** | `analytics.admin.marketing.campaigns` |
| **adresse** | `{admin.marketing.route_prefix}/campaigns` |
| **paramètres** | aucun |

La table cherchable et paginée des campagnes, avec leurs conditions d'URL et leur
nombre de publicités. **Les campagnes se créent et se modifient ici.**

### Détail d'une campagne · **écriture**

| | |
|---|---|
| **route** | `analytics.admin.marketing.campaigns.show` |
| **adresse** | `{admin.marketing.route_prefix}/campaigns/{campaign}` |
| **paramètres** | `campaign` |

Son trafic sur la période, son identité, ses conditions d'URL, et la table de ses
publicités avec leur trafic et leurs objectifs de conversion. **Les publicités et
les objectifs se gèrent ici**, et la campagne peut être modifiée ou supprimée.

### Publicités

| | |
|---|---|
| **route** | `analytics.admin.marketing.ads` |
| **adresse** | `{admin.marketing.route_prefix}/ads` |
| **paramètres** | aucun |

La table à plat de toutes les publicités, toutes campagnes confondues. **Leur
modification se fait sur la campagne parente.**

### Détail d'une publicité · **écriture**

| | |
|---|---|
| **route** | `analytics.admin.marketing.ads.show` |
| **adresse** | `{admin.marketing.route_prefix}/ads/{ad}` |
| **paramètres** | `ad` |

Sa campagne parente, ses conditions d'URL et son éditeur paraissent tout de
suite ; son trafic, sa tendance et le détail de ses conversions suivent.

---

## Ce qui n'a pas d'écran

### Les deux étapes OAuth de Search Console

| Route | Adresse | Ce qu'elle fait |
|---|---|---|
| `analytics.admin.integrations.search-console.connect` | `{admin.route_prefix}/integrations/search-console/connect` | Part vers Google. **Refuse si les identifiants ne sont pas configurés.** |
| `analytics.admin.integrations.search-console.callback` | `{admin.route_prefix}/integrations/search-console/callback` | Le retour de Google. **C'est cette adresse qu'il faut déclarer sur le client OAuth**, sauf si vous en imposez une autre par `search_console.redirect`. |

Les deux sont derrière les mêmes protections que les écrans.

### Le point de collecte

| | |
|---|---|
| **route** | `analytics.web.ingest` |
| **méthode** | `POST` |
| **adresse** | `{endpoint}` · `/__analytics` |
| **permissions** | `web.middleware`, puis le contrôle d'origine et la limite de débit, qu'on **ne peut pas retirer** |

C'est là que le collecteur envoie ses lots. **Aucun jeton CSRF**, et il ne peut
pas y en avoir · une balise d'envoi n'en porte pas. Le contrôle d'origine et la
limite de débit tiennent sa place, et ils sont posés **après** votre pile · vider
`web.middleware` vous prive de la session, pas d'eux.

**La limite se règle**, elle · `throttle`, 120 requêtes par minute au défaut. Une
page publique très fréquentée veut la monter. Le contrôle d'origine, lui, n'a
aucun réglage.

### Les douze commandes

| Commande | Ce qu'elle fait | Quand |
|---|---|---|
| `analytics:install` | publie la configuration, ajoute les variables d'environnement, lance les migrations | à l'installation |
| `analytics:seed` | remplit les écrans de visites inventées · refuse hors développement sans `--force` | en développement, pour éprouver les écrans · voir plus bas |
| `analytics:refresh` | supprime les tables de l'analytique et rejoue ses migrations · les statistiques repartent de zéro | une fois, pour un projet installé avant la reprise du schéma · voir [reprise-du-schema.md](reprise-du-schema.md) |
| `analytics:check` | dit si le paquet est correctement installé et opérationnel | après l'installation, et quand quelque chose cloche |
| `analytics:sweep` | clôt les sessions inactives au-delà du délai | **planifiée, toutes les 5 minutes** |
| `analytics:archive` | résume les jours clos, pour que l'effacement ne coûte aucun chiffre · `--days` borne un passage | **planifiée, chaque jour à 03:00** |
| `analytics:prune` | efface les pages vues et clics **anonymes** au-delà de la conservation · refuse un jour non résumé | **planifiée, chaque jour à 03:30** |
| `analytics:geoip:download` | télécharge la base MaxMind GeoLite2 City | **planifiée, le 1er de chaque mois à 04:00** · inerte sans clé |
| `analytics:geoip:check` | dit si la base est utilisable, et pourquoi une adresse résout ou non · accepte une adresse en argument | au besoin |
| `analytics:search-console:sync` | tire les requêtes organiques dans le cache local | **planifiée, chaque jour à 05:00** · inerte sans connexion |
| `analytics:events:scan` | compare les événements déclarés à ceux employés dans le code · `--fix` ajoute les manquants | quand vous instrumentez |
| `analytics:events:check` | vérifie que chaque étape de tunnel référence un événement déclaré | quand vous écrivez un tunnel |

### Remplir les écrans pour les éprouver

À l'installation, les quatorze écrans sont vides · les données viennent du
trafic réel, et il en faut des semaines. `analytics:seed` les remplit de visites
inventées, pour juger la lisibilité et la tenue des écrans tout de suite.

```bash
php artisan analytics:seed
php artisan analytics:seed --visits=2000 --days=90
```

| Option | Défaut | Ce qu'elle règle |
|---|---|---|
| `--visits` | 600 | combien de visites ajouter |
| `--days` | 30 | sur combien de jours passés les répartir · jamais plus que la conservation, `retention_days` |
| `--force` | — | exécuter hors développement |

**Elle refuse hors `local` et `testing`**, et nomme l'environnement trouvé · une
commande qui écrit de fausses visites est aussi dangereuse qu'une commande qui
en efface. `--force` lève le refus, si c'est vraiment ce que vous voulez.

**Chaque passage ajoute** · relancée, elle double les visites. Les deux
campagnes de démonstration, elles, ne sont posées qu'une fois.

**Ce qu'elle pose** ·

- **deux campagnes de démonstration** et leurs pubs, reconnues par les
  paramètres `utm_campaign` et `utm_content` de leurs liens, avec pour objectifs
  votre première conversion et votre premier tunnel déclarés ;
- **les visites**, **enregistrées comme celles du collecteur** · même chemin,
  l'horloge réglée sur le moment de chaque visite. Elles parcourent **vos**
  pages publiques (les routes nommées, sans paramètre, hors authentification),
  cliquent, déclenchent **vos** événements, avancent dans **vos** tunnels, et
  arrivent en direct, par un moteur, un réseau, un site tiers ou un lien de
  campagne, sur ordinateur, téléphone ou tablette. Certains visiteurs reviennent,
  quelques robots passent, et les dernières visites tombent dans les minutes qui
  précèdent, pour le temps réel ;
- **le lieu de chaque visite** · il vient de votre base de géolocalisation, et à
  défaut d'une courte liste de villes ;
- **puis ce que feraient les tâches planifiées** · les sessions inactives
  closes, et les journées closes résumées — **celles qui l'étaient déjà
  comprises**, puisque la commande y ajoute des visites et que la tâche de nuit,
  elle, ne revient jamais sur une journée résumée.

**Elle refuse un `--days` plus long que la conservation.** Au-delà, le pas à pas
d'une visite est effacé à la purge suivante · les visites posées là
disparaîtraient sans avoir compté dans les classements. Et une journée déjà
purgée ne se résume plus à nouveau, puisque son détail n'est plus complet.

**La chaîne vit dans le paquet**, sous `database/seeders/`, et se joue aussi à
la manière ordinaire · mais le refus hors développement vit sur la commande, et
il n'y est pas ·

```bash
php artisan db:seed --class="Falcon\Analytics\Database\Seeders\DatabaseSeeder"
```

**Le `DatabaseSeeder` de votre application ne déclare pas le nôtre**, et c'est
voulu · un `db:seed` ordinaire poserait six cents visites inventées chaque fois
que vous rejouez autre chose.

### La fusion des identités

**Une personne connue = un profil**, et c'est tenu tout seul, sans écran et sans
réglage. Vous n'avez rien à faire ; c'est écrit ici parce que ça **change les
chiffres que vous lisez**, et qu'une variation qu'on ne sait pas expliquer se
prend pour un défaut.

Quand quelqu'un s'authentifie et qu'un de vos gardes le désigne comme sujet
suivi, le paquet rassemble ses profils · trois cas, et un seul comportement à
retenir ·

| Ce qui se passe | Ce que ça donne |
|---|---|
| il se connecte pour la première fois depuis ce navigateur, et il n'avait aucun profil | le profil de ce navigateur devient le sien |
| il avait déjà un profil ailleurs — un autre navigateur, un autre appareil | les deux se replient en un seul, **le plus ancien survit**, et l'autre devient un alias |
| il se connecte depuis un navigateur qui appartient à quelqu'un d'autre | ses sessions rejoignent **son** profil, et le navigateur garde son propriétaire |

Ce qu'il faut en savoir concrètement ·

- **le profil replié disparaît de l'annuaire**, et ses sessions comme ses
  événements sont désormais lus sous le profil survivant. Votre nombre de
  visiteurs **baisse** ce jour-là, sans que rien ait été perdu ;
- **la première et la dernière visite du survivant s'élargissent** pour couvrir
  les deux · un visiteur « vu pour la première fois » avant sa propre création ;
- **l'alias continue de fonctionner** · les envois suivants de ce navigateur
  arrivent sur le profil survivant, sans nouvelle fusion ;
- **la séparation des appareils survit** · les sessions gardent leur empreinte
  de navigateur, donc « deux appareils » reste lisible sous un seul profil.

> **Rien de tout cela ne concerne les visiteurs anonymes.** Deux navigateurs sans
> personne derrière restent deux visiteurs · le paquet ne rapproche jamais deux
> profils sur autre chose qu'une authentification de votre application.

### La planification, et ce qu'elle exige de vous

**Rien de particulier.** Le paquet inscrit lui-même ses cinq tâches dans
l'ordonnanceur de Laravel · il vous suffit de déclencher `schedule:run` comme
vous le faites déjà, par une tâche système ou par un appel HTTP.

> **Aucune tâche dédiée à analytics n'est à créer**, et aucun processus permanent
> n'est requis. Si votre application n'a pas encore d'ordonnanceur, c'est le seul
> prérequis.

**L'ordre de deux d'entre elles est une garantie, pas un détail** · le résumé à
03:00, l'effacement à 03:30. **L'effacement refuse un jour que le résumé n'a pas
traité**, donc un ordonnanceur qui s'arrête les arrête tous les deux · le retard
attend, et rien n'est perdu.

> **Et si le vôtre s'arrête quand même, ouvrir un écran d'analytique rattrape le
> retard** — après l'envoi de la page, une fois par heure au plus, et par
> tranches. C'est le filet pour les hébergements mutualisés, dont les
> ordonnanceurs s'arrêtent sans un mot. `analytics:check` dit combien de jours
> attendent ; si ce nombre ne baisse pas d'un jour à l'autre, votre
> `schedule:run` ne tourne pas.

---

## Ce que le paquet capture tout seul

Les **pages vues**, à chaque chargement. Les **clics**, mais seulement sur ce qui
est réellement interactif · un clic sur du texte ou sur du vide ne porte aucun
signal et n'est jamais enregistré.

Un élément compte comme interactif quand il est ·

- un contrôle natif · `<a>`, `<button>`, `<summary>`, ou un `<input>` actionnable
  (`submit`, `button`, `reset`, `image`, `checkbox`, `radio`) ;
- un composant ARIA · `role="button | link | menuitem | menuitemcheckbox |
  menuitemradio | tab | option | switch"` ;
- rendu interactif par un gestionnaire · `wire:click`, `@click`, `x-on:click`,
  `onclick`.

**Si un élément n'est interactif que par votre propre code** et ne porte rien de
ce qui précède, déclarez-le avec `data-track-event`. Sur un `<form>`,
`data-track-event` est capturé **à la soumission**, pas au clic.

### Enrichir un clic

| Attribut | Effet |
|---|---|
| `data-track-event="domaine.action"` | nomme l'action · c'est la clé de l'événement déclaré et de la jointure des tunnels |
| `data-track-value="3"` | le score de ce clic, en points entiers · il remplace, pour ce clic, celui de la déclaration. Un nombre décimal est ignoré, et dans un tunnel ce sont les points de l'étape qui comptent |
| `data-track-prop-*="…"` | des propriétés libres · `data-track-prop-listing-id` devient `props.listing_id` |
| `data-track-section="hero"` | une zone logique, appliquée à tout le sous-arbre |
| `data-track-label="…"` | un libellé humain · sinon le texte détecté |
| `data-track-ignore` | exclut l'élément et son sous-arbre |

**Ces attributs se lisent en remontant depuis l'élément cliqué**, et c'est ce
qui les rend praticables · vous taguez le bouton, pas le `<span>` qu'il
contient, et pas chaque cellule d'une ligne.

- pour le **nom**, le **score** et la **zone**, le premier rencontré en
  remontant gagne · le plus proche du clic ;
- les **propriétés** s'accumulent tout le long de la remontée, et là encore la
  plus proche gagne en cas de doublon · une ligne de tableau peut donc porter
  `data-track-prop-listing-id` pour tous les boutons qu'elle contient ;
- `data-track-ignore` **arrête tout** dès qu'il est rencontré · le clic n'est
  pas enregistré du tout.

---

## Les événements nommés et les conversions

Les clics anonymes se capturent seuls ; **les événements nommés sont ceux que
vous déclarez**, et ce sont eux qui alimentent l'écran des événements, les
tunnels et les objectifs marketing.

Déclarez-les dans `app/Analytics/events.php` — chemin réglable par
`events_path` — qui est **la source unique** ·

```php
use Falcon\Analytics\Events\TrackedEvent;

TrackedEvent::define('auth.client.register.submit', 'Inscription client (soumission)', value: 5);
TrackedEvent::define('listing.publish.submit', 'Publication d\'une annonce', value: 15);
TrackedEvent::define('review.submit', 'Avis déposé', conversion: true);
TrackedEvent::define('nav.catalog.click', 'Accès au catalogue');
```

| | |
|---|---|
| `name` | la clé technique, telle qu'employée par `data-track-event` ou `Analytics::record()`. Convention · `domaine.action` |
| `label` | ce que le tableau de bord affiche |
| `value` | le score que rapporte chaque occurrence **par défaut**, en points entiers · jamais un montant. Une occurrence qui porte le sien — `data-track-value`, `Analytics::record(value:)` — compte le sien. Un événement déclaré avec un score est une conversion, sauf `conversion: false` |
| `conversion` | marque l'événement comme une conversion |

> **Un score, pas un montant.** Le tableau de bord additionne des points et les
> affiche en « pts ». Un nombre décimal est refusé dès la déclaration · le
> fichier cesse de se charger à cette ligne, et **l'erreur n'est écrite que dans
> le journal**. Les événements déclarés après elle n'existent plus pour les
> écrans.

**Comment un événement se lit à l'écran** · par son libellé, s'il est déclaré ·
sinon par le texte du lien cliqué · sinon par son nom technique. Le parcours
d'une session, le fil en direct et le classement des clics suivent tous cette
règle.

**Le classement des clics compte une ligne par événement** · deux boutons qui
déclenchent le même événement n'en font qu'une, à son libellé. Pour les
distinguer, déclarez deux événements. Un clic sans événement garde une ligne
par texte.

`php artisan analytics:events:scan` compare cette liste à ce que votre code
emploie réellement, et `--fix` ajoute les manquants · un événement employé mais
non déclaré s'affiche sinon par son nom technique dans le classement des clics.

### Émettre depuis le serveur

Pour les vraies conversions qu'un clic ne peut pas confirmer — une inscription
validée, un paiement abouti ·

```php
use Falcon\Analytics\Facades\Analytics;

Analytics::record('CompleteRegistration', value: 5, props: ['plan' => 'pro']);
```

Même visiteur, même session, même stockage, mêmes tunnels. **L'appel est
différé** — il ne bloque jamais la réponse —, sans effet quand le suivi est
éteint ou que le contexte est exclu, et **il ne lève jamais rien vers
l'appelant**.

> **Il vaut dans la requête du visiteur, et seulement là.** Depuis une tâche
> différée ou une commande, il n'y a pas de visiteur à qui rattacher
> l'événement · rien n'est enregistré, et le journal le dit. Appelez-le dans
> la requête qui déclenche l'action, avant de confier le reste à une tâche.

---

## Les tunnels

Déclarez-les dans `app/Analytics/funnels.php` — chemin réglable par
`funnels_path`. Chaque étape correspond à un événement nommé **ou** à une route
de page vue, jamais aux deux, et rapporte ses propres points ·

```php
use Falcon\Analytics\Funnels\Funnel;

Funnel::define('acquisition_client', 'Acquisition client')
    ->step('Page inscription', value: 1, route: 'client.register')
    ->step('Soumission',       value: 5, event: 'auth.client.register.submit');
```

Le même événement peut appartenir à plusieurs tunnels avec un score différent
dans chacun.

### Les branches parallèles

Un jalon est souvent atteignable de plusieurs façons. La progression étant
séquentielle, poser les alternatives en étapes consécutives se lirait « est passé
par l'une, *puis* par l'autre » et rapporterait des zéros. **Déclarez-les à la
même profondeur** ·

```php
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelBranch;

Funnel::define('acquisition', 'Acquisition')
    ->step('Offre consultée', value: 2, event: 'offer.viewed')
    ->step('Formulaire ouvert', value: 8, anyOf: [
        FunnelBranch::event('Questionnaire', 'quiz.opened'),
        FunnelBranch::route('Contact', 'contact'),
    ])
    ->step('Demande envoyée', value: 100, event: 'lead.created');
```

Un visiteur avance une fois, quelle que soit la branche empruntée, et l'étape
rapporte combien sont passés par chacune — **y compris par celles que personne
n'a prises**, un zéro étant lui aussi une lecture.

Une étape accepte exactement un `event`, un `route` ou un `anyOf`, et `anyOf`
demande au moins deux branches.

---

## Le marketing

Un module distinct, qui mesure la performance publicitaire **sans aucune
interface de régie** · les campagnes et les publicités se définissent depuis les
écrans, sont stockées dans votre base, et sont rapprochées des sessions par les
paramètres d'URL que portent les liens.

- **Les campagnes et les publicités se créent dans l'interface.** Chacune porte
  des conditions libres sur les paramètres d'URL — par exemple
  `utm_source=facebook` et `utm_campaign=summer`.
- **Les objectifs se déclarent par publicité**, en choisissant parmi les
  événements déclarés et les tunnels. Le module rapporte alors la portée, les
  conversions et le taux de conversion, par publicité et par campagne.
- **Rien n'est stocké sur la session au moment de la visite**, que les paramètres
  d'URL eux-mêmes. Le rapprochement se refait **à chaque lecture d'écran**, ce qui
  rend l'attribution **rétroactive** · une campagne définie aujourd'hui retrouve
  les sessions du mois dernier.

### Les trois règles qui décident à qui va une session

Elles ne se devinent pas, et elles expliquent la plupart des « pourquoi ce
chiffre » ·

1. **La définition la plus précise gagne.** Si une publicité demande
   `utm_source=facebook` et une autre `utm_source=facebook` **et**
   `utm_campaign=summer`, une arrivée portant les deux va à la seconde · c'est le
   nombre de conditions qui tranche, et à égalité, la première rencontrée.
2. **Les valeurs sont comparées à l'identique.** Pas de motif, pas de joker, pas
   de casse ignorée · `Facebook` n'est pas `facebook`. Une condition dont le
   paramètre est absent de l'arrivée ne correspond pas.
3. **Une définition sans condition n'attribue jamais rien.** Elle est écartée
   plutôt que de tout prendre — c'est le comportement voulu, mais une publicité
   enregistrée sans condition reste à zéro sans qu'aucun écran ne s'en plaigne.
4. **Seules les définitions actives sont lues.** Une campagne ou une publicité
   désactivée est écartée du calcul.

> **Désactiver n'archive pas, ça efface des rapports.** Le rapprochement se
> refaisant à chaque lecture, une publicité désactivée aujourd'hui **disparaît
> aussi des périodes passées** · ses sessions redeviennent non attribuées, ou
> partent à la définition la plus précise qui reste. C'est le revers exact de la
> rétroactivité, et c'est ce qui la rend utile · pour arrêter une campagne sans
> toucher à son historique, laissez-la active et cessez simplement d'en diffuser
> les liens.

> **Un visiteur peut être crédité à plusieurs publicités.** S'il est arrivé par
> l'une puis par l'autre dans la période lue, sa conversion compte pour les deux ·
> ce n'est **pas** une attribution au premier contact. La somme des conversions
> par publicité peut donc dépasser le total, et c'est cohérent.
>
> Chaque publicité, elle, compte des **visiteurs distincts** · ses conversions ne
> dépassent jamais sa portée.

> **La lecture se fait dans la période choisie.** Une session hors période
> n'attribue rien, quelle que soit son ancienneté.

---

## Google Search Console

Google retire le mot-clé des adresses de provenance · **les requêtes de recherche
naturelle ne s'obtiennent que par son interface de programmation**, autorisée par
un administrateur en lecture seule.

Ce que l'hôte fournit · un client OAuth 2.0 web dans Google Cloud, l'interface
Search Console activée, et l'adresse de rappel déclarée dessus. Les identifiants
vont dans `search_console.*` · voir [configuration.md](configuration.md).

**Le parcours** · sur `analytics.admin.integrations`, « Connecter Google Search
Console » part vers Google, revient sur la route de rappel, puis demande quelle
propriété rattacher. La synchronisation est ensuite quotidienne, et
`analytics:search-console:sync` la déclenche à la main.

**Ce que fait la synchronisation**, et qui surprend si on ne l'a pas lu ·

- **le premier passage remonte seize mois** · c'est tout ce que l'interface de
  Google sert, et le paquet le prend en une fois. Vos courbes de recherche
  naissent donc pleines, avant même la pose du collecteur ;
- **chaque passage relit les trois derniers jours** · Google réécrit ces
  journées à mesure que ses chiffres se consolident, donc un chiffre d'hier
  peut bouger aujourd'hui. Ce n'est pas une erreur de comptage ;
- **un échec marque la connexion**, et l'écran des intégrations le montre · la
  cause part dans le canal de journal du paquet.

**Ces données ne sont pas soumises à la rétention** · elles vivent dans leur
propre table et `analytics:prune` n'y touche pas.

**Tant que les identifiants sont vides, la fonction ne se propose pas** · le lien
disparaît de la barre latérale, l'écran dit ce qui manque si on s'y rend quand
même, et la connexion refuse en y ramenant.

---

## La géolocalisation

Base locale MaxMind GeoLite2 City · **l'adresse d'un visiteur ne quitte jamais
votre serveur**. Aucun appel à un tiers.

1. une clé gratuite sur [maxmind.com](https://www.maxmind.com/en/geolite2/signup) ;
2. `ANALYTICS_GEOIP_LICENSE_KEY` dans votre `.env` ;
3. `php artisan analytics:geoip:download`.

`analytics:geoip:check` dit où vous en êtes, et pourquoi une adresse résout ou
non. La base est ensuite rafraîchie chaque mois toute seule.

**Sans clé, rien ne casse** · les sessions comptent comme non localisées, la
carte du temps réel reste vide, et la commande de téléchargement vous dit quoi
faire.

---

## La vie privée et le RGPD

- **L'adresse IP** est tronquée avant d'être stockée. Le pays et la ville sont
  lus avant, donc les écrans ne perdent que l'adresse exacte.
  `privacy.anonymize_ip` à `false` la garde entière.
- **Les adresses de pages** sont nettoyées de ce qui ressemble à une donnée
  personnelle — jeton, mot de passe, courriel — avant d'être stockées. Le
  paramètre reste, **sa valeur devient `redacted`** · une adresse amputée ne se
  relirait plus. Les paramètres de campagne sont gardés tels quels, et la liste
  est réglable.
- **L'identifiant persistant d'un visiteur** n'existe que si
  `identity.consent_cookie` désigne un cookie et que ce cookie vaut `"1"`. Sinon
  tout reste à la portée de la session. Son cookie, `fa_vid`, dure **treize
  mois au plus**, le plafond que la CNIL fixe pour un cookie de mesure, et une
  visite suivante ne le prolonge pas.
- **Le nom d'un sujet n'est jamais stocké** · il est lu sur votre modèle au
  moment de l'affichage.
- **L'effacement** se fait depuis le détail d'un visiteur · il supprime le
  profil, ses sessions, ses événements et ses alias fusionnés, en une
  transaction. Les deux classements de la vue d'ensemble — pages et clics les
  plus vus — gardent ses visites des journées déjà résumées, en simples comptes
  anonymes · ils lisent ces journées dans les compteurs de la nuit.
