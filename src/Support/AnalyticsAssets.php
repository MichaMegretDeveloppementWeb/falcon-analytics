<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Falcon\Analytics\Console\InstallCommand;

/**
 * Ce que l'hote importe · **la source unique de ces deux chemins.**
 *
 * Ils vivaient en constantes dans {@see InstallCommand}, qui est aujourd'hui
 * leur seul lecteur · le paquet n'a ni desinstallateur ni diagnostic. Les
 * sortir ici ne corrige donc aucune duplication existante · cela en interdit
 * une future, et donne au paquet la meme forme que ses voisins.
 *
 * La forme et le nom sont ceux de `BookingAssets::hostImports()` et de
 * `KitAssets::hostImports()` · trois paquets qui repondent a la meme question
 * doivent y repondre du meme mot, sans quoi celui qui les lit tous les trois
 * doit apprendre trois fois la meme chose.
 *
 * **Ce qui manque encore, et qui n'est pas ici** · rien ne relit cette liste
 * apres l'installation. `booking:check` ouvre les entrees de l'hote pour
 * verifier que les lignes y sont toujours ; analytics n'a pas de commande
 * equivalente, donc `analytics.assets` est ecrit et consulte par personne. Le
 * jour ou `analytics:check` existera, il partira d'ici.
 */
final class AnalyticsAssets
{
    /**
     * Cle de configuration, nature du fichier, chemin depuis la racine du projet.
     *
     * **Le collecteur va dans le script du site public, pas dans celui du
     * back-office** · on ne mesure pas les visites de la personne qui
     * administre.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function hostImports(): array
    {
        return [
            'admin_css' => ['css', 'vendor/falcon/analytics/resources/css/analytics-admin.css'],
            'web_js' => ['js', 'vendor/falcon/analytics/resources/js/collector.js'],
        ];
    }
}
