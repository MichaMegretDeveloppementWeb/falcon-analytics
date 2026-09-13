# Configurer Falcon Analytics

**Cette page fait autorité.** Le `README.md` montre le minimum pour démarrer ;
tout ce qui est réglable est ici, et rien n'est ici qui ne soit réglable.

Trente-six clés, en douze blocs. Le paquet **fonctionne sans en toucher une
seule** · les valeurs ci-dessous sont celles qui s'appliquent tant que vous ne
dites rien, et un essai le tient.

> **Trente-six réglages, et non quarante-cinq.** Le fichier porte neuf noms de
> plus — `identity`, `admin`, `geoip`… — mais ce sont des groupes, pas des
> valeurs · on ne règle pas `identity`, on règle ce qu'il contient.

---

## Trois choses vraies pour toutes les clés

On ne les répète donc pas dans les tableaux, et chaque exception est signalée
là où elle est ·

1. **La portée est le paquet.** Aucune de ces clés ne change le comportement de
   votre application ailleurs que dans Falcon Analytics.
2. **Rien n'est à relancer.** Une valeur changée s'applique au prochain
   affichage. La seule exception est le cache de configuration de Laravel · si
   vous employez `config:cache`, relancez-le, comme pour n'importe quel réglage.
3. **Toutes existent depuis la `1.0.0`**, la première version du paquet.

---

## Publier le fichier, ou ne pas le publier

```bash
php artisan vendor:publish --tag=analytics-config
```

**Ce n'est pas obligatoire.** Sans publication, les valeurs ci-dessous
s'appliquent. Publiez le jour où vous voulez en changer une.

> **Une copie publiée vieillit, et le paquet le sait.** Les clés qu'une version
> ultérieure ajoute sont complétées en mémoire, pour que votre copie d'hier ne
> vous prive pas d'une fonction d'aujourd'hui. Ce que vous avez choisi n'est
> jamais réécrit, et une liste que vous avez vidée reste vide · c'est une
> réponse, pas un oubli.

---

## L'interrupteur général

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `enabled` | booléen | `true` · `ANALYTICS_ENABLED` | À `false`, aucun événement n'est reçu et le collecteur n'est pas rendu. Les écrans restent accessibles et montrent l'historique. |

---

## Le journal

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `log_channel` | chaîne ou `null` | `null` | Le canal où le paquet écrit ses propres erreurs. **Sans valeur**, il écrit dans le canal par défaut de votre application. |

> **C'est le seul endroit où ce paquet se plaint**, et ça vaut d'être su · il est
> écrit pour ne jamais casser la page de quelqu'un, donc **tout ce qui rate rate
> en silence** · une réception refusée, un fichier de tunnels mal formé, un
> téléchargement échoué, un bloc d'écran qui n'a pas pu lire ses données, une
> synchronisation Google interrompue.
>
> Lui donner un canal à lui — un fichier séparé, par exemple — est le geste qui
> rend ces pannes visibles sans les noyer dans le journal de l'application.
> **Devant un chiffre qui ne ressemble à rien, c'est là qu'on regarde**, et
> `analytics:check` n'y supplée pas · il voit la configuration, pas ce qui s'est
> passé cette nuit.

---

## Les tunnels et les événements nommés

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `funnels_path` | chemin ou `null` | `null` | Le fichier qui déclare vos tunnels. **Sans valeur**, `app/Analytics/funnels.php`. Absent, il n'y a simplement aucun tunnel. |
| `events_path` | chemin ou `null` | `null` | Le fichier qui déclare vos événements nommés. **Sans valeur**, `app/Analytics/events.php`. Absent, l'écran des événements ne montre que ce que le collecteur a capturé tout seul. |
| `events_scan_paths` | liste de chemins | `['app', 'resources/views']` | Ce que `analytics:events:scan` parcourt pour trouver les événements employés dans votre code. Relatifs à la racine du projet. |

Les deux fichiers sont **chargés à la demande** · ils ne coûtent rien aux pages
qui ne les emploient pas.

---

## Le point de collecte

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `endpoint` | chemin | `'__analytics'` | L'adresse où le collecteur envoie ses lots. Le **nom** de la route ne bouge pas, lui · `analytics.web.ingest`. |
| `throttle` | `requêtes,minutes` | `'120,1'` | La limite de débit posée sur ce point. **Comptée par adresse IP**, comme toute limite de Laravel pour un visiteur non connecté. |
| `exclude_ips` | liste d'IP ou de CIDR | `[]` | Les adresses entièrement exclues du suivi — personnel interne, sondes de supervision. **Vide veut dire « personne »**, et c'est une réponse. |

