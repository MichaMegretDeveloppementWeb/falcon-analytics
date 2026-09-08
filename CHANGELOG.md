# Journal des versions

Les étapes notables de `falcon/analytics`. Les versions sont des étiquettes git ;
les étiquettes de correctif antérieures, par palier (v0.1.x), portent le détail
des pas.

Tant que le paquet est en développement, la dernière entrée reste ouverte : les
améliorations arrivent sur la version courante et son étiquette avance, pour que
les consommateurs gardent leur contrainte et les reçoivent quand même.

Les tirer demande `composer update falcon/analytics`, et rien d'autre — aucune
contrainte à monter, aucun cache à vider. Composer installe ce paquet depuis les
sources par son dépôt VCS, donc il relit l'étiquette et la suit jusqu'au nouveau
commit ; vérifié sur un projet épinglé à l'ancienne référence, qui a bougé sur une
simple mise à jour. À noter que `composer install` ne le fera pas : il rejoue le
verrou tel qu'il est.

## [1.1.0] — Branches parallèles de tunnel, puis tout ce qui a suivi (2026-08-07, toujours ouverte)

Le paquet est encore en développement, donc ce qui suit améliore la 1.1.0 plutôt
que d'ouvrir une version à part. L'étiquette `v1.1.0` avance avec, et un
`composer update` sur une contrainte `^1.1` reprend l'ensemble.

### Tunnels
- **Branches parallèles.** Une étape peut désormais se déclarer avec
  `anyOf: [...]`, qui liste des façons alternatives et nommées d'atteindre le
  même jalon — un formulaire ouvert depuis l'une ou l'autre de deux pages, une
  inscription menée par l'un ou l'autre de deux parcours. Posées en étapes
  consécutives, elles se lisaient « est passé par l'une, PUIS par l'autre » et
  rapportaient des zéros ; les branches se tiennent à la même profondeur, donc un
  visiteur avance une fois quelle que soit celle qu'il prend. Le rapport porte la
  portée de chaque branche, rendue sous l'étape, pour que l'écran réponde par où
  les visiteurs entrent réellement.
- `analytics:events:check` valide maintenant les événements des branches aussi.
- Entièrement compatible avec l'existant : les étapes `event:` et `route:` ne
  changent pas, et une étape sans branche rapporte un tableau `branches` vide.

### GeoIP (ajouté le 2026-08-10)
- **Le téléchargement ne meurt plus sur sa propre archive.**
  `analytics:geoip:download` dépliait avec `PharData`, qui lit tout le fichier en
  mémoire : une archive GeoLite2-City de 32 Mo dépassait les 128 Mo par défaut de
  PHP, et la commande échouait en pleine extraction, le téléchargement déjà
  dépensé. Elle décompresse désormais vers un fichier temporaire et parcourt le
  tar, donc la mémoire reste plate quel que soit le poids de l'archive.
- **`analytics:geoip:check`**, une nouvelle commande. La géolocalisation se
  dégrade en localité vide quoi qu'il arrive — pas de base, une base tronquée,
  une adresse privée — et les trois donnaient la même colonne blanche. La
  commande rapporte le chemin, la taille et la date de la base, l'adresse de
  développement configurée, et résout une adresse témoin en nommant l'état.
- **`GeoResolver::status()`** et l'énumération `GeoStatus` derrière. `locate()`
  se dégrade toujours en silence, ce qui est juste pour une requête ; `status()`
  est pour qui doit réparer.
- **Le tableau de bord le dit aussi.** Les écrans Sessions et Visiteurs portent
  un avertissement quand la base est absente ou illisible, avec quoi lancer.
  Muet quand il n'y a rien à signaler.

