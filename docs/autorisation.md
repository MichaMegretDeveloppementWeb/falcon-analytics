# Autorisation

[← Documentation](../README.md)

Cette page répond à deux questions · **qui peut ouvrir quel écran**, et **qui
peut faire quel geste** (créer une campagne, la supprimer, effacer un visiteur…).

---

## Le principe

**Deux contrôles se suivent, et chacun a son rôle.**

1. **La porte** décide qui entre. Ce sont les middlewares de `admin.middleware`
   et de `admin.marketing.middleware` · ils ouvrent la session, cherchent le
   compte dans le bon garde et renvoient vers la page de connexion quiconque
   n'est pas connecté. La porte ne connaît pas vos rôles.
2. **Les capacités** décident de ce que chaque personne entrée peut ouvrir et
   faire. Une capacité est une question nommée que le paquet pose à Laravel,
   par exemple `analytics.campaigns.delete` · « ce compte peut-il supprimer
   cette campagne ? ». **Le paquet pose la question, votre application y
   répond**, par une *Gate* de Laravel.

**Par défaut, toutes les capacités répondent oui à tout compte connecté**, et
non à une personne qui ne l'est pas. Tant que vous n'écrivez rien, tout
fonctionne comme sans capacités.

**Les capacités sont rangées en arbre**, et c'est ce qui vous évite de tout
écrire ·

- une capacité **que vous n'écrivez pas** répond comme celle qui est au-dessus
  d'elle ;
- une capacité **que vous écrivez** répond par votre règle, qui **remplace**
  celle du dessus.

### Comment une question remonte l'arbre

Vous avez écrit une seule règle, sur `analytics.marketing` · réservé au gérant.
Un employé clique « Supprimer » sur la campagne Printemps.

1. Le paquet demande `analytics.campaigns.delete`. Vous ne l'avez pas écrite ·
   elle demande à celle du dessus, `analytics.campaigns.edit`.
2. Pas écrite non plus · elle demande à `analytics.campaigns`, qui demande à
   `analytics.marketing`.
3. Celle-là, vous l'avez écrite · votre règle répond **non**, puisque ce compte
   n'est pas le gérant. La suppression est refusée.

**Une ligne a donc fermé tout le marketing** · ses trois écrans et tous ses
gestes.

---

## L'arbre

```
analytics
├─ analytics.audience
│  ├─ analytics.overview
│  ├─ analytics.realtime
│  ├─ analytics.sessions
│  ├─ analytics.visitors
│  │  └─ analytics.visitors.delete
│  ├─ analytics.events
│  ├─ analytics.funnels
│  └─ analytics.integrations
│     └─ analytics.integrations.manage
└─ analytics.marketing
   ├─ analytics.marketing-dashboard
   ├─ analytics.campaigns
   │  └─ analytics.campaigns.edit
   │     └─ analytics.campaigns.delete
   └─ analytics.ads
      └─ analytics.ads.edit
         └─ analytics.ads.delete
```

- `analytics.audience` et `analytics.marketing` sont des **groupes** · ils n'ont
  pas d'écran à eux, ils servent à fermer tout un espace d'un coup.
- **Chaque écran a sa capacité**, et elle vaut aussi pour ce qu'on y fait tant
  que rien n'est écrit plus bas.
- **« Supprimer » est rangé sous « modifier »** · qui ne peut pas modifier une
  campagne ne peut pas la supprimer non plus, sauf si vous l'écrivez.

## Les capacités

Toutes sont des cas de l'énumération `Falcon\Analytics\Enums\Authorization\Ability`.
**Écrivez de préférence le cas plutôt que le nom** · votre éditeur vous le
propose, et un nom mal tapé devient une erreur immédiate, au lieu d'une règle qui
ne s'applique jamais.

