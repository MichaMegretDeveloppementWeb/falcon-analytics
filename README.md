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

PHP 8.5 avec l'extension **intl**, Laravel 13, Livewire 4.2, et une base
**MySQL ou MariaDB** · les tableaux de bord posent du SQL propre à ce moteur, et
l'installation refuse de commencer sur un autre.
**Node, non.** `falcon/ui-kit` vient avec le paquet.

Les visites sont mesurées sur **tout navigateur sorti depuis 2018** · Chrome 39,
Firefox 31, Safari 11.1, Edge 14 ou plus récents. Sur un plus ancien, la page
s'affiche normalement, sans être mesurée.

Il faut aussi que `schedule:run` tourne chaque minute · c'est ce qui ferme les
sessions inactives, résume les jours clos et efface ce qui a dépassé la
conservation. **Si votre ordonnanceur s'arrête, rien n'est perdu** · l'effacement
refuse un jour non résumé, et ouvrir un écran rattrape le retard.

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
douze contrôles sur des défauts qui échouent tous en silence.

---

## La documentation

| | |
|---|---|
| **[installation.md](docs/installation.md)** | l'installation, le montage des écrans, le déploiement |
| **[configuration.md](docs/configuration.md)** | **les 37 réglages**, un tableau par bloc · la seule autorité |
| **[fonctionnalites.md](docs/fonctionnalites.md)** | une fiche par écran, les dix commandes, l'instrumentation, les tunnels, le marketing, la vie privée |
| **[mise-a-jour.md](docs/mise-a-jour.md)** | monter de version, le contrat public, revenir en arrière |
| **[reprise-du-schema.md](docs/reprise-du-schema.md)** | **temporaire** · la montée d'un projet installé avant la reprise du schéma, en local puis en production |
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

Analytics::record('commande.payee', value: 10);
```

**`value` est un score, en points entiers**, jamais un montant · le tableau de
bord additionne des points. **L'appel est différé** · il ne bloque jamais la
réponse, et il ne lève jamais rien vers l'appelant.

Les événements nommés se déclarent dans un fichier à vous, ce qui permet de les
marquer comme conversions et de les enchaîner en tunnels. Le détail est dans
[fonctionnalites.md](docs/fonctionnalites.md#les-événements-nommés-et-les-conversions).

---

## Les limites, dites franchement

- **Un seul domaine.** Un visiteur qui passe d'un site à l'autre est deux
  visiteurs · il n'y a pas de suivi inter-domaines, et il n'y en aura pas.
- **Le pas à pas d'une session ne se consulte que 90 jours** par défaut. Au-delà,
  les pages vues et les clics anonymes sont effacés — **et aucun chiffre d'aucun
  écran ne bouge** · ils ont été comptés d'avance, et tout ce qui porte un nom
  est gardé pour toujours. Vous pouvez demander deux ans sans rien fausser.
- **Un tunnel ou un événement déclaré aujourd'hui ne dit rien du passé déjà
  effacé.** La matière n'existe plus ; l'historique de cette mesure commence le
  jour où vous la déclarez. C'est vrai de tous les outils du genre.
- **Les robots sont enregistrés, puis écartés de tous les écrans.** Ce qui passe
  pour un navigateur inconnu, en revanche, compte comme une visite ordinaire.
- **La géolocalisation demande une clé MaxMind**, gratuite mais à demander, et
  une base à télécharger. Sans elle, les localités restent vides.
- **Search Console demande un client OAuth Google.** Sans lui, la fonction ne se
  propose pas · le lien disparaît du menu et la connexion refuse. C'est le bon
  comportement, mais ça reste une fonction en moins.
- **L'attribution publicitaire se fait au rapport**, sur les paramètres d'URL
  capturés à l'arrivée. Une campagne créée après coup retrouve ses sessions ·
  une session sans paramètre reconnaissable ne s'attribue à personne.
- **Sans `@analyticsCollector`, aucune page vue et aucun clic ne sont mesurés**,
  et rien ne le signale ailleurs que dans `analytics:check`. C'est la première
  chose à vérifier devant un tableau de bord vide. Les événements que vous
  émettez depuis le serveur avec `Analytics::record()`, eux, n'en dépendent pas.
- **Derrière un proxy, déclarez vos proxies de confiance.** Sans ça toutes les
  visites portent la même adresse, donc un seul pays — et surtout **une seule
  limite de débit pour tout le site**, qui finit par refuser les envois sans que
  rien ne le dise.

---

## Licence

Propriétaire. Tous droits réservés.