### Vue d'ensemble (ajouté le 2026-08-10)
- **La valeur rejoint son libellé.** Dix-neuf lignes réparties sur six sections
  posaient le libellé tout à gauche et le nombre tout à droite, sans rien pour
  traverser l'écart : les deux choses qu'un lecteur doit apparier étaient les
  deux plus éloignées de la ligne. Les lignes sont désormais des listes bornées
  dont la colonne des nombres commence après le plus long libellé, alignée d'une
  ligne à l'autre pour rester comparable, et l'espace restant tombe à droite où
  il ne coûte rien. Mesuré sur Audience : l'écart du libellé à la valeur passe
  d'environ quatre cents pixels à vingt-quatre.
- **Un nombre par ligne.** Chaque ligne portait une part et un compte, côte à
  côte, de même taille, sans séparation — l'œil devait donc décider lequel il
  lisait. Le compte reste, la part passe dans l'attribut `title`, et l'anneau à
  côté montre la proportion de toute façon.
- **Une entrée dans chaque carte.** Localités et Conversions ouvraient droit sur
  une liste, sans point d'entrée ; les deux mènent maintenant avec leur total en
  24 px. C'est le contraste de taille qui crée la hiérarchie — sans lui, aucune
  zone ne s'annonce.
- **De l'air entre les sections, moins à l'intérieur.** Les sections étaient
  espacées de 32 px quand les lignes d'une carte l'étaient de 28 : un groupe dont
  la séparation extérieure dépasse à peine son espacement intérieur ne se lit pas
  comme un groupe. Désormais 48 px et un filet.

## [1.0.0] — Version stable après l'audit complet d'avant la v1 (2026-07-21)

Audit sur six dimensions (architecture, gestion des erreurs, requêtes, sécurité,
documentation, qualité du code), chaque constat corrigé.

### Sécurité et robustesse
- Le middleware configuré des modules est désormais enregistré comme
  **middleware persistant Livewire** (une action ne peut plus se rejouer après
  une session révoquée) ; un avertissement est journalisé quand un hôte monte un
  module avec une liste de middleware vide.
- Les `props` ingérées sont plafonnées (clé 100, valeur 500 caractères, 8 Ko de
  JSON) ; les valeurs utm et d'agent utilisateur sont tronquées à la longueur de
  leur colonne ; la page des intégrations se dégrade comme tous les autres
  écrans ; les objectifs de publicité sont validés contre l'énumération.
- Collecteur : le rejet de `fetch` non traité est silencé, une branche morte
  retirée.

### Performance
- Nouveaux index : `sessions.country`, `events (route, occurred_at)` et
  `events (visitor_id, occurred_at)` ; la recherche par pays est bornée à la
  période ; l'évaluation des tunnels se fait désormais **en flux**, sans
  accumulation en mémoire ; les sessions marketing marquées sont plafonnées
  (20 000, journalisé en cas de troncature).

### Architecture
- La construction du rapport marketing sort du dépôt (766 → 150 lignes) vers
  `MarketingReportBuilder` ; la mécanique de tunnel, triplée, est unifiée dans
  `FunnelEventWalker` ; le formulaire de campagne est mutualisé dans
  `EditsCampaign` ; la logique de fusion des visiteurs passe dans le service
  `VisitorMerger` ; `SearchConsoleException` est typée ;
  `VisitorIdentityResolver` est renommée.
- 331 essais (1053 assertions), Pint et Larastan au vert.

## [0.3.5] — Suite de l'audit d'interface : les derniers modules alignés (2026-07-21)

### Modifié
- Le **« Top pubs » du tableau de bord marketing** abandonne la liste à barres et
  pastilles pour le motif de liste classée : la position, le nom de la publicité
  avec une sous-ligne « n sessions · campagne », et le nombre de conversions comme
  chiffre clé (la légende cryptique « conversions / sessions » disparaît).
- Les barres d'acquisition du **détail d'un visiteur** sont affinées pour
  rejoindre le style de la vue d'ensemble.
- Résultat de l'audit consigné : tous les autres écrans (les listes en tableaux,
  les tunnels en barres de perte, le parcours de session, les événements, les
  détails de campagne et de publicité) suivent déjà les principes de conception
  posés avec la refonte de la vue d'ensemble.