| Cas de `Ability` | Nom | Ce qu'elle couvre | Sans règle, elle suit |
|---|---|---|---|
| `Analytics` | `analytics` | tout le paquet | · (oui à tout compte connecté) |
| `Audience` | `analytics.audience` | l'espace Audience, d'un coup | `Analytics` |
| `Overview` | `analytics.overview` | la vue d'ensemble | `Audience` |
| `Realtime` | `analytics.realtime` | le temps réel | `Audience` |
| `Sessions` | `analytics.sessions` | les sessions, et le détail d'une session | `Audience` |
| `Visitors` | `analytics.visitors` | les visiteurs, et la fiche d'un visiteur | `Audience` |
| `VisitorsDelete` | `analytics.visitors.delete` | supprimer les données d'un visiteur | `Visitors` |
| `Events` | `analytics.events` | les événements | `Audience` |
| `Funnels` | `analytics.funnels` | les tunnels | `Audience` |
| `Integrations` | `analytics.integrations` | les intégrations, et l'état de la connexion à Search Console | `Audience` |
| `IntegrationsManage` | `analytics.integrations.manage` | connecter Search Console, choisir la propriété, synchroniser, déconnecter | `Integrations` |
| `Marketing` | `analytics.marketing` | l'espace Marketing, d'un coup | `Analytics` |
| `MarketingDashboard` | `analytics.marketing-dashboard` | le tableau de bord marketing | `Marketing` |
| `Campaigns` | `analytics.campaigns` | les campagnes, et le détail d'une campagne | `Marketing` |
| `CampaignsEdit` | `analytics.campaigns.edit` | créer et modifier une campagne et ses conditions | `Campaigns` |
| `CampaignsDelete` | `analytics.campaigns.delete` | supprimer une campagne | `CampaignsEdit` |
| `Ads` | `analytics.ads` | les publicités, et le détail d'une publicité | `Marketing` |
| `AdsEdit` | `analytics.ads.edit` | créer et modifier une publicité, ses conditions et ses objectifs | `Ads` |
| `AdsDelete` | `analytics.ads.delete` | supprimer une publicité | `AdsEdit` |

---

## Écrire vos règles

### Où les écrire

Nous vous conseillons un provider réservé aux règles, plutôt que de les ajouter à
`AppServiceProvider` :

```bash
php artisan make:provider GateServiceProvider
```

Laravel l'inscrit tout seul dans `bootstrap/providers.php`. Vous restez libre de
les écrire ailleurs · le paquet les lit une fois toute l'application démarrée,
pourvu qu'elles soient écrites dans le `boot()` d'un provider.

### La forme d'une règle

```php
Gate::define(Ability::Marketing, fn (Admin $admin): bool => $admin->role === Role::Manager);
```

- **à gauche**, la capacité ;
- **à droite**, une fonction qui reçoit le compte connecté et répond `true` ou
  `false`. Le compte est celui du garde de votre porte · ici un `Admin`.

**Ce que « gérant » veut dire vous appartient.** Les exemples ci-dessous
supposent une colonne `role` sur vos administrateurs, lue par une énumération
`App\Enums\Role` · remplacez-la par votre propre notion de rôle, qu'il s'agisse
d'une colonne, d'une table ou d'un paquet de permissions.

### Des exemples

Chacun s'écrit dans le `boot()` de votre `GateServiceProvider`, avec ces imports ·

```php
use App\Enums\Role;
use App\Models\Admin;
use App\Models\User;
use Falcon\Analytics\Enums\Authorization\Ability;
use Illuminate\Support\Facades\Gate;
```

**1 · Réserver tout Analytics à vos administrateurs.** Vos clients et vos
administrateurs partagent la même table `users`, avec une colonne `is_admin`.
Sans règle, un client connecté qui tape l'adresse des écrans y entrerait.

```php
Gate::define(Ability::Analytics, fn (User $user): bool => $user->is_admin);
```

Une ligne · les dix-neuf capacités la suivent.

**2 · Réserver le marketing au gérant.** Les employés gardent l'audience.

```php
Gate::define(Ability::Marketing, fn (Admin $admin): bool => $admin->role === Role::Manager);
```