> **La limite par IP et les proxies de confiance se tiennent par la main.** Tant
> que Laravel ne connaît pas vos proxies, **toutes vos visites portent une seule
> adresse** · elles partagent donc un seul compteur, et au-delà du seuil les
> envois sont refusés **pour tout le site à la fois**. Rien ne le signale — une
> balise d'envoi ne lit pas la réponse — et le tableau de bord se contente de
> plafonner.
>
> C'est la plus coûteuse des conséquences d'un proxy non déclaré, avant même la
> géolocalisation. Réglez les proxies ; ne montez pas le seuil pour compenser.

> **Pas de jeton CSRF sur ce point, et il ne peut pas y en avoir** · une balise
> d'envoi n'en porte pas. Le contrôle d'origine et la limite de débit tiennent
> ce rôle, et **ni l'un ni l'autre n'est réglable** · ils sont ajoutés après
> votre pile.

---

## L'identité du visiteur

Ces quatre valeurs suffisent à la plupart des projets · le paquet en tire le
sujet suivi, les exclusions et le consentement, sans une ligne de code.

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `identity.subject_guards` | liste de gardes | `['web']` | Les gardes dont l'utilisateur connecté devient le sujet suivi. |
| `identity.exclude_guards` | liste de gardes | `[]` | Les gardes dont l'utilisateur connecté est **entièrement** exclu du suivi. Vide veut dire « personne ». |
| `identity.consent_cookie` | nom de cookie ou `null` | `null` | Le cookie dont la valeur `"1"` autorise l'identifiant de visiteur persistant. **Sans valeur**, aucun identifiant ne survit à la session. Le nommer ici suffit · le paquet le sort du chiffrement de Laravel lui-même, sinon il serait relu à `null` et le consentement ne serait jamais vu. |
| `identity.subjects` | dictionnaire | `[]` | Comment afficher un sujet · un libellé, et les colonnes à concaténer pour son nom. **Vide**, l'écran affiche le nom du garde mis en forme — `client` devient `Client` — et l'identifiant, faute de savoir quelles colonnes lire. |

Le nom d'un sujet est **lu au moment de l'affichage et jamais stocké**.

```php
'subjects' => [
    'client' => ['label' => 'Client', 'name' => ['first_name', 'last_name']],
],
```

> **Pour une logique que ces valeurs ne couvrent pas**, trois fermetures posées
> depuis un fournisseur de services, et elles l'emportent ·
> `Analytics::resolveSubjectUsing(…)`, `consentUsing(…)`, `excludeUsing(…)`.

---

## L'espace d'administration

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `admin.route_prefix` | chemin | `'admin/analytics'` | Ce qui apparaît dans l'URL de chaque écran d'analytique. |
| `admin.middleware` | liste | `['web', 'auth']` | Ce qui protège ces écrans. Le défaut convient à une application à un seul garde · adaptez-le, par exemple `['web', 'auth:admin']`. |
| `admin.layout` | nom de composant ou `null` | `null` | Le composant Blade qui dessine ces écrans. **Sans valeur**, la coquille du paquet, qui est une administration complète et autonome. |

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `admin.marketing.route_prefix` | chemin | `'admin/marketing'` | L'adresse des écrans marketing, qu'un hôte peut monter ailleurs que les autres. |
| `admin.marketing.middleware` | liste | `['web', 'auth']` | Ce qui protège les écrans marketing, indépendamment des autres. |

> **Une liste vide monte les écrans sans aucune protection**, elle ne les retire
> pas · ni session, ni authentification, donc **joignables publiquement**. Il n'y
> a pas de réglage pour ne pas monter les écrans · pour les mettre hors
> d'atteinte, désignez un garde que personne n'a, ou bloquez le préfixe en amont.
>
> Le paquet inscrit un avertissement dans son journal au moment du montage, et
> `analytics:check` le rapporte comme **bloquant**. Les deux arrivent après coup.

> **La pile doit être complète**, session comprise · les routes du paquet sont
> déclarées hors de vos groupes, donc elles n'héritent d'aucun de vos
> middlewares. C'est pourquoi le défaut porte `web` et pas seulement `auth`.

