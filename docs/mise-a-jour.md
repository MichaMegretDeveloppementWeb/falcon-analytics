# Monter de version

---

## ⚠️ Vous montez un projet installé avant la reprise du schéma ?

**Alors la procédure ci-dessous ne suffit pas**, et `migrate` ne rattrapera
rien · les vingt-deux migrations qui construisaient le schéma par touches
successives sont devenues dix, une par table. **Les tables du paquet se
suppriment et se rejouent**, et ce qu'il avait mesuré est perdu.

> **Tout est dans [`reprise-du-schema.md`](reprise-du-schema.md)** · ce qui
> change, ce que vous perdez, et la procédure **en local puis en production**,
> qui ne se jouent pas pareil.

**Pour une installation neuve, il n'y a rien de tout cela à faire** · la
procédure ordinaire ci-dessous suffit, et suffira toujours.

---

## La procédure

```bash
composer update falcon/analytics
php artisan migrate
php artisan vendor:publish --tag=laravel-assets --force
php artisan view:cache
php artisan analytics:check
```

**La republication n'est pas optionnelle.** Le kit compare la copie que vous
servez au fichier que le paquet livre, et **refuse de servir une copie
périmée** · vos pages lèveraient une erreur nommant le fichier et la commande.

> **`view:cache` non plus, et personne ne vous le dira.** Une vue Blade déjà
> compilée peut survivre à la mise à jour qui remplace sa source. Le balisage
> d'hier tourne alors avec le script d'aujourd'hui, et les deux ne se
> reconnaissent plus · mesuré sur une installation à nu, un conteneur écoutait
> encore l'ancien nom d'un événement, si bien que rien de ce qu'il devait
> montrer ne paraissait. La page s'affichait, et rien ne levait.

**`migrate` avant la republication**, et pas l'inverse · si une montée ajoute
une colonne, un écran qui la lit doit la trouver. Entre les deux commandes, le
pire qui arrive est une feuille d'hier ; dans l'autre ordre, c'est une requête
sur une colonne absente.

`analytics:check` remplace le tour de l'application à la main · seize contrôles,
tous sur des défauts qui échouent en silence.

> **La configuration publiée n'est jamais écrasée.** Une clé ajoutée par une
> nouvelle version prend son défaut sans que vous touchiez à rien, et un essai
> le garde · une configuration publiée il y a un an continue de fonctionner.
> Vous ne republiez `config/analytics.php` que si vous voulez relire les
> commentaires à jour, et alors `analytics:install --force` le fait.

---

## Ce qu'une montée demande, selon ce qui change

| Ce qui a changé | Ce que vous avez à faire |
|---|---|
| une correction, un écran ajouté | republier · rien d'autre |
| **une migration** | `migrate`, puis republier |
| **la valeur d'un jeton de style** | republier · **aucun build de votre côté**, la valeur est résolue par le navigateur |
| une clé de configuration ajoutée | rien · elle prend son défaut |
| une rupture du contrat public | lire le journal · une version majeure le dit |

**Votre `npm run build` n'entre jamais là-dedans** · le paquet compile ses
propres écrans, et votre compilation ne lit pas nos vues. Elle ne concerne que
votre propre habillage, et donc seulement si vous avez nommé un gabarit à vous.