**3 · Tout le monde voit les campagnes, seul le gérant les modifie.**

```php
Gate::define(Ability::CampaignsEdit, fn (Admin $admin): bool => $admin->role === Role::Manager);
```

Les employés ouvrent la liste et le détail des campagnes · les boutons
« Nouvelle campagne », « Modifier » et « Supprimer » ne leur sont pas proposés,
la suppression étant rangée sous la modification.

**4 · Les chiffres pour tous, les fiches des visiteurs pour le gérant.** Les
fiches montrent le parcours d'une personne précise · un stagiaire n'a pas à les
lire.

```php
Gate::define(Ability::Visitors, fn (Admin $admin): bool => $admin->role === Role::Manager);
```

Le stagiaire garde la vue d'ensemble, le temps réel, les sessions · le lien vers
la fiche d'un visiteur devient du texte, et l'adresse tapée répond 403.

**5 · Trois niveaux · assistant, administrateur, super-administrateur.**

```php
Gate::before(fn (Admin $admin): ?bool => $admin->role === Role::SuperAdmin ? true : null);

Gate::define(Ability::Analytics, fn (Admin $admin): bool => $admin->role === Role::Admin);
Gate::define(Ability::IntegrationsManage, fn (): bool => false);
```

- `Gate::before` est un outil de Laravel, posé avant toutes les questions · il
  répond **oui à tout** pour le super-administrateur, et « pas d'avis »
  (`null`) pour les autres, qui passent alors par les capacités ;
- l'administrateur a tout, sauf connecter ou déconnecter Search Console ;
- l'assistant n'a rien d'Analytics ;
- `Gate::before` vaut pour toute votre application, pas seulement pour ce paquet.

**6 · Ouvrir plus largement qu'au-dessus.** L'audience est réservée au gérant,
mais tout le monde peut regarder le temps réel.

```php
Gate::define(Ability::Audience, fn (Admin $admin): bool => $admin->role === Role::Manager);
Gate::define(Ability::Realtime, fn (Admin $admin): bool => true);
```

La règle écrite sur `Realtime` remplace celle du dessus · les employés voient
l'espace Audience avec le seul temps réel.

**7 · Une règle qui regarde l'objet.** Une campagne active ne se supprime pas,
sauf par le gérant.

```php
use Falcon\Analytics\Models\Campaign;

Gate::define(Ability::CampaignsDelete, fn (Admin $admin, ?Campaign $campaign = null): bool =>
    $admin->role === Role::Manager || $campaign?->is_active === false);
```

Dans la liste des campagnes, le bouton « Supprimer » n'apparaît que sur les
campagnes que la règle accepte · le paquet la pose ligne par ligne, avec la
campagne de la ligne.

**8 · Ouvrir les écrans à des personnes non connectées.** Par défaut, une
personne non connectée n'a accès à rien, **même si la porte la laisse passer**.
Un `auth` oublié dans la configuration se voit ainsi au premier essai, au lieu
d'ouvrir les écrans à tout le monde. Pour les ouvrir exprès, une ligne sur la
racine ·

```php
Gate::define(Ability::Analytics, fn (?Admin $admin = null): bool => true);
```

Le `?Admin $admin = null` dit que la règle accepte une personne non connectée ·
Laravel ne pose jamais la question pour elle à une règle qui ne le dit pas.

### L'objet que reçoit une règle

Quand le geste porte sur un objet précis, la capacité le reçoit en second
argument. **Déclarez-le toujours facultatif** · il vaut `null` quand on crée
l'objet, qui n'existe pas encore, et **une adresse pose toujours la question sans
objet**.

| Capacités | Objet reçu |
|---|---|
| `CampaignsEdit`, `CampaignsDelete` | `Falcon\Analytics\Models\Campaign` |
| `AdsEdit`, `AdsDelete` | `Falcon\Analytics\Models\Ad` |
| `VisitorsDelete` | `Falcon\Analytics\Models\Visitor` |
| les autres | aucun |

