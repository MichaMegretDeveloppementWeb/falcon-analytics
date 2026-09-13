# Falcon Analytics

**Une mesure d'audience pour les applications Laravel, qui ne sort pas de chez
vous.**

On l'installe, on pose une directive sur les pages à suivre, et on obtient
quatorze écrans d'administration · pages vues, clics, sessions, profils de
visiteur, temps réel, tunnels, conversions et attribution publicitaire.

**Tout reste dans votre base.** Aucun tiers, aucun service externe, aucun
worker · ça tourne là où Laravel tourne, hébergement mutualisé compris.

---

## Pourquoi celui-ci

| | |
|---|---|
| **Vos données sont à vous** | huit tables dans votre base, et rien qui parte ailleurs |
| **Vos visiteurs sont des vôtres** | le paquet sait que ce visiteur est *ce client-là*, parce qu'il lit vos gardes d'authentification. Aucun outil externe ne peut faire ça |
| **Rien à compiler** | le paquet livre ses écrans déjà compilés · pas de Node chez vous, pas de vues à lire, pas de build à refaire |
| **Rien à faire tourner** | pas de file d'attente, pas de websocket, pas de démon · le temps réel se rafraîchit par une interrogation Livewire ordinaire |
| **Discret par défaut** | sans consentement, aucun identifiant ne survit à la session · et c'est le comportement par défaut, pas une option |

---

## Ce qu'il vous faut

PHP 8.5, Laravel 13, Livewire 4.2, et une base MySQL, MariaDB ou PostgreSQL.
**Node, non.** `falcon/ui-kit` vient avec le paquet.

Il faut aussi que `schedule:run` tourne chaque minute · c'est ce qui ferme les
sessions inactives et efface les événements périmés.

---

## Installer

```bash
composer require falcon/analytics
php artisan analytics:install
```

Puis **une ligne dans le gabarit public** que vous voulez mesurer ·

```blade
@analyticsCollector
```

Et c'est mesuré. **La procédure complète** — les dépôts privés,
l'authentification GitHub, le montage des écrans dans votre propre habillage, le
déploiement — est dans **[docs/installation.md](docs/installation.md)**.

Pour vérifier une installation à tout moment · `php artisan analytics:check`,
dix contrôles sur des défauts qui échouent tous en silence.

---

## La documentation

| | |
|---|---|
| **[installation.md](docs/installation.md)** | l'installation, le montage des écrans, le déploiement |
| **[configuration.md](docs/configuration.md)** | **les 45 réglages**, un tableau par bloc · la seule autorité |
| **[fonctionnalites.md](docs/fonctionnalites.md)** | une fiche par écran, les neuf commandes, l'instrumentation, les tunnels, le marketing, la vie privée |
| **[mise-a-jour.md](docs/mise-a-jour.md)** | monter de version, le contrat public, revenir en arrière |
| **[developpement.md](docs/developpement.md)** | travailler sur le paquet |
| **[CHANGELOG.md](CHANGELOG.md)** | l'historique, et ce que chaque version demande à l'intégrateur |

---

## Les écrans

**Neuf pour l'analytique** · vue d'ensemble, temps réel, visiteurs et détail
d'un visiteur, sessions et détail d'une session, événements, tunnels,
intégrations.

**Cinq pour le marketing** · synthèse, campagnes et détail, publicités et
détail. Trois d'entre eux écrivent · la liste des campagnes, le détail d'une
campagne et celui d'une publicité.

Ils se rendent dans **la coquille du paquet**, prête à l'emploi, ou **dans votre
propre habillage** si vous en nommez un. Les noms de routes ne bougent jamais ·
seules les adresses se règlent.

> **Ce que le paquet promet, ce sont les adresses de ses pages, jamais leur
> intérieur.** Un écran attend une page entière autour de lui.

---

## Mesurer autre chose qu'une page vue

**Les pages vues et les clics se capturent seuls**, sans que vous écriviez quoi
que ce soit — et seulement sur ce qui est réellement interactif. Pour nommer une
action ·

```blade
<button data-track-event="devis.demande">Demander un devis</button>
```

Pour les conversions qu'un clic ne peut pas confirmer — un paiement abouti, une
inscription validée ·

```php
use Falcon\Analytics\Facades\Analytics;

Analytics::record('commande.payee', value: $order->total);
```

**L'appel est différé** · il ne bloque jamais la réponse, et il ne lève jamais
rien vers l'appelant.

Les événements nommés se déclarent dans un fichier à vous, ce qui permet de les
marquer comme conversions et de les enchaîner en tunnels. Le détail est dans
[fonctionnalites.md](docs/fonctionnalites.md#les-événements-nommés-et-les-conversions).

---

## Les limites, dites franchement

- **Un seul domaine.** Un visiteur qui passe d'un site à l'autre est deux
  visiteurs · il n'y a pas de suivi inter-domaines, et il n'y en aura pas.
- **Les événements bruts s'effacent** au bout de 90 jours par défaut. Les
  agrégats, eux, restent · mais un événement de l'an dernier ne se rejoue pas.
- **Les robots sont enregistrés, puis écartés de tous les écrans.** Ce qui passe
  pour un navigateur inconnu, en revanche, compte comme une visite ordinaire.
- **La géolocalisation demande une clé MaxMind**, gratuite mais à demander, et
  une base à télécharger. Sans elle, les localités restent vides.
- **Search Console demande un client OAuth Google.** Sans lui, toute
  l'intégration reste cachée, ce qui est le bon comportement mais reste une
  fonction en moins.
- **L'attribution publicitaire se fait au rapport**, sur les paramètres d'URL
  capturés à l'arrivée. Une campagne créée après coup retrouve ses sessions ·
  une session sans paramètre reconnaissable ne s'attribue à personne.
- **Sans `@analyticsCollector`, rien n'est mesuré**, et rien ne le signale
  ailleurs que dans `analytics:check`. C'est la première chose à vérifier
  devant un tableau de bord vide.

---

## Licence

Propriétaire. Tous droits réservés.
