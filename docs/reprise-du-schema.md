# Reprendre le schéma · la montée qui remet les compteurs à zéro

> **⚠️ Ce fichier est temporaire.** Il ne concerne que les projets installés
> **avant** la reprise du schéma. Une installation neuve n'a rien à en faire :
> elle obtient directement la bonne structure.
>
> **Quand le supprimer** · le jour où tous les projets existants sont montés.
> Il n'y aura alors plus personne à qui il s'adresse. Retirez aussi le renvoi
> qui y mène depuis `mise-a-jour.md`.

---

## À qui ça s'adresse

À qui monte `falcon/analytics` **sur un projet qui l'utilisait déjà**. La
procédure se joue **deux fois** · une fois en local, sur le projet en
développement, et une fois en production. Les deux ne se déroulent pas
pareil, et cette page dit en quoi.

---

## Ce qui a changé, et pourquoi ça ne se rattrape pas

Le schéma a été repris à neuf. Les vingt-deux migrations qui l'avaient
construit par touches successives sont devenues **dix, une par table**, chacune
décrivant son état final.

**Une installation antérieure ne peut donc pas être rattrapée par une migration
de plus** · son journal porte des noms de migrations qui n'existent plus, et ses
tables ont une histoire que le paquet ne raconte plus. Elles se suppriment et
se rejouent.

### Ce que la reprise corrige, et qui n'était pas rattrapable autrement

**Les instants quittent un type que le moteur convertit** contre le fuseau du
serveur. Jusqu'ici, le disque portait une heure décalée du décalage de votre
serveur — deux heures en été, une en hiver — que **tout autre lecteur voyait
fausse** · une sauvegarde restaurée ailleurs, une réplique, un outil
décisionnel. Vos écrans, eux, disaient juste, la conversion inverse tournant à
la lecture. C'est ce qui rendait le défaut invisible.

**Et l'heure que la pendule locale saute** au passage à l'heure d'été était
**refusée par la base** · pendant cette heure-là, une fois par an, le paquet
n'enregistrait rien du tout.

### Ce que vous perdez, ce que vous gardez

| Perdu | Gardé |
|---|---|
| visiteurs, sessions, évènements | **tout le reste de votre base** |
| résumés quotidiens, archives | votre configuration publiée, `config/analytics.php` |
| campagnes, publicités, objectifs | vos propres migrations, et leur journal |
| la connexion à la Search Console | vos vues, vos surcharges, vos assets |

> **La connexion à la Search Console est à refaire** · son jeton vit dans une
> table du paquet. Comptez le reconnecter après la montée.

---

## Ce qu'il faut changer dans votre code, avant

**Un score s'écrit désormais en points entiers.** Partout où votre projet en
déclare un · `value: 80.0` devient `value: 80`.

| Où le chercher | Ce qui arrive sinon |
|---|---|
| `app/Analytics/events.php` · chaque `TrackedEvent::define(…, value: …)` | si le fichier déclare `strict_types`, il cesse de se charger à la première valeur décimale · les événements qui suivent disparaissent des écrans, **sans autre trace qu'une ligne du journal** |
| `app/Analytics/funnels.php` · chaque `->step(…, value: …)` | la même chose pour les tunnels |
| vos appels `Analytics::record(…, value: …)` | par la façade, un nombre décimal perd ce qui suit la virgule, avec un avertissement de PHP |
| vos attributs `data-track-value` | un nombre décimal est ignoré · l'évènement part, sans son score |

**Le site public n'est pas touché** · ces deux fichiers ne se lisent que sur
les écrans d'analytique et dans les tâches d'entretien. **Et rien ne lève**,
d'où l'importance de chercher · une conversion absente de l'écran des
événements est le seul signe visible.

> **Un score n'est pas un montant.** Si vous passiez un prix à `value`, c'est
> l'occasion de le retirer · le tableau de bord additionne des points et les
> affiche en « pts ».

## Deux défauts de vie privée qui changent

- **L'adresse IP est désormais tronquée par défaut.** Si votre copie publiée de
  `config/analytics.php` écrit `'anonymize_ip' => false`, elle continue de la
  garder entière · c'est votre choix qui vaut.
- **Le cookie d'un visiteur qui a consenti dure treize mois**, au lieu de deux
  ans. Ceux déjà posés dans les navigateurs gardent leur échéance · le paquet ne
  réécrit pas un cookie valide.

---

## En local, sur le projet en développement

**C'est ici qu'on regarde ce que la commande fait**, avant de le faire en
production.

```bash
composer update falcon/analytics
php artisan analytics:refresh
php artisan vendor:publish --tag=laravel-assets --force
php artisan view:cache
php artisan analytics:check
```