## [0.3.4] — Refonte de la vue d'ensemble : une présentation par module (2026-07-21)

### Modifié
- **Vue d'ensemble refondue** sur les principes de la référence : chaque section
  est maintenant une carte unique à colonnes séparées verticalement, et chaque
  module reçoit une présentation qui convient à sa donnée au lieu de la liste à
  barres et pastilles répétée — les Sources deviennent un anneau avec une légende
  d'écart, les Localités une simple liste classée de valeurs, les Pages gardent
  les seules barres (fines, sous le libellé), les Conversions montrent un point
  vert et leur part du total, les principaux événements un classement numéroté, et
  le module des requêtes Google adopte le motif de titre (un grand total de clics
  avec son écart, une sous-ligne « Position moy. · impressions » par requête et un
  écart par requête contre la période précédente — nouvelle lecture au dépôt).
- **Passe de formulation** : les phrases explicatives cèdent la place à une copie
  produit factuelle (« Dernières données Google : 19 juil. », un appel à l'action
  Search Console d'une ligne, un titre de section « Audience »).

## [0.3.3] — Synchronisation manuelle de la Search Console depuis le tableau de bord (2026-07-21)

### Ajouté
- **« Synchroniser maintenant »** sur l'écran des intégrations : lance exactement
  la même synchronisation que la commande nocturne, en ligne avec un état de
  chargement (aucun worker nulle part, par choix), et rapporte le nombre de lignes
  écrites. La logique passe dans un service partagé `SearchConsoleSynchronizer`,
  employé par la commande comme par la page.

## [0.3.2] — Documentation de la mise en place Google Cloud (2026-07-21)

### Documentation
- **README** : la mise en place Google Cloud pas à pas pour l'intégration Search
  Console (choix du projet, activation de l'API Search Console, écran de
  consentement et comptes de test, client web OAuth et adresse de retour,
  identifiants, propriété vérifiée), plus un tableau de dépannage de la connexion
  (redirect_uri_mismatch, access_denied, liste de propriétés vide, autorisation
  révoquée). Version de documentation seule.

## [0.3.1] — Correction : l'autoplanification sur un planificateur déclenché par HTTP (2026-07-21)

### Corrigé
- **Les commandes et les planifications s'enregistrent aussi hors console.** Les
  hébergements mutualisés déclenchent souvent le planificateur par un point HTTP
  qui appelle `Artisan::call('schedule:run')` ; la garde `runningInConsole()`
  précédente désenregistrait en silence toutes les commandes et toutes les tâches
  planifiées du paquet dans ce cas, donc `analytics:sweep`, `analytics:prune`, le
  rafraîchissement mensuel de GeoIP et la synchronisation Search Console ne
  tournaient jamais. Les planifications sont désormais liées paresseusement
  (`callAfterResolving(Schedule::class)`), donc une requête HTTP ordinaire ne paie
  toujours rien.

## [0.3.0] — Google Search Console : les vraies requêtes organiques (2026-07-20)

Les mots que les visiteurs tapent réellement dans Google, qu'aucune mesure
propriétaire ne peut voir, remontés sur la vue d'ensemble par la Search Console
du site.

### Ajouté
- **Écran des intégrations** (`{nom}.integrations`) : l'administration connecte
  sa Search Console par OAuth Google (portée en lecture seule, état anti-CSRF,
  client HTTP léger — pas de google/apiclient), choisit la **propriété** vérifiée
  à rattacher, et peut se déconnecter (confirmé par modale, jeton révoqué au
  mieux). Tant que l'hôte ne fournit aucun identifiant OAuth
  (`ANALYTICS_GSC_CLIENT_ID` et `ANALYTICS_GSC_CLIENT_SECRET`), toute la
  fonctionnalité reste cachée. Les jetons sont stockés **chiffrés** ; un accès
  révoqué signale la connexion et propose de la refaire.
- **Synchronisation quotidienne** (`analytics:search-console:sync`, planifiée à
  05:00, inerte sans connexion rattachée) : des lectures paginées de Search
  Analytics vers le cache local `falcon_analytics_search_queries` — un rattrapage
  d'environ seize mois à la première exécution, puis une relecture des trois
  derniers jours (les données de la Search Console se fixent tard), insérées ou
  mises à jour sur la date et la requête. Le tableau de bord n'appelle jamais
  l'API au moment de l'affichage.
- **Section « Clics par recherches Google » de la vue d'ensemble** : les
  principales requêtes de la période par clics, avec impressions, taux de clic et
  position moyenne pondérée par les impressions, une mention de fraîcheur
  (« Données Google jusqu'au … »), et une carte d'appel à l'action tant qu'aucune
  connexion n'est rattachée. Distincte par nature du terme de campagne (utm_term).
- Deux migrations (`falcon_analytics_search_console`,
  `falcon_analytics_search_queries`), une section de README, 36 essais (HTTP
  simulé).

## [0.2.5] — Portabilité : un README exhaustif et une coquille autonome complète (2026-07-20)

Le paquet s'installe désormais sur n'importe quel projet Laravel depuis son seul
README, prouvé par une installation sur un projet vierge.

### Ajouté
- **README exhaustif** : l'installation (les dépôts VCS, y compris celui du kit
  qui vient par transitivité, la commande d'installation, les assets du système
  de conception, les sources Tailwind), le branchement de l'identité et du
  consentement, le comportement du collecteur, la référence complète de
  l'instrumentation, les événements nommés et les conversions, les événements
  émis par le serveur, les tunnels, le module marketing, le temps réel, les
  commandes et l'autoplanification, la géolocalisation (MaxMind, n'importe quel
  MMDB de niveau ville, et l'adresse de développement), la vie privée et le RGPD,
  une référence de configuration complète et le modèle de données.

### Corrigé
- **La navigation de la coquille autonome** : la barre latérale du gabarit du
  paquet liste maintenant tous les écrans — les six pages d'analytique (Vue
  d'ensemble, Temps réel, Visiteurs, Sessions, Événements, Tunnels) et le module
  marketing (Vue d'ensemble, Campagnes, Pubs) en deux groupes nommés ; elle
  s'arrêtait auparavant à quatre liens, cachant le temps réel, les visiteurs et
  le marketing à un hôte qui emploie la coquille par défaut.

### Validé
- **Installation à blanc** sur un projet Laravel 13 neuf (sqlite) : le
  `composer require` depuis les dépôts VCS, `analytics:install`,
  `ui-kit:install` avec le `@source` Tailwind et la compilation, le collecteur
  (pages vues, clic nommé ingéré), les neuf écrans d'analytique et de marketing
  rendus avec leurs styles, le chargement des événements déclarés et d'un tunnel,
  `analytics:events:scan` et `analytics:events:check`, et la dégradation sans
  géolocalisation (la mention « non localisé » sur la carte du temps réel).

## [0.2.4] — Écran temps réel, phase B : carte du monde et refonte (2026-07-20)

L'écran du temps réel reçoit sa carte des connexions et une mise en page inspirée
de la référence du domaine, toujours sans aucun service externe.

### Ajouté
- **Carte du monde** : un fond SVG embarqué dans le paquet, produit hors ligne
  depuis Natural Earth 110m (domaine public), projection de Miller, frontières
  tracées à largeur constante (`vector-effect: non-scaling-stroke`). Aucune
  tuile, aucune bibliothèque cartographique, aucune requête externe : portable par
  construction. Vue mondiale fixe. Les marqueurs agrègent les sessions par ville,
  se régénèrent sur place à chaque tour (`wire:ignore` et une charge utile `map`)
  et sont dimensionnés en **pixels d'écran**, donc ils restent lisibles sur
  téléphone ; les localisations en ligne pulsent, les infobulles empruntent le
  système délégué du tableau de bord, les sessions non localisées reçoivent une
  mention discrète.
- **Une paire d'onglets au-dessus de la carte** (fenêtre récente / en ligne
  maintenant, de largeurs égales) qui filtre les marqueurs et la liste des pays
  par un événement de fenêtre.
- **Liste des pays** à côté de la carte, dérivée des points de la carte (aucune
  requête supplémentaire), avec les totaux par onglet.
- **Liste des visiteurs récents** : une ligne par visiteur sur les dernières
  vingt-quatre heures (la session la plus récente, l'icône de l'appareil, le
  point « en ligne », un lien vers le détail de la session).
- **Fil d'activité en direct** repris : le type d'événement mène la ligne (les
  conversions ressortent), le nom du visiteur et l'heure suivent.
- **Géolocalisation en développement** : `analytics.geoip.dev_ip` substitue une
  adresse publique quand celle de la requête est privée ou réservée, pour que le
  développement local ait des sessions localisées ; inerte en production, par
  construction.

### Modifié
- **Design aligné sur la référence** : les tons d'encre, secondaire et discret,
  l'accent bleu et le vert du « en ligne », des cartes plates à 8 px, des centres
  d'anneau qui montrent le **nombre de catégories** (« 3 sources », « 2 types »),
  Sources et Appareils côte à côte, une section « Pages vues » pleine largeur
  avec des barres de proportion (empilées sous l'URL sur petit écran), et le
  graphique par minute gardé en bonus sous la carte.
- **Le « en ligne maintenant » compte les visiteurs distincts** et non les
  sessions, donc il ne peut jamais dépasser le nombre de visiteurs de la fenêtre.

## [0.2.3] — Écran temps réel, phase A (2026-07-17)

Qui est en ligne maintenant, et ce qui s'est passé dans la fenêtre récente, sur
n'importe quel hébergement.

### Ajouté
- **Page temps réel** (`analytics.realtime`) : les indicateurs du « en ligne
  maintenant » et de la fenêtre récente, un graphique des pages vues par minute,
  des anneaux d'appareils et de sources avec leurs légendes, les pages
  principales, et un fil d'activité borné et défilant qui nomme les visiteurs par
  l'attribution rétroactive (les conversions ressortent). Aucun filtre, par choix.
- **Rafraîchissement** : un simple sondage Livewire (`wire:poll.visible`, 10 s par
  défaut), suspendu tant que l'onglet est caché ; aucun worker, aucun websocket,
  aucun service externe, donc cela tourne sur n'importe quel hébergement. Bloc
  `analytics.realtime` réglable (sondage, fenêtre du « en ligne », fenêtre
  récente, borne du fil).
- **Graphiques en direct** : les composants `live-line` et `live-donut` se
  tiennent sous `wire:ignore` et se mettent à jour sur place depuis l'événement de
  tour de la page, pour qu'un sondage ne détruise ni ne réanime jamais un
  graphique.
- **Budget** : tout le tour se rend en onze requêtes bornées et indexées au plus,
  figé par un essai de budget.
- **Classe de support `SourceLabel`** : les libellés des canaux d'acquisition
  sortent du composant Blade de source vers une source unique partagée.

## [0.2.2] — Fusion des identités et annuaire des visiteurs (2026-07-17)

Une personne connue, un profil de visiteur, et l'écran des visiteurs devient un
annuaire de tous les temps.

### Ajouté et modifié
- **Fusion des identités** : quand un navigateur devient identifié et que le sujet
  possède déjà un profil, les deux se replient l'un dans l'autre (le plus ancien
  survit) ; la ligne repliée devient un alias (`merged_into_id`) dont l'uuid
  continue de router les balises vers le profil canonique. Les sessions
  enregistrent leur navigateur physique (`browser_key`), donc deux appareils d'une
  même personne qui naviguent en même temps donnent bien deux sessions, et une
  session ouverte survit à la fusion. Une connexion sur un navigateur partagé mène
  au profil de la personne connectée ; le navigateur garde son propriétaire. Les
  sessions identifiées orphelines rejoignent le profil canonique de leur sujet.
- **Migration** : ajoute les deux colonnes, remplit `browser_key` et consolide
  chaque profil dupliqué préexistant de l'hôte (aucune commande résiduelle).
- **RGPD** : effacer un profil efface aussi ses alias fusionnés. L'URL de détail
  d'un alias redirige vers le profil canonique.
- **Écran des visiteurs** : la liste est désormais l'annuaire de tous les temps de
  chaque profil réel (alias et visiteurs purement robots exclus, comptes de
  sessions de tous les temps) ; le sélecteur de période appartient visuellement au
  bloc de titre « Activité » et ne pilote que lui. Le filtre par rôle reste
  global.

## [0.2.1] — Nommage rétroactif des sessions (2026-07-17)

Une session anonyme d'un visiteur identifié affiche désormais le nom de la
personne.

### Ajouté et modifié
- **`SessionSubjectAttributor`** : une session s'affiche sous son propre sujet
  quand elle est authentifiée, sinon sous le sujet rattaché à son visiteur, avec
  la mention « Non connecté » (sous-ligne dans la liste, badge à infobulle dans le
  détail). Le repli est retenu quand les sessions identifiées du visiteur pointent
  vers plusieurs sujets distincts (navigateur partagé).
- **Détail d'un visiteur** : les sessions identifiées portent un badge
  « Connecté ».
- **Recherche** : un nom complet réparti sur plusieurs colonnes correspond
  désormais (« René Roy » — chaque mot doit correspondre à l'une des colonnes de
  nom), et une correspondance de sujet fait aussi remonter les sessions anonymes
  nommées par leur visiteur.

## [0.2.0] — Audit et durcissement (2026-07-08)

Un audit complet du paquet (architecture et SOLID, gestion des erreurs,
optimisation des requêtes, Livewire et validation, nommage et commentaires,
migrations, essais et code mort) contre les règles d'implémentation du projet,
suivi des corrections de conformité. Consolide les v0.1.58 à v0.1.65. Chaque pas
a gardé la suite entière, Pint et Larastan au vert ; les écritures marketing et
les tableaux de bord ont été revalidés en direct.

### Corrigé et modifié
- **Hygiène** (v0.1.58) : code mort retiré (MarketingTrendChart) ; `TrendChart`
  rendu non réactif avec un remontage par `wire:key` ; index manquant
  `visitors.first_seen_at` ajouté ; `Collector::render()` gardé pour qu'il se
  dégrade en chaîne vide au lieu de casser la page de l'hôte ; journal de
  l'effacement RGPD monté en `notice` ; tirets cadratins retirés et espaces
  insécables manquantes ajoutées dans les textes d'interface français.
- **Résilience des widgets** (v0.1.59) : les treize widgets différés `#[Lazy]` se
  dégradent en état d'erreur en ligne au lieu d'une 500 quand leur lecture échoue
  (`GuardsWidgetRead`).
- **Couche d'écriture** (v0.1.60) : les écritures marketing sont extraites en
  Actions (SaveCampaign, DeleteCampaign, SaveAd, DeleteAd) avec try/catch et toast
  d'erreur, un `saveAd` atomique, une suppression de publicité bornée à sa
  campagne, et des `messages()` de validation en français.
- **Attribution et déduplication** (v0.1.61) : `AttributionResolver` extraite ;
  `FunnelStep::matches()` devient la source unique de la correspondance d'étape
  (le doublon disparaît de `FunnelEvaluator` et du dépôt marketing) ; l'essai
  manquant de conversion sur objectif de tunnel ajouté.
- **Optimisation des requêtes** (v0.1.62) : les comptes de conversions par
  objectif des pages de détail sont regroupés en une seule requête.
- **Couche de calcul** (v0.1.63) : `MarketingMetricsCalculator` extraite, pour que
  les widgets marketing ne calculent plus les taux et les tendances sur place.
- **Couche de données** (v0.1.64) : `SubjectReadRepository` extraite de
  `SubjectResolver`.
- **Donnée morte** (v0.1.65) : le champ `AdObjective.value`, inutilisé, est
  supprimé.