> **Les noms de routes ne sont pas réglables, et c'est délibéré.** Ils sont
> fixes — `analytics.admin.overview`, `analytics.admin.marketing.campaigns`… —
> pour qu'un menu ou une redirection puisse en écrire un. **Seules les adresses
> bougent.** La liste complète est dans [fonctionnalites.md](fonctionnalites.md).

> **Le layout appartient à l'espace, marketing compris.** C'est la même
> administration, et un hôte qui monte les deux veut un seul habillage.

---

## L'espace public

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `web.middleware` | liste | `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession` | La pile posée devant le point de collecte. **Elle doit être complète** · la route du paquet est déclarée hors de vos groupes, donc elle n'hérite de rien. |

> **Ne retirez pas la session de cette liste.** Sans consentement — le cas par
> défaut — l'identifiant du visiteur vit dans la session, donc sans
> `StartSession` chaque envoi du collecteur répond en erreur. La page du
> visiteur n'en souffre pas, une balise d'envoi ne lisant pas la réponse · vous
> ne le verrez que dans vos journaux d'erreurs, et vos écrans resteront vides.
>
> Le contrôle d'origine et la limite de débit, eux, sont ajoutés **après** cette
> liste · la vider vous prive de la session, pas d'eux.

**Elle doit être complète.** Les routes du paquet sont enregistrées hors de vos
groupes de routes · elles n'héritent de rien.

---

## La durée de vie des données

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `retention_days` | entier (jours) | `90` | Au-delà, les événements bruts sont effacés par `analytics:prune`. **Les sessions et les profils de visiteur sont gardés indéfiniment** · seul le détail événement par événement disparaît. |

> **`0` ou un nombre négatif coupe la purge**, il ne l'accélère pas · rien n'est
> jamais effacé, et la commande le dit en clair plutôt que de vider la table.
> C'est la lecture prudente d'une valeur qu'on ne peut pas deviner, mais c'est
> l'inverse de ce qu'on attend en l'écrivant · pour ne rien garder, il n'y a pas
> de réglage, et c'est volontaire.

**Cette valeur plafonne ce que trois écrans peuvent montrer**, et c'est la
conséquence qu'on ne voit pas venir · ils lisent les événements bruts ·

| | Ce qui arrive au-delà de la rétention |
|---|---|
| **les tunnels** | ne rapportent rien · les étapes se lisent sur les événements |
| **les événements** | même chose · l'écran ne remonte pas plus loin |
| **le détail d'une session** | la session **reste listée**, avec ses chiffres, mais **son parcours est vide** · les pages et les clics qui le composaient ont été effacés |

Les autres écrans ne sont pas concernés · fréquentation, visiteurs, sources,
marketing et Search Console lisent des données que la purge ne touche pas.

> **Une période de 90 jours sur une rétention de 90 jours est une coïncidence**,
> et elle se voit · le bord de la fenêtre se dégarnit à mesure que la purge
> avance. Gardez la rétention au-dessus de la plus longue période que vous
> comptez lire.

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `session.timeout_minutes` | entier | `5` | L'inactivité au bout de laquelle une session est close. La fin enregistrée est la dernière activité **plus** ce délai, jamais l'heure du balayage. |
| `session.heartbeat_seconds` | entier | `20` | À quelle fréquence le collecteur signale qu'un onglet est toujours là. Suspendu quand l'onglet est caché. |
| `session.flush_seconds` | entier | `5` | À quelle fréquence il envoie ce qu'il a accumulé. |

---

## L'écran temps réel

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `realtime.poll_seconds` | entier | `10` | À quelle fréquence la page se rafraîchit. **Suspendu quand l'onglet est caché, et quand l'écran est sorti du champ de vision.** |
| `realtime.online_seconds` | entier | `60` | En deçà de quelle ancienneté d'activité une session compte comme « en ligne ». |
| `realtime.window_minutes` | entier | `30` | La fenêtre « récent » que lisent tous les blocs de cette page. |
| `realtime.feed_limit` | entier | `25` | La borne du fil d'activité, pour qu'un rafraîchissement ne grossisse pas avec le trafic. |

> **Aucun service permanent n'est requis** · pas de processus de travail, pas de
> connexion permanente, pas de service externe. La page interroge le serveur à
> intervalle régulier, et c'est tout.

---

## Google Search Console

