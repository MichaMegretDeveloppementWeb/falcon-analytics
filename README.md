# Falcon Analytics

Une mesure d'audience propriétaire et respectueuse de la vie privée, pour les
espaces d'administration Laravel. Autonome, discrète et portable : on
l'installe, on pose une directive, on règle quelques valeurs de configuration,
et on obtient une analyse comportementale complète — pages vues, clics,
sessions, profils de visiteur, temps réel, tunnels, conversions et attribution
publicitaire — rendue dans un tableau de bord d'administration. Toutes les
données restent dans votre propre base. Aucun tiers, aucun service externe,
aucun worker : tout tourne là où Laravel tourne, hébergement mutualisé compris.

## Sommaire

1. [Prérequis](#prérequis)
2. [Installation](#installation)
3. [Intégration dans l'hôte](#intégration-dans-lhôte)
   - [Identité](#1-identité)
   - [Configuration du collecteur](#2-configuration-du-collecteur)
   - [Montage des écrans et navigation](#3-montage-des-écrans-et-navigation)
   - [Styles (sources Tailwind)](#4-styles-sources-tailwind)
4. [Instrumentation (`data-track-*`)](#instrumentation-data-track-)
5. [Événements nommés et conversions](#événements-nommés-et-conversions)
6. [Événements émis par le serveur](#événements-émis-par-le-serveur)
7. [Tunnels](#tunnels)
8. [Module marketing](#module-marketing)
9. [Temps réel](#temps-réel)
10. [Google Search Console](#google-search-console)
11. [Commandes et planification](#commandes-et-planification)
12. [Géolocalisation](#géolocalisation)
13. [Vie privée et RGPD](#vie-privée-et-rgpd)
14. [Référence de configuration](#référence-de-configuration)
15. [Modèle de données](#modèle-de-données)

## Prérequis

- PHP >= 8.4
- Laravel 13
- Livewire 4 et `falcon/ui-kit` 3 sont des dépendances Composer du paquet et
  s'installent d'eux-mêmes ; `analytics:install` lance aussi l'installateur du
  kit, donc une seule commande couvre les deux (voir
  [Styles](#4-styles-sources-tailwind)).

## Installation

### 1. Composer

Le paquet est distribué depuis un dépôt git privé. Composer ne lit les
`repositories` que dans le `composer.json` **racine**, donc l'hôte doit déclarer
le dépôt du paquet **et** celui de sa dépendance `falcon/ui-kit` :

```jsonc
// composer.json de l'application hôte
"repositories": [
    { "type": "vcs", "url": "https://github.com/MichaMegretDeveloppementWeb/falcon-analytics.git" },
    { "type": "vcs", "url": "https://github.com/MichaMegretDeveloppementWeb/falcon-ui-kit.git" }
]
```

```bash
composer require falcon/analytics
```

(Pour un développement en monodépôt, un dépôt `path` avec `"symlink": true`
pointant sur le dossier du paquet fonctionne de la même manière.)

**S'authentifier auprès des dépôts privés.** Les deux dépôts sont privés, donc
Composer a besoin d'un identifiant GitHub en lecture — sans quoi
`composer require` échoue sur « Could not find package ». Un seul jeton couvre
les deux dépôts. Trois cas :

- *Machine de développement* : lancez `composer require falcon/analytics` et
  suivez l'invite — Composer vous renvoie vers
  `https://github.com/settings/tokens/new?scopes=repo`, vous collez le jeton une
  fois, et il est retenu pour toute la machine dans `~/.composer/auth.json`
  (jamais dans le projet). Ou réglez-le à l'avance :
  `composer config --global github-oauth.github.com ghp_VOTRE_JETON`.
- *Serveur* : la même commande `composer config` globale en SSH ; un serveur dont
  le git système s'authentifie déjà auprès de GitHub par clé SSH marche aussi
  (Composer se rabat sur un clone git).
- *Intégration continue* : exposez le jeton en variable d'environnement au
  moment de l'installation :
  `COMPOSER_AUTH='{"github-oauth":{"github.com":"<jeton>"}}'`.

**Si l'hôte utilise déjà Livewire.** Livewire est une dépendance partagée et non
embarquée : Composer résout une seule installation pour toute l'application, et
le paquet s'y branche (même point de mise à jour, même exécution Alpine). Un
hôte en Livewire 4 n'a rien à faire ; un hôte en Livewire 3 obtient un conflit
de version franc — montez l'hôte en Livewire 4 d'abord, rien ne casse en
silence.

### 2. La commande d'installation

```bash
php artisan analytics:install
```

Composer a déjà apporté `falcon/ui-kit` (les tableaux de bord sont bâtis
dessus), et cette commande l'installe également. **Une commande, pas deux.**

Elle pose trois questions :

```
  Feuille de styles du back-office ?  [resources/css/app.css]
  Script du back-office ?             [resources/js/app.js]
  Script du site public ?             [resources/js/app.js]
```

Acceptez les valeurs par défaut sur un projet neuf. Les trois réponses servent :
deux portent un import, la troisième est passée à `ui-kit:install`. Il n'y a pas
de feuille publique à demander, le paquet n'en livrant aucune.

La commande installe ensuite le kit, publie `config/analytics.php`, ajoute les
variables `ANALYTICS_*` à `.env` et `.env.example`, **écrit ses deux imports**,
et lance les migrations (les tables sont préfixées `falcon_analytics_*`).
Relancez avec `--force` pour écraser la configuration publiée.

Sans interaction :

```bash
php artisan analytics:install --admin-css=resources/css/admin.css \
                              --admin-js=resources/js/admin.js \
                              --web-js=resources/js/web.js \
                              --no-interaction
```

L'écriture dans les fichiers d'environnement n'ajoute que ce qui manque et se
rejoue sans dommage : seules les variables absentes de chaque fichier sont
ajoutées, groupées et commentées ; une valeur existante n'est jamais réécrite,
et un fichier qui n'existe pas est laissé tel quel. La configuration publiée
reste la référence — chaque variable a une valeur par défaut sûre.

### 3. Ce que la commande a écrit

```css
/* resources/css/admin.css */
@import '../../vendor/falcon/analytics/resources/css/analytics-admin.css';
```

```js
/* resources/js/web.js */
import '../../vendor/falcon/analytics/resources/js/collector.js';
```

**Le paquet ne compile rien.** `analytics-admin.css` déclare ses vues comme
source Tailwind, et c'est **votre** build qui les compile dans l'unique feuille
de cet espace. Deux feuilles Tailwind sur une page écrivent les mêmes noms de
classes, et la dernière chargée gagne par sa seule position — d'où l'unicité.

**Le collecteur va dans le script public, jamais dans celui du back-office** :
on ne mesure pas les visites de qui consulte le tableau de bord. Il est autonome
— aucun `import`, aucune dépendance npm — et il s'arrête de lui-même quand
`window.__falconAnalytics` est absent, donc vous n'écrivez aucune condition.

Déclarez ensuite vos points d'entrée dans `vite.config.js`, chargez-les avec
`@vite`, et compilez :

```bash
npm run build
```

### 4. Brancher l'hôte

Suivez la liste d'[intégration](#intégration-dans-lhôte) ci-dessous. En bref :

1. Réglez vos gardes (et le cookie de consentement, s'il y en a un) dans le
   bloc `identity` de `config/analytics.php`.
2. Si vous utilisez un cookie de consentement, sortez-le du chiffrement
   (`bootstrap/app.php` → `encryptCookies(except: [...])`).
3. Derrière un proxy ou un répartiteur de charge, réglez `trustProxies` pour que
   la véritable adresse du client parvienne au paquet (la géolocalisation et les
   exclusions en dépendent).
4. Ajoutez `@analyticsConfig` aux gabarits publics que vous voulez suivre.
5. Protégez les tableaux de bord avec votre middleware d'administration et
   liez-les depuis votre navigation (ou reposez-vous sur la coquille autonome du
   paquet).
6. Assurez-vous que le cron standard du planificateur
   (`php artisan schedule:run` chaque minute) est actif : la maintenance est
   planifiée par le paquet lui-même.
7. Facultatif : mettez en place la [géolocalisation](#géolocalisation) (base
   locale, une commande).

## Intégration dans l'hôte

Le paquet ne touche ni à votre code, ni à vos routes, ni à votre navigation.
Toute l'intégration tient dans de la configuration déclarative et une directive
Blade.

### 1. Identité

Le type de sujet est le nom de la garde. Déclarez quels utilisateurs authentifiés
sont suivis comme sujets identifiés, et lesquels sont des personnes internes à
exclure entièrement :

```php
'identity' => [
    'subject_guards' => ['client', 'lessor'], // utilisateurs authentifies suivis comme sujets
    'exclude_guards' => ['admin'],            // personnes internes, jamais stockees
    'consent_cookie' => 'consent_marketing',  // cookie dont la valeur "1" accorde l'identifiant persistant
    'subjects' => [
        // Metadonnees d'affichage par garde, resolues au rendu seulement (jamais stockees).
        'client' => ['label' => 'Client', 'name' => ['first_name', 'last_name']],
        'lessor' => ['label' => 'Loueur', 'name' => ['company_name'], 'fallback' => ['first_name', 'last_name']],
    ],
],
```

- **`subject_guards`** — quand un utilisateur authentifié sur l'une de ces
  gardes navigue, ses sessions sont rattachées à un sujet (`type` = nom de la
  garde, `id` = identifiant de l'utilisateur). Une même personne connue résout
  toujours vers **un seul profil de visiteur** : quand un navigateur devient
  identifié et que le sujet possède déjà un profil, les profils fusionnent
  d'eux-mêmes (voir [Vie privée](#vie-privée-et-rgpd)).
- **`exclude_guards`** — rien n'est collecté tant qu'un tel utilisateur est
  authentifié : `@analyticsConfig` ne rend rien sur ses pages, et le collecteur
  s'arrête de lui-même faute de configuration.
- **`consent_cookie`** — quand il est renseigné, un visiteur ne reçoit
  l'identifiant *persistant* entre visites que si le cookie vaut `"1"` ; sinon
  le suivi reste borné à la session. `null` signifie que l'identifiant
  persistant est toujours accordé (à utiliser quand le consentement est traité
  ailleurs, ou n'est pas requis).
- **`subjects`** — comment les visiteurs identifiés sont affichés dans le
  tableau de bord. Pour chaque garde : un `label` facultatif (le nom de la garde
  par défaut) et une liste de colonnes `name` concaténées en nom d'affichage,
  lues sur le modèle de la garde (déduit de `config/auth.php`, ou imposé par des
  entrées explicites `model` / `table` / `key`). Les colonnes `fallback` servent
  quand toutes les colonnes `name` sont vides.

Pour une logique dynamique, enregistrez des fermetures sur le gestionnaire
`Analytics` depuis un fournisseur de services — elles priment sur la
configuration déclarative :

```php
use Falcon\Analytics\Facades\Analytics;

Analytics::resolveSubjectUsing(fn () => ...); // ['type' => string, 'id' => int] | null
Analytics::consentUsing(fn () => ...);        // bool
Analytics::excludeUsing(fn () => ...);        // bool
```

> **Ce que l'hôte doit garantir.** Un cookie de consentement doit être sorti du
> chiffrement (`bootstrap/app.php` → `encryptCookies(except: [...])`) pour que le
> serveur puisse le lire. Derrière un proxy, réglez `trustProxies` pour que la
> véritable adresse du client soit utilisée — sans quoi tous les visiteurs
> partagent l'adresse du proxy, ce qui casse la géolocalisation, `exclude_ips` et
> la carte du temps réel.

Autres exclusions : `exclude_ips` accepte des adresses et des plages CIDR
(réseau du bureau, sondes de disponibilité). Le trafic de robots est détecté
côté serveur (device-detector) et exclu de toute lecture des tableaux de bord.

### 2. Configuration du collecteur

Le **code** du collecteur vit dans votre bundle public, importé par
`analytics:install`. Ce qui vient encore du serveur, c'est sa **configuration**,
et c'est ce que la directive porte. Ajoutez-la aux gabarits que vous voulez
suivre (typiquement le gabarit public, avant `</body>`) :

```blade
@analyticsConfig
```

Elle rend un seul objet de configuration en ligne, et rien d'autre :

```html
<script>window.__falconAnalytics={"endpoint":"/fa","route":"prestations",…};</script>
```

**Cela ne peut pas être compilé dans un bundle**, et c'est pourquoi la directive
existe encore : le nom de la route change à chaque page, et le suivi se coupe
quand une personne authentifiée sur une garde exclue navigue. C'est la même
séparation que fait Livewire entre `@livewireScripts`, qui est du code, et
`@livewireScriptConfig`, qui est de la donnée.

Elle ne rend **rien du tout** quand le suivi est désactivé ou que le contexte
courant est exclu, et se dégrade en chaîne vide sur n'importe quelle erreur
interne — elle ne peut jamais casser une page de l'hôte. **Ne rien rendre
signifie suivi éteint** : le collecteur lit `window.__falconAnalytics` et
s'arrête de lui-même quand il est absent, donc vous n'écrivez aucune condition.

Le collecteur capture ensuite tout seul, sans une ligne de code :

- les **pages vues** à chaque chargement (URL, nom de route, référent) ;
- les **clics sur les éléments réellement interactifs** (voir
  [Instrumentation](#instrumentation-data-track-)) ;
- des **battements** tant que l'onglet est visible, qui alimentent l'activité de
  session, sa durée et l'écran temps réel ;
- l'**acquisition** (domaine du référent, `utm_*` et paramètres d'URL
  publicitaires) et l'**appareil** (type, navigateur) par session.

Les événements sont mis en mémoire tampon côté client et envoyés par lots (5 s
par défaut) au point d'ingestion (`/__analytics` par défaut), qui est limité en
débit et vérifie l'origine. Aucune dépendance à une bannière de consentement : le
collecteur fonctionne toujours, et le réglage de consentement ne décide que de
la persistance de l'identifiant entre les visites.

> **Limite connue de la vérification d'origine.** Une balise `beacon` ne peut pas
> porter de jeton CSRF, donc le point d'ingestion vérifie l'origine de la requête
> à la place : cela arrête les requêtes forgées entre sites depuis un navigateur,
> mais un client serveur à serveur peut falsifier l'en-tête et injecter des
> événements. La portée est bornée par la limite de débit (`throttle`), la
> validation stricte de la charge utile et les plafonds de taille — le compromis
> habituel de tout point d'ingestion propriétaire.

### 3. Montage des écrans et navigation

Deux modules d'administration indépendants sont enregistrés, montés entièrement
depuis la configuration — préfixe d'URL, préfixe de nom de route, middleware et
gabarit :

```php
'dashboard' => [
    'route_prefix' => 'admin/analytics',
    'route_name'   => 'analytics',
    'middleware'   => ['web', 'auth'],   // par exemple ['web', 'auth:admin'] pour une garde dediee
    'layout'       => null,              // null = coquille du paquet ; ou le nom d'une vue de l'hote
    'layout_section' => 'content',       // la section que le gabarit de l'hote rend
],

'marketing' => [
    'route_prefix' => 'admin/marketing',
    'route_name'   => 'marketing',
    'middleware'   => ['web', 'auth'],
    'layout'       => null,
    'layout_section' => 'content',
],
```

Les routes du paquet sont enregistrées hors de vos groupes de routes, donc le
middleware doit comprendre une pile de session (`web`) à côté de votre garde.

Le middleware configuré, `web` mis à part puisque Livewire le passe toujours,
est aussi enregistré comme **middleware persistant Livewire** : il est rejoué à
chaque mise à jour de composant (`/livewire/update`), pour que les actions des
écrans (effacement RGPD, création et modification de campagnes et de publicités,
déconnexion de la Search Console) continuent de rejouer votre garde une fois la
page chargée.

**Pages d'analytique** (`{nom}` = `dashboard.route_name`, `analytics` par
défaut) :

| Nom de route | Page |
|---|---|
| `{nom}.overview` | Synthèse : indicateurs, tendance, acquisition, audience, localités, principaux événements |
| `{nom}.realtime` | Temps réel : présents, carte du monde, activité en direct (voir [Temps réel](#temps-réel)) |
| `{nom}.visitors` | Annuaire des visiteurs depuis toujours (+ `{nom}.visitors.show`, le détail d'un profil) |
| `{nom}.sessions` | Liste des sessions (+ `{nom}.sessions.show`, le parcours complet) |
| `{nom}.events` | Événements nommés : volumes, valeurs, conversions |
| `{nom}.funnels` | Tunnels déclarés dans le code |
| `{nom}.integrations` | Intégrations (connexion Google Search Console) ; visible seulement une fois [configurée](#google-search-console) |

**Pages marketing** (`{nom}` = `marketing.route_name`, `marketing` par défaut) :

| Nom de route | Page |
|---|---|
| `{nom}.dashboard` | Synthèse marketing : performance des campagnes et des publicités, hors dépenses |
| `{nom}.campaigns` | Liste des campagnes (+ `{nom}.campaigns.show`, le détail) |
| `{nom}.ads` | Liste des publicités (+ `{nom}.ads.show`, le détail) |

**Gabarit.** Avec `layout => null`, les pages se rendent dans la coquille
autonome du paquet : sa propre barre latérale (les liens des deux modules,
construits depuis les noms de route configurés), sa barre du haut, sa bascule de
thème sombre — un espace d'administration prêt à l'emploi. Donnez le nom d'une
vue de l'hôte (`layouts.admin`, par exemple) pour imbriquer les pages dans votre
propre habillage ; dans ce cas, ajoutez vous-même les liens à votre navigation :

```blade
<a href="{{ route('analytics.overview') }}">Vue d'ensemble</a>
<a href="{{ route('analytics.realtime') }}">Temps réel</a>
<a href="{{ route('analytics.visitors') }}">Visiteurs</a>
<a href="{{ route('analytics.sessions') }}">Sessions</a>
<a href="{{ route('analytics.events') }}">Événements</a>
<a href="{{ route('analytics.funnels') }}">Tunnels</a>
```

Un gabarit d'hôte doit charger ses propres points d'entrée Vite (ceux dans
lesquels `analytics:install` a écrit), porter `{{ falcon_theme_class() }}` sur
`<html>` pour le thème sombre, et rendre la section nommée par `layout_section`
(`@yield('content')` avec la valeur par défaut).

### 4. Styles (sources Tailwind)

Les vues des tableaux de bord emploient les composants du kit et les utilitaires
Tailwind ; elles sont compilées par le build Vite de **l'hôte**. Une ligne,
écrite par `analytics:install` dans la feuille que vous avez nommée :

```css
@import '../../vendor/falcon/analytics/resources/css/analytics-admin.css';
```

Ce fichier déclare nos vues, avec un chemin résolu **depuis lui** — vous les
lisez sans les nommer, et sans savoir où elles vivent. Nous pourrions ajouter
cinquante écrans, votre ligne ne changerait pas.

Après chaque mise à jour du paquet, recompilez (`npm run build`) pour que les
nouvelles classes utilitaires des nouveaux écrans entrent dans la feuille.

## Instrumentation (`data-track-*`)

Les pages vues sont capturées à chaque chargement. **Les clics ne sont capturés
que sur les éléments réellement interactifs** : un clic sur du texte ou sur du
vide ne porte aucun signal et n'est jamais enregistré. Un élément compte comme
interactif quand il est :

- un contrôle natif : `<a>`, `<button>`, `<summary>`, ou un `<input>` actionnable
  (`submit` / `button` / `reset` / `image` / `checkbox` / `radio`) ;
- un composant ARIA : `role="button" | link | menuitem | menuitemcheckbox |
  menuitemradio | tab | option | switch` ;
- rendu interactif par un gestionnaire : `wire:click`, `@click`, `x-on:click`,
  `onclick`.

Si un élément n'est interactif que par du code à vous et ne porte rien de ce qui
précède dans son balisage, déclarez-le explicitement avec `data-track-event`. Sur
un `<form>`, `data-track-event` est capturé à la **soumission**, pas au clic.

Des attributs enrichissent un clic capturé :

| Attribut | Effet |
|---|---|
| `data-track-event="domaine.action"` | nomme l'action (clé de l'événement déclaré et de la jointure des tunnels) |
| `data-track-value="3"` | valeur de base facultative (l'étape du tunnel prime) |
| `data-track-prop-*="..."` | propriétés libres (`data-track-prop-listing-id` → `props.listing_id`) |
| `data-track-section="hero"` | zone logique appliquée à tout le sous-arbre |
| `data-track-label="..."` | libellé humain (sinon le texte détecté) |
| `data-track-ignore` | exclut l'élément et son sous-arbre |

## Événements nommés et conversions

Les clics anonymes sont capturés tout seuls ; les **événements nommés** sont ceux
que vous déclarez, et ce sont eux qui alimentent l'écran des événements, les
tunnels et les objectifs marketing. Déclarez-les dans `app/Analytics/events.php`
(chemin réglable par `analytics.events_path`) — la source unique :

```php
use Falcon\Analytics\Events\TrackedEvent;

TrackedEvent::define('auth.client.register.submit', 'Inscription client (soumission)', value: 5.0);
TrackedEvent::define('listing.publish.submit', 'Publication d\'une annonce', value: 15.0);
TrackedEvent::define('review.submit', 'Avis depose', conversion: true);
TrackedEvent::define('nav.catalog.click', 'Acces au catalogue');
```

- **`name`** — la clé technique, telle qu'employée par `data-track-event` ou
  `Analytics::record()`. Convention : `domaine.action`.
- **`label`** — ce que le tableau de bord affiche.
- **`value`** — valeur monétaire ou de score attachée par défaut à chaque
  occurrence.
- **`conversion`** — marque l'événement comme une conversion (mis en avant dans
  les tableaux de bord, compté dans les indicateurs du temps réel et du
  marketing). Sans ce drapeau, les événements portant une valeur comptent comme
  des conversions.

Deux commandes tiennent les déclarations honnêtes :

- `php artisan analytics:events:scan` compare le fichier aux événements
  réellement employés dans le code (attributs `data-track-event` et appels à
  `Analytics::record`, cherchés sous `analytics.events_scan_paths`) ; `--fix`
  ajoute les déclarations manquantes.
- `php artisan analytics:events:check` vérifie que chaque étape de tunnel
  référence un événement déclaré.

## Événements émis par le serveur

Au-delà de ce que le collecteur capture dans le navigateur, le code applicatif
peut émettre des événements directement : même visiteur, même session, même
stockage, mêmes tunnels. Utile pour les vraies conversions qu'un clic ne peut
pas confirmer (une inscription validée, un paiement abouti) :

```php
use Falcon\Analytics\Facades\Analytics;

Analytics::record('CompleteRegistration', value: 5.0, props: ['plan' => 'pro']);
```

L'appel est différé (il ne bloque jamais la réponse), sans effet quand le suivi
est éteint ou que le contexte est exclu, et ne lève jamais rien vers l'appelant.

## Tunnels

Déclarez les tunnels dans `app/Analytics/funnels.php` (chemin réglable par
`analytics.funnels_path`). Chaque étape correspond à un événement nommé **ou** à
une route de page vue, jamais aux deux, et porte son propre poids ; le même
événement peut appartenir à plusieurs tunnels avec une valeur différente dans
chacun :

```php
use Falcon\Analytics\Funnels\Funnel;

Funnel::define('acquisition_client', 'Acquisition client')
    ->step('Page inscription', value: 1, route: 'client.register')
    ->step('Soumission',       value: 5, event: 'auth.client.register.submit');
```

L'écran des tunnels rend, pour chacun, les volumes par étape, les taux de
conversion entre étapes et la valeur totale sur la période choisie.

**Branches parallèles.** Un jalon est souvent atteignable de plusieurs façons.
La progression étant séquentielle, poser les alternatives en étapes consécutives
se lirait « est passé par l'une, *puis* par l'autre » et rapporterait des zéros.
Déclarez-les à la même profondeur :

```php
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelBranch;

Funnel::define('acquisition', 'Acquisition')
    ->step('Offre consultee', value: 2, event: 'offer.viewed')
    ->step('Formulaire ouvert', value: 8, anyOf: [
        FunnelBranch::event('Questionnaire', 'quiz.opened'),
        FunnelBranch::route('Contact', 'contact'),
    ])
    ->step('Demande envoyee', value: 100, event: 'lead.created');
```

Un visiteur avance une fois, quelle que soit la branche empruntée, et l'étape
rapporte combien sont passés par chacune — y compris par les branches que
personne n'a prises, un zéro étant lui aussi une lecture. Une étape accepte
exactement un `event`, un `route` ou un `anyOf`, et `anyOf` demande au moins
deux branches.

## Module marketing

Un module de premier niveau distinct, qui mesure la performance publicitaire
**sans aucune API de régie** : les campagnes et les publicités sont définies
depuis les écrans marketing (stockées dans votre base) et rapprochées des
sessions par les paramètres d'URL que portent les liens de la publicité.

- Les **campagnes et les publicités** se créent dans l'interface. Chacune porte
  des conditions libres sur les paramètres d'URL (par exemple
  `utm_source=facebook` et `utm_campaign=summer`) ; une session dont les
  paramètres d'arrivée les satisfont est attribuée à la publicité, au moment du
  rapport.
- Les **objectifs** se déclarent par publicité en choisissant parmi les
  événements déclarés (voir
  [Événements nommés](#événements-nommés-et-conversions)) ; le module rapporte la
  portée, les conversions et la valeur par publicité et par campagne.
- **L'attribution est au premier contact et rétroactive** : la première session
  attribuée d'un visiteur marque l'acquisition, et ses conversions ultérieures
  créditent cette publicité, y compris d'une visite à l'autre.

## Temps réel

`{nom}.realtime` montre qui est en ligne maintenant : les indicateurs de la
fenêtre récente, une carte du monde des connexions (SVG intégré — aucun serveur
de tuiles, aucune bibliothèque cartographique, aucune requête externe), des
répartitions par pays, source et appareil, les pages consultées, un graphique de
pouls par minute, les visiteurs récents et un fil d'activité en direct.

- Le **rafraîchissement** est un simple sondage Livewire (`wire:poll.visible`,
  10 s par défaut), suspendu tant que l'onglet est caché. Aucun websocket, aucun
  worker, aucun service externe : cela fonctionne sur n'importe quel hébergement,
  par construction. La page est réservée à l'administration, donc la charge du
  sondage est marginale (chaque tour est une poignée de requêtes bornées et
  indexées).
- **« En ligne maintenant »** compte les visiteurs distincts actifs dans
  `realtime.online_seconds` (60 s par défaut, soit trois battements du
  collecteur).
- La carte a besoin de la [géolocalisation](#géolocalisation) pour placer ses
  points ; sans base, les sessions comptent comme non localisées.

```php
'realtime' => [
    'poll_seconds' => 10,     // intervalle de rafraichissement de la page
    'online_seconds' => 60,   // fenetre d'activite du « en ligne maintenant »
    'window_minutes' => 30,   // fenetre « recente » de chaque bloc
    'feed_limit' => 25,       // borne du fil d'activite
],
```

## Google Search Console

Google retire la requête de recherche du référent, donc les mots-clés organiques
ne parviennent jamais à une mesure propriétaire. L'intégration facultative de la
Search Console les affiche quand même — « Clics par recherches Google » sur la
vue d'ensemble — en laissant l'administration connecter la Search Console du site
par OAuth, en lecture seule.

**Mise en place côté hôte — Google Cloud, pas à pas** (la fonctionnalité reste
entièrement cachée tant que ce n'est pas fait). Tout se passe sur
<https://console.cloud.google.com>, et **le compte Google employé compte** : le
projet appartient à ce compte, et lui seul pourra l'administrer ensuite. Prenez
le compte qui administre le site.

1. **Projet** — choisissez (ou créez par *IAM & Admin → Create a project*) le
   projet Google Cloud qui portera le client OAuth. Si le site emploie déjà la
   connexion Google (Socialite), reprenez ce même projet : un projet peut porter
   plusieurs clients OAuth. Vérifiez que le projet affiché dans la barre du haut
   est le bon avant chacune des étapes suivantes.
2. **Activer l'API** — *APIs & Services → Library*, cherchez « Google Search
   Console API » (lien direct :
   <https://console.cloud.google.com/apis/library/searchconsole.googleapis.com>),
   cliquez **Enable** *sur ce projet*. Ne sautez pas cette étape : le
   consentement OAuth fonctionne sans elle, mais tous les appels de données sont
   ensuite rejetés (l'écran des intégrations affiche alors que la liste des
   propriétés n'a pas pu être chargée).
3. **Écran de consentement** — *APIs & Services → OAuth consent screen* : le type
   *External* convient. Tant que l'application est en statut *Testing*, seuls les
   comptes listés sous **Test users** peuvent autoriser : ajoutez-y le compte
   Google qui connectera la Search Console. (Publier l'application n'est pas
   nécessaire pour cette intégration à un seul administrateur.)
4. **Client OAuth** — *APIs & Services → Credentials → Create credentials → OAuth
   client ID*, type **Web application**. Sous *Authorized redirect URIs*, ajoutez
   l'adresse de retour du paquet **exactement** telle que l'écran des
   intégrations l'affiche :
   `https://votre-hote/{dashboard.route_prefix}/integrations/search-console/callback`.
   Google n'accepte que des domaines publics (plus `localhost`) ; un domaine
   local en `.test` est refusé, donc l'aller-retour OAuth se valide sur un
   environnement déployé.
5. **Identifiants** — copiez l'identifiant et le secret du client dans le `.env`
   de l'hôte :

```dotenv
ANALYTICS_GSC_CLIENT_ID=xxx.apps.googleusercontent.com
ANALYTICS_GSC_CLIENT_SECRET=xxx
```

   (puis `php artisan config:cache` si l'hôte met sa configuration en cache).
6. **Côté Search Console** — le compte Google qui autorisera doit posséder le
   site comme **propriété vérifiée** dans
   <https://search.google.com/search-console> (peu importe la méthode de
   vérification). Le paquet ne propose pas les propriétés non vérifiées.

**Parcours d'administration** : sur `{nom}.integrations`, « Connecter Google
Search Console » lance le consentement OAuth (portée `webmasters.readonly`,
accès hors ligne) ; de retour de Google, on choisit la **propriété** vérifiée à
rattacher. La déconnexion, confirmée par une modale, révoque le jeton et
supprime la connexion.

**Dépanner la connexion** :

| Symptôme | Cause et remède |
|---|---|
| Google affiche `redirect_uri_mismatch` | L'adresse enregistrée sur le client OAuth diffère de celle que le paquet envoie. Recopiez-la mot pour mot depuis l'écran des intégrations. |
| Google affiche `access_denied` ou « app not verified » | L'écran de consentement est en *Testing* et le compte qui se connecte n'est pas dans les **Test users** (étape 3). |
| Connecté, mais la liste des propriétés ne se charge pas | L'API Search Console n'est pas activée **sur le projet qui porte le client** (étape 2). Activez-la, puis « Réessayer » — aucune reconnexion n'est nécessaire. |
| La liste des propriétés est vide | Le compte qui autorise ne possède aucune propriété vérifiée (étape 6). |
| La carte affiche « Erreur » plus tard | Google a révoqué ou expiré l'autorisation (changement de mot de passe, retrait de droits). « Reconnecter » relance le consentement ; les données en cache restent. |

**Synchronisation** : `analytics:search-console:sync` tourne chaque jour
(planifiée par le paquet), et l'écran des intégrations offre la même
synchronisation à la demande (« Synchroniser maintenant » — en ligne, sans
worker ; le premier rattrapage peut prendre un moment). Les données de la Search
Console ont environ trois jours de retard sur la réalité et l'API impose des
quotas, donc le tableau de bord ne lit jamais que le cache local
(`falcon_analytics_search_queries`) : la première exécution rattrape l'historique
d'environ seize mois offert par l'API, en appels paginés, puis chaque exécution
relit les derniers jours. Le jeton de rafraîchissement est stocké **chiffré** ;
un accès révoqué signale la connexion sur l'écran des intégrations et sur la
carte de la vue d'ensemble.

**Affichage** : la section de la vue d'ensemble liste les principales requêtes de
la période par clics, avec impressions, taux de clic et position moyenne pondérée
par les impressions, plus une mention de fraîcheur « Données Google jusqu'au … ».
À ne pas confondre avec le terme de campagne (`utm_term`) : ce sont les mots
réellement tapés dans Google.

## Commandes et planification

| Commande | Rôle |
|---|---|
| `analytics:install` | installer le kit, publier la configuration, écrire les deux imports, ajouter les variables d'environnement, lancer les migrations |
| `analytics:check` | diagnostiquer une installation : migrations, imports, collecteur, point d'ingestion, protection des modules |
| `analytics:geoip:download` | télécharger ou rafraîchir la base GeoLite2 City locale |
| `analytics:geoip:check` | dire si la base est utilisable, et pourquoi une adresse résout ou non |
| `analytics:sweep` | poser `ended_at` sur les sessions inactives au-delà du délai |
| `analytics:prune` | supprimer les événements bruts plus vieux que `retention_days` |
| `analytics:events:scan` | comparer les événements déclarés à ceux employés dans le code (`--fix` complète) |
| `analytics:events:check` | vérifier que les étapes de tunnel référencent des événements déclarés |
| `analytics:search-console:sync` | tirer les requêtes organiques dans le cache local |

`sweep` (toutes les 5 minutes), `prune` (chaque jour à 03:30), la synchronisation
Search Console (chaque jour à 05:00, inerte sans connexion rattachée) et le
rafraîchissement mensuel de GeoLite2 (le 1er à 04:00, inerte tant qu'aucune clé
de licence n'est posée) sont **planifiés par le paquet lui-même** : l'hôte n'a
qu'à déclencher le planificateur standard de Laravel chaque minute, sans cron
dédié à l'analytique. Les deux formes de déclenchement conviennent : un vrai cron
(`* * * * * php artisan schedule:run`) ou, en hébergement mutualisé, un point
HTTP appelant `Artisan::call('schedule:run')`.

## Géolocalisation

Les localités sont résolues depuis l'adresse IP du visiteur contre une **base
MMDB locale**, donc une adresse ne quitte jamais le serveur (aucun appel tiers au
moment de la requête).

**MaxMind GeoLite2 City** (gratuite, téléchargement intégré) :

1. Créez un compte et une clé de licence gratuits :
   <https://www.maxmind.com/en/geolite2/signup>
2. Ajoutez la clé au `.env` : `ANALYTICS_GEOIP_LICENSE_KEY=xxxxxxxx`
3. Téléchargez la base : `php artisan analytics:geoip:download`

Le `.mmdb` se pose dans `storage/app/analytics/GeoLite2-City.mmdb` et se
rafraîchit chaque mois par la commande planifiée.

**Quand les localités restent vides, demandez pourquoi.** La géolocalisation se
dégrade en « inconnu » quelle qu'en soit la cause — pas de base, une base
tronquée, ou une adresse privée — donc l'écran seul ne dit pas laquelle
corriger. `php artisan analytics:geoip:check` rapporte le chemin, la taille et la
date de la base, l'adresse de développement configurée, et résout une adresse
témoin en nommant l'état. Passez une adresse pour tester celle-là :
`php artisan analytics:geoip:check 92.222.0.1`. Les écrans Sessions et Visiteurs
portent le même avertissement quand il y a quelque chose à y faire.

**N'importe quelle autre base MMDB au niveau ville convient** (par exemple
[DB-IP City Lite](https://db-ip.com/db/download/ip-to-city-lite), sans compte) :
téléchargez le `.mmdb` vous-même et pointez `ANALYTICS_GEOIP_DATABASE` sur son
chemin absolu.

**En développement local** : les adresses privées ou de bouclage (`127.0.0.1`) ne
peuvent jamais être localisées. Réglez `ANALYTICS_GEOIP_DEV_IP` sur n'importe
quelle adresse publique pour qu'elle se substitue aux adresses privées ou
réservées — inerte en production par construction, une vraie adresse publique
n'étant jamais remplacée. Prenez-en une qui résout vers une ville et non vers un
simple pays, sans quoi la colonne n'affichera jamais qu'un drapeau ;
`analytics:geoip:check <ip>` dit ce qu'une candidate résout avant qu'on s'y
engage.

La géolocalisation par IP est par nature au niveau de la ville ou de la région ;
elle ne désignera pas une rue.

## Vie privée et RGPD

- **Propriétaire de bout en bout.** Tout — collecte, stockage, tableaux de bord,
  géolocalisation — se passe sur votre propre infrastructure ; aucune donnée n'en
  sort jamais.
- **Consentement pris en compte.** Avec `identity.consent_cookie` renseigné,
  l'identifiant persistant entre visites n'est accordé qu'après consentement ;
  tout le reste demeure borné à la session.
- **Anonymisation d'IP** — `privacy.anonymize_ip = true` stocke une adresse
  tronquée au lieu de l'adresse brute (la localité est résolue avant la
  troncature).
- **Nettoyage des URL** — les paramètres de requête listés dans
  `privacy.redact_query_params` (jetons, secrets, adresses de courriel…) sont
  retirés des URL stockées ; les paramètres de suivi (`utm_*`, identifiants
  publicitaires) sont conservés.
- **Rétention** — les événements bruts sont purgés au-delà de `retention_days`
  (90 par défaut) par `analytics:prune`, planifiée par le paquet ; les sessions et
  les profils de visiteur sont conservés.
- **Droit à l'effacement** — depuis la page de détail d'un visiteur,
  l'administration peut l'effacer : profil, alias fusionnés, sessions et
  événements sont supprimés en une action.
- **Une personne, un profil** — quand un sujet identifié est reconnu dans un
  second navigateur ou sur un second appareil, les profils fusionnent (le plus
  ancien survit, l'autre devient un alias qui y renvoie) ; une connexion sur un
  navigateur partagé mène au profil de la personne connectée, sans voler celui du
  propriétaire du navigateur.
- **Exclusion des personnes internes** — `exclude_guards` et `exclude_ips`
  tiennent le trafic interne entièrement dehors ; les robots sont filtrés de tous
  les rapports.

## Référence de configuration

Toutes les clés vivent dans `config/analytics.php` ; les valeurs pilotées par
l'environnement sont entre parenthèses.

| Clé | Défaut | Rôle |
|---|---|---|
| `enabled` (`ANALYTICS_ENABLED`) | `true` | interrupteur général : ni ingestion ni collecteur une fois éteint |
| `log_channel` | `null` | canal de journalisation du paquet (`null` = celui de l'application) |
| `funnels_path` | `null` → `app/Analytics/funnels.php` | fichier des tunnels déclarés dans le code |
| `events_path` | `null` → `app/Analytics/events.php` | fichier des événements déclarés |
| `events_scan_paths` | `['app', 'resources/views']` | chemins parcourus par `analytics:events:scan` |
| `endpoint` | `__analytics` | chemin d'ingestion (`/__analytics`) |
| `assets.admin_css` · `assets.admin_js` · `assets.web_js` | `resources/css/app.css` · `resources/js/app.js` · `resources/js/app.js` | vos points d'entrée, là où `analytics:install` a écrit ses deux imports |
| `throttle` | `120,1` | limite de débit de l'ingestion (requêtes, minutes) |
| `exclude_ips` | `[]` | adresses et plages CIDR exclues du suivi |
| `identity.subject_guards` | `['web']` | gardes suivies comme sujets identifiés |
| `identity.exclude_guards` | `[]` | gardes entièrement exclues |
| `identity.consent_cookie` | `null` | cookie qui conditionne l'identifiant persistant |
| `identity.subjects` | `[]` | métadonnées d'affichage par garde (libellé, colonnes de nom) |
| `dashboard.route_prefix` | `admin/analytics` | préfixe d'URL des tableaux de bord |
| `dashboard.route_name` | `analytics` | préfixe des noms de route |
| `dashboard.middleware` | `['web', 'auth']` | protection des tableaux de bord |
| `dashboard.layout` | `null` | `null` = coquille du paquet, ou vue de gabarit de l'hôte |
| `dashboard.layout_section` | `content` | section que le gabarit de l'hôte rend |
| `marketing.*` | `admin/marketing` / `marketing` / … | les mêmes cinq clés pour le module marketing |
| `retention_days` | `90` | rétention des événements bruts |
| `session.timeout_minutes` | `5` | inactivité au-delà de laquelle une session est close |
| `session.heartbeat_seconds` | `20` | intervalle des battements du collecteur (onglet visible) |
| `session.flush_seconds` | `5` | intervalle d'envoi des lots par le collecteur |
| `realtime.poll_seconds` | `10` | rafraîchissement de la page temps réel |
| `realtime.online_seconds` | `60` | fenêtre du « en ligne maintenant » |
| `realtime.window_minutes` | `30` | fenêtre récente du temps réel |
| `realtime.feed_limit` | `25` | borne du fil d'activité |
| `search_console.client_id` (`ANALYTICS_GSC_CLIENT_ID`) | `''` | identifiant du client OAuth Google (vide = fonctionnalité cachée) |
| `search_console.client_secret` (`ANALYTICS_GSC_CLIENT_SECRET`) | `''` | secret du client OAuth Google |
| `search_console.redirect` (`ANALYTICS_GSC_REDIRECT`) | `null` | adresse de retour imposée (`null` = route de retour du paquet) |
| `privacy.anonymize_ip` | `false` | stocker des adresses tronquées |
| `privacy.redact_query_params` | jetons, secrets, courriel | paramètres retirés des URL stockées |
| `geoip.license_key` (`ANALYTICS_GEOIP_LICENSE_KEY`) | `''` | clé de licence MaxMind |
| `geoip.edition` (`ANALYTICS_GEOIP_EDITION`) | `GeoLite2-City` | édition MaxMind |
| `geoip.database_path` (`ANALYTICS_GEOIP_DATABASE`) | `storage/app/analytics/GeoLite2-City.mmdb` | emplacement du MMDB |
| `geoip.dev_ip` (`ANALYTICS_GEOIP_DEV_IP`) | `null` | adresse publique substituée aux adresses privées ou réservées (développement) |
| `geoip.download_url` | permalien MaxMind | modèle d'URL de téléchargement (`{edition}` et `{license_key}` sont remplacés) |

## Modèle de données

Huit tables, toutes préfixées `falcon_analytics_` :

| Table | Contenu |
|---|---|
| `falcon_analytics_visitors` | une ligne par navigateur ou personne (uuid, rattachement au sujet, alias de fusion) |
| `falcon_analytics_sessions` | une ligne par visite (activité, appareil, acquisition, localité, paramètres marketing) |
| `falcon_analytics_events` | les événements bruts (pages vues, clics, événements nommés), purgés au-delà de la rétention |
| `falcon_analytics_campaigns` | les campagnes marketing, définies dans l'interface |
| `falcon_analytics_ads` | les publicités et leurs conditions sur les paramètres d'URL |
| `falcon_analytics_ad_objectives` | les événements choisis comme objectifs, par publicité |
| `falcon_analytics_search_console` | la connexion Search Console (jetons OAuth chiffrés, propriété, état) |
| `falcon_analytics_search_queries` | les requêtes organiques en cache, par jour (clics, impressions, position) |

Les migrations se chargent depuis le paquet, sans publication ; chaque lecture
des tableaux de bord passe par des requêtes bornées et indexées, pour que les
écrans restent rapides sur de gros volumes.

## Licence

Propriétaire.