L'objet arrive aussi à une règle écrite plus haut dans l'arbre · une règle sur
`CampaignsEdit` reçoit la campagne quand `CampaignsDelete`, qui la suit, lui
pose la question.

### Vos propres écrans

Si l'un de vos écrans lit ou modifie ces données par les modèles du paquet,
c'est votre porte · vérifiez-y la capacité vous-même, avec la même règle ·

```php
Gate::authorize(Ability::CampaignsDelete, $campaign);
```

---

## Ce que voit un compte refusé

- **Le menu du paquet** n'offre que les écrans permis, et un espace dont aucun
  écran n'est permis disparaît.
- **Les boutons** des gestes refusés ne sont pas affichés.
- **Un lien vers un écran refusé** devient du texte.
- **Votre propre menu** reste le vôtre · entourez ses liens de
  `@can(\Falcon\Analytics\Enums\Authorization\Ability::Marketing)`.
- **Une adresse tapée à la main** répond « accès interdit » (erreur 403).
- **Un geste envoyé sans passer par l'écran**, depuis la console du navigateur
  par exemple, est refusé par le serveur, et rien n'est écrit.

---

## Une étape de plus sur une branche

Certaines protections ne sont pas une question de rôle · redemander le mot de
passe, demander le code d'une double authentification, garder une trace de qui a
consulté quoi. Ce sont des middlewares, et vous pouvez les poser sur une partie
de l'arbre, par `admin.middleware_for` ·

```php
// config/analytics.php
'admin' => [
    'middleware' => ['web', 'auth:admin'],
    'middleware_for' => [
        'analytics.marketing' => ['password.confirm'],
        'analytics.visitors' => ['record.consultation'],
    ],
],
```

- **Chaque écran de la branche** franchit, dans cet ordre, la porte, puis sa
  capacité, puis les étapes de chaque branche où il se trouve, de la racine vers
  le bas. Ici, les trois écrans marketing demandent de confirmer le mot de passe ;
  le reste de l'audience n'est pas touché.
- **Chaque clic sur ces écrans refait les étapes**, comme la porte. Un écran
  resté ouvert ne devient pas une porte ouverte · une fois la confirmation
  expirée, le clic suivant mène à la page de confirmation, puis revient.
- **Les clés sont des écrans ou des groupes.** Un geste n'a pas d'adresse à lui ·
  pour réserver un geste, écrivez une capacité.

**Un middleware de trace** verrait chaque clic, puisque chaque clic le rejoue.
Pour ne compter que l'ouverture de la page, il laisse passer les demandes qui
portent l'en-tête `X-Livewire`, que porte chaque clic ·

```php
public function handle(Request $request, Closure $next): Response
{
    if (! $request->hasHeader('X-Livewire')) {
        // record the consultation
    }

    return $next($request);
}
```

---

## Vérifier

```bash
php artisan analytics:abilities
```

dessine l'arbre, et dit pour chaque capacité qui répond · « votre règle », « suit
analytics.marketing », ou le défaut.

```bash
php artisan analytics:abilities --user=3
```

ajoute ce que chaque capacité répond au compte 3 · le compte est cherché dans le
garde de votre porte, `--guard=` en nomme un autre. Les réponses sont données sans
objet · une règle qui regarde une campagne peut répondre autrement sur l'une
d'elles.

```bash
php artisan analytics:check
```

signale, parmi ses contrôles, **une règle écrite sous un nom qui n'existe pas**
(`analytics.visiteurs` au lieu de `analytics.visitors` · sa restriction ne
s'appliquerait jamais), **une clé de `admin.middleware_for` qui ne couvre aucun
écran** ou nomme un middleware inconnu, et **une porte sans authentification**.

---

## Après une mise à jour

Une capacité nouvelle arrive sous une branche existante et suit celle du dessus ·
**ce que vous avez fermé reste fermé**. Le [journal des versions](../CHANGELOG.md)
nomme chaque capacité ajoutée.