Google retire le mot-clé des adresses de provenance · les requêtes de recherche
naturelle ne s'obtiennent que par son interface de programmation, autorisée par
un administrateur en lecture seule.

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `search_console.client_id` | chaîne | `''` · **secret** · `ANALYTICS_GSC_CLIENT_ID` | L'identifiant du client OAuth 2.0 Google. |
| `search_console.client_secret` | chaîne | `''` · **secret** · `ANALYTICS_GSC_CLIENT_SECRET` | Son secret. |
| `search_console.redirect` | URL absolue ou `null` | `null` · `ANALYTICS_GSC_REDIRECT` | L'adresse de retour déclarée sur le client OAuth. **Sans valeur**, la route de rappel du paquet · `{admin.route_prefix}/integrations/search-console/callback`. |

> **Tant que les deux premières sont vides, la fonction ne se propose pas.** Le
> lien disparaît de la barre latérale, l'écran d'intégrations dit ce qui manque
> si on s'y rend quand même, et la connexion refuse en ramenant à cet écran. Ce
> n'est pas une panne, c'est l'état par défaut · rien à faire si vous ne voulez
> pas de cette fonction.

Ce que l'hôte fournit · un client OAuth 2.0 web dans Google Cloud, avec
l'interface Search Console activée, et l'adresse de retour ci-dessus déclarée
dessus.

---

## La vie privée

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `privacy.anonymize_ip` | booléen | `false` | À `true`, l'IP est tronquée avant d'être stockée · **le dernier octet en IPv4**, tout ce qui suit les 48 premiers bits en IPv6. Au défaut, elle est gardée entière — ce qui donne la localité et l'historique de connexion. |
| `privacy.redact_query_params` | liste | `token`, `access_token`, `auth`, `password`, `secret`, `apikey`, `api_key`, `otp`, `signature`, `email` | Les paramètres dont la valeur est remplacée par `redacted` dans les adresses stockées, sans tenir compte de la casse. **Le paramètre reste, c'est sa valeur qui part** · une adresse tronquée ne se relit plus. **Les paramètres de campagne sont gardés tels quels** — `utm_*`, `gclid`, `fbclid`, les vôtres. Videz la liste pour tout stocker sans rien masquer. |

> **Tronquer n'aveugle pas la carte.** Le pays et la ville se lisent encore sur
> une adresse tronquée · c'est l'identification d'un abonné précis qui devient
> impossible, ce qui est exactement ce qu'on cherche.

---

## La géolocalisation

Base locale MaxMind GeoLite2 City · gratuite, précise, et **entièrement sur
votre serveur**. L'adresse d'un visiteur ne sort jamais.

| Clé | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|
| `geoip.license_key` | chaîne | `''` · **secret** · `ANALYTICS_GEOIP_LICENSE_KEY` | La clé MaxMind. **Sans elle**, `analytics:geoip:download` refuse en nommant la variable et l'adresse d'inscription, et la localisation reste vide. |
| `geoip.edition` | chaîne | `'GeoLite2-City'` · `ANALYTICS_GEOIP_EDITION` | L'édition de base téléchargée. |
| `geoip.database_path` | chemin absolu | `storage_path('app/analytics/GeoLite2-City.mmdb')` · `ANALYTICS_GEOIP_DATABASE` | Où la base extraite est écrite, et lue. |
| `geoip.dev_ip` | IP publique ou `null` | `null` · `ANALYTICS_GEOIP_DEV_IP` | En développement, l'adresse publique substituée aux adresses privées — `127.0.0.1` ne se localise jamais. **Inerte en production par construction** · une vraie adresse publique n'est jamais remplacée. |
| `geoip.download_url` | URL | permalien MaxMind | `{edition}` et `{license_key}` y sont substitués. À ne changer que pour un miroir interne. |