> **Les écrans ne se publient pas**, et c'est ce qui les garde à jour · une
> montée les renouvelle tous, sans rien vous demander. Pour habiller un écran en
> particulier, votre gabarit suffit · voir
> [installation.md](installation.md#un-écran-en-particulier).

---

## Ce qui compte comme rupture

Ces éléments forment **le contrat public**. Leur retrait ou leur modification
incompatible impose une version majeure ·

- **les huit noms de tables** `falcon_analytics_*` et leurs colonnes · un nom de
  table est public, qu'on l'ait voulu ou non · un hôte finit par écrire une
  requête dessus, un rapport, un export, un nettoyage ;
- **les seize classes publiques** · la façade `Analytics` et le gestionnaire
  derrière elle, le fournisseur de services, les huit modèles, les deux
  énumérations lues sur un modèle, `TrackedEvent`, `Funnel` et `FunnelBranch` ;
- **les noms de routes** · ils ne bougent jamais, seules les adresses se
  règlent, et `TheRouteNamesDoNotMoveTest` refuse qu'on en change un ;
- **le nom d'une clé de configuration**, ou son défaut si l'effet change ;
- **la directive** `@analyticsCollector` ;
- **le nom d'un jeton de style**, et le sélecteur qui permet de le surcharger ;
- **une commande**, ou une de ses options ;
- **le point de collecte** et la forme de ce qu'il accepte.

**Restent dans une version mineure** · un écran ajouté, une clé ajoutée avec un
défaut, un jeton ajouté, une colonne ajoutée, une correction qui ne change
aucune de ces signatures.

Ce qui n'est pas dans cette liste et pas décrit dans
[configuration.md](configuration.md) **n'est pas public**, et peut changer sans
préavis. Concrètement · les écrans, ce qui les alimente, ce qui lit et écrit, ce
que font les commandes — **146 classes sur 162**, et chacune le dit dans son
propre code.

> **Les écrans méritent un mot.** Le fournisseur les annonce à Laravel sous
> `analytics::…`, ce qui vous permettrait techniquement d'en poser un dans une
> page à vous. **Ce n'en est pas une invitation** · un écran attend une page
> entière autour de lui. Ce que ce paquet promet, ce sont les adresses de ses
> pages, jamais leur intérieur.

---

## Choisir sa contrainte

```json
"falcon/analytics": "^1.0"
```

**C'est la bonne forme.** Elle vaut aussi pour `falcon/ui-kit`, que vous devez
déclarer dans vos `repositories` même si vous ne l'installez pas vous-même.

> **Ne verrouillez pas sur une mineure sans raison.** Deux paquets de la suite
> figés sur des mineures différentes deviennent impossibles à installer
> ensemble. Un verrouillage plus strict reste défendable pour une dépendance
> assumée à des détails internes, avec son coût de maintenance écrit noir sur
> blanc.

---

## Revenir en arrière

```bash
composer require falcon/analytics:1.2.0
php artisan vendor:publish --tag=laravel-assets --force
php artisan analytics:check
```

**La republication est ce qui compte** · sans elle vous serviriez les fichiers
de la version que vous venez de quitter, et le paquet lèverait une erreur au
premier affichage plutôt que de les servir en silence.

### Le schéma, qui est la vraie question

**Ne redescendez pas les migrations pour redescendre de version.** Toutes sont
réversibles, et c'est précisément ce qui rend le geste dangereux · `down()` fait
ce qu'on lui demande, et une colonne retirée emporte ce qu'elle contenait.

La bonne façon de voir les choses · **une version antérieure lit très bien un
schéma en avance sur elle**. Une colonne qu'elle ignore ne la dérange pas. Vous
redescendez le code, vous laissez la base où elle est, et vous n'avez rien
perdu.

`migrate:rollback` ne se justifie que si vous abandonnez le paquet, ou si la
montée a échoué au milieu — et dans ce cas, sauvegardez d'abord.

Le retour est donc **sans perte tant que vous êtes dans la même majeure**. D'une
majeure à la précédente, lisez ce que la majeure avait demandé · un retour
demande de le défaire, et le journal des versions est ce qui le dit.

---

## Ce qu'il faut vérifier après

- `php artisan analytics:check` passe sans point bloquant ;
- un écran s'affiche, en clair **et** en sombre ;
- **une visite est mesurée** · chargez une page publique portant la directive,
  et regardez l'écran temps réel. C'est le seul contrôle qui prouve que la
  chaîne entière tient, du navigateur à la base ;
- vos tâches planifiées tournent toujours · `php artisan schedule:list`.

> **Un tableau de bord vide après une montée ne veut pas dire « personne n'est
> venu ».** C'est le symptôme commun de tout ce qui échoue en silence ici, et
> c'est pour cela qu'on vérifie une visite plutôt que de regarder des chiffres.
