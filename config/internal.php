<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Réglages internes du paquet — PAS ceux de l'hôte
|--------------------------------------------------------------------------
|
| Ce fichier n'est jamais publié, et ce qu'il contient n'est jamais demandé à
| personne. Ce sont des décisions de conception du paquet, posées ici plutôt
| qu'en dur pour qu'elles se lisent en un endroit, se changent d'une ligne et
| se déplacent le temps d'un essai.
|
| La différence avec `analytics.php` tient en une question · est-ce que deux
| hôtes raisonnables répondraient différemment ? Si oui, c'est un réglage de
| l'hôte et ça va dans le fichier publié. Si non, c'est un choix du paquet et
| ça vient ici — parce qu'une valeur qui ne convient à personne est un défaut
| à corriger dans le paquet, pas une question à poser à chaque projet.
|
| Le fournisseur de services les pose APRÈS la configuration de l'hôte, sans
| égard pour ce qu'une copie publiée dirait. Une clé de ce fichier recopiée
| dans `config/analytics.php` n'aurait donc aucun effet, et c'est voulu.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | L'entretien, rattrapé au chargement d'un écran
    |--------------------------------------------------------------------------
    |
    | Le planificateur est la voie normale. Celle-ci existe parce qu'un
    | planificateur d'hébergement mutualisé s'arrête sans un mot : le résumé
    | s'arrête avec lui, l'effacement aussi — rien n'est perdu, par conception
    | — mais les résumés prennent du retard et aucun écran ne le dit.
    |
    | Rien de tout cela n'est une question pour l'hôte · c'est au paquet de
    | tenir ses propres comptes, et de le faire sans qu'on le sente.
    |
    */

    'maintenance' => [
        // Le rattrapage a lieu après l'envoi de la page, donc l'écran ne
        // ralentit jamais. Coupé, un planificateur mort ne se voit plus.
        'on_screen_load' => true,

        // Jamais plus d'une fois par heure, quel que soit le nombre de pages
        // ouvertes et le nombre d'administrateurs.
        'interval_minutes' => 60,

        // Jours résumés par visite. Un retard de six mois se rattrape en
        // vingt-six passages plutôt qu'en un seul qui retiendrait un
        // processus du serveur bien après que la page soit partie.
        'days_per_run' => 7,
    ],

];