Une clé gratuite s'obtient sur
[maxmind.com](https://www.maxmind.com/en/geolite2/signup), puis
`php artisan analytics:geoip:download`. `analytics:geoip:check` dit où vous en
êtes.

---

## Les variables d'environnement, rassemblées

| Variable | Clé | Secret | Écrite dans `.env` par l'installation |
|---|---|---|---|
| `ANALYTICS_ENABLED` | `enabled` | non | oui |
| `ANALYTICS_GSC_CLIENT_ID` | `search_console.client_id` | **oui** | oui, vide |
| `ANALYTICS_GSC_CLIENT_SECRET` | `search_console.client_secret` | **oui** | oui, vide |
| `ANALYTICS_GSC_REDIRECT` | `search_console.redirect` | non | oui, vide |
| `ANALYTICS_GEOIP_LICENSE_KEY` | `geoip.license_key` | **oui** | oui, vide |
| `ANALYTICS_GEOIP_EDITION` | `geoip.edition` | non | **non** · à écrire soi-même |
| `ANALYTICS_GEOIP_DATABASE` | `geoip.database_path` | non | oui, vide |
| `ANALYTICS_GEOIP_DEV_IP` | `geoip.dev_ip` | non | oui, vide |

Une variable **vide vaut absente** · elle est relue comme une chaîne vide, et ce
n'est ni une clé, ni un chemin.

**Sept des huit sont posées par `analytics:install`**, commentées, et seulement
si elles manquent · une valeur déjà écrite n'est jamais touchée. L'édition de
base GeoIP n'y est pas, parce qu'on n'en change pour ainsi dire jamais ·
écrivez-la à la main le jour où vous passez à une autre.

---

## Les réglages du kit, qui ne sont pas les nôtres

Falcon Analytics s'appuie sur `falcon/ui-kit`, qui a sa propre configuration.
Ces clés-là ne sont **pas** dans `config/analytics.php`, et elles sont décrites
dans la notice du kit. Celles qui vous concernent en tant qu'intégrateur
d'analytics ·

| Clé du kit | Ce qu'elle change pour vous |
|---|---|
| `ui.assets.path` | où nos fichiers compilés sont publiés sous `public/` |
| `ui.inject` | si le kit pose lui-même ses balises dans les pages qu'il dessine |
| `ui.reset` | qui fournit le style de base du navigateur |

Leur référence complète · `vendor/falcon/ui-kit/docs/configuration.md`.

---

## Les couleurs, et comment les changer

Vingt propriétés CSS, déclarées dans les deux thèmes. **Aucune recompilation du
paquet n'est nécessaire** · ce sont des propriétés ordinaires, que le navigateur
résout à l'affichage.

| Jeton | Rôle | Clair | Sombre |
|---|---|---|---|
| `--an-series-1` | la plus forte des parts d'un camembert | `#1684ea` | `#3b8fe8` |
| `--an-series-2` | | `#4b9bf0` | `#2f77c8` |
| `--an-series-3` | | `#7cb8f2` | `#2560a4` |
| `--an-series-4` | | `#a5cdf7` | `#1d4b80` |
| `--an-series-5` | | `#bcdcfa` | `#17395f` |
| `--an-series-6` | la plus pâle | `#d1d5db` | `#374151` |
| `--an-conversion` | une conversion · une réussite | `#10b981` | `#34d399` |
| `--an-online` | un visiteur en ligne maintenant | `#54ce91` | `#4ade80` |
| `--an-online-strong` | le mot qui le nomme, sur fond sombre | `#22a96f` | `#22c55e` |
| `--an-ink` | l'encre de l'écran temps réel | `#000624` | `#f3f4f6` |
| `--an-ink-soft` | son encre secondaire | `#44485f` | `#9ca3af` |
| `--an-accent` | son accent | `#116dff` | `#4b9bf0` |
| `--an-map-land` | les terres de la carte | `#f5f5f5` | `#1f2937` |
| `--an-map-border` | les frontières | `#dddddd` | `#374151` |
| `--an-live-1` | la première du temps réel | *dérivé de `--an-accent`* | |
| `--an-live-2` | la deuxième | *dérivé de `--an-online`* | |
| `--an-live-3` | | `#8ab5ff` | `#2f6bb5` |
| `--an-live-4` | | `#c9dbff` | `#24507f` |
| `--an-live-5` | | `#dde1e6` | `#313845` |
| `--an-live-6` | | `#eff1f5` | `#232833` |

**Pour en changer une**, dans votre propre feuille, qui passe après la nôtre ·

```css
@layer components {
    :root       { --an-accent: #7c3aed; }
    .dark       { --an-accent: #a78bfa; }
}
```

> **Les deux premières couleurs du temps réel dérivent des deux jetons
> au-dessus**, au lieu de les recopier · retintez l'accent et cet écran reste
> d'accord avec lui-même.

> **Le sélecteur du thème sombre est `.dark`**, et c'est le mot du kit. Il n'est
> pas réglable · il est compilé dans les feuilles livrées. La notice du kit dit
> pourquoi.