**Lancez `analytics:refresh` sans `--force`.** Elle affiche le tableau des
tables qu'elle vise, avec leur nombre de lignes, et attend un `yes`. **C'est le
moment de lire ce tableau** · vous y verrez exactement ce que la production
recevra. Un `no` ne touche à rien.

> **Le moteur de stockage de votre base locale compte.** Les index composites du
> paquet dépassent la limite de clé de MyISAM, que certaines installations
> locales posent encore par défaut · les migrations tombent alors sur un index,
> avec un message du moteur. Si c'est votre cas, elles tomberont **ici** plutôt
> qu'en production, ce qui est la bonne place. Le paquet lui-même n'exige rien
> d'autre que **MySQL ou MariaDB**, ce que `analytics:check` dit au point
> « Base de données ».

**Ce que vous devez voir ensuite** · vos écrans d'analytique s'ouvrent, vides.
C'est le résultat attendu.

---

## En production

**Trois choses de plus qu'en local**, et elles comptent.

### 1 · Une sauvegarde, avant

```bash
mysqldump -u … -p … > sauvegarde-avant-reprise.sql
```

La commande ne vise que les tables du paquet, et un essai le garde. **La
sauvegarde ne protège pas de la commande, elle protège de vous** · se tromper de
projet ou d'environnement est la seule erreur qu'elle rattrape.

### 2 · Faire taire le collecteur pendant l'opération

Entre la suppression des tables et la fin des migrations, le collecteur écrit
dans des tables qui n'existent pas. **Le visiteur ne voit rien** — la balise
part sans attendre de réponse — mais votre journal se remplit d'erreurs, et les
tâches planifiées (`analytics:sweep` toutes les cinq minutes) échouent bruyamment.

```bash
# dans .env, le temps de l'opération
ANALYTICS_ENABLED=false
php artisan config:cache
```

**Et remettez-le à `true` à la fin**, avec un `config:cache` de plus.
`analytics:check` refuse l'installation tant qu'il est à `false`, donc vous ne
pouvez pas l'oublier sans le voir.

> **Le mode maintenance de Laravel ne suffit pas** · il arrête les requêtes
> web, pas l'ordonnanceur.

### 3 · La commande, sans confirmation

```bash
composer update falcon/analytics --no-dev --optimize-autoloader
php artisan analytics:refresh --force
php artisan vendor:publish --tag=laravel-assets --force
php artisan config:cache
php artisan view:cache
php artisan analytics:check
```

**`--force` saute la confirmation**, parce qu'un déploiement n'a personne pour
répondre. Ne l'écrivez nulle part ailleurs.

> **La republication n'est pas optionnelle** · le kit compare la copie que vous
> servez au fichier que le paquet livre et **refuse de servir une copie
> périmée**. Vos pages lèveraient une erreur nommant le fichier et la commande.

---

## Ce que la commande garantit, et comment c'est prouvé

**Elle ne peut pas toucher une table qui n'est pas du paquet.** Elle ne lit pas
une liste écrite à la main : elle demande au moteur la liste des tables **de la
base de la connexion**, et ne garde que celles dont le nom commence par
`falcon_analytics_`.

**Elle n'oublie que ses propres migrations.** Les lignes qu'elle retire du
journal sont celles dont le nom correspond aux fichiers que le paquet livre
réellement — pas un motif qui pourrait attraper une des vôtres.

**Quatre essais le tiennent**, et ils sont dans la suite du paquet ·

| L'essai | Ce qu'il prouve |
|---|---|
| une table étrangère posée à côté | elle sort intacte, **avec ses lignes** |
| le schéma après la commande | exactement celui d'une installation neuve |
| le journal des migrations | il ne perd que les lignes du paquet |
| un refus à la confirmation | ne supprime rien |

---

## Le contrôle après

```bash
php artisan analytics:check
```

Seize contrôles, tous sur des défauts qui échouent en silence. **Il doit dire
« Installation valide ».** S'il rend un point bloquant, il nomme ce qui manque
et la commande qui le pose.

**Puis ouvrez un écran d'analytique** · il s'affiche, vide, et se remplit à la
première visite.

---

## Si quelque chose tourne mal

**La commande s'est arrêtée au milieu** · rejouez-la. Elle supprime ce qui
reste et rejoue les migrations · elle ne suppose rien de l'état où elle vous
trouve.

**Les migrations refusent de jouer** · lisez `analytics:check` au point « Base
de données ». Il dit si la connexion est bien en MySQL ou MariaDB, la seule chose
que le paquet demande. Si elle l'est, c'est le message du moteur qui nomme ce qui
a refusé · le plus souvent un index, sur une base locale en MyISAM, comme dit
plus haut.

**Vous avez lancé la commande sur le mauvais projet** · restaurez la sauvegarde.
C'est précisément pour ça qu'elle est demandée.
