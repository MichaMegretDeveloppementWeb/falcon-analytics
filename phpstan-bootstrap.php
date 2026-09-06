<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

/*
 * Ce que le fournisseur declare a Laravel, declare a l'analyseur.
 *
 * larastan verifie qu'un `view('...')` designe une vue qui existe, et il le fait
 * en appelant reellement `view()->exists()`. Dans un paquet, le prefixe
 * `analytics::` n'est enregistre qu'au demarrage du fournisseur, que l'analyse
 * ne joue pas.
 *
 * L'enregistrement est le meme qu'en AnalyticsServiceProvider::boot(). Sans lui,
 * le controle est inapplicable a un paquet et il faudrait le taire, ce qui
 * reviendrait a ne plus verifier les vues du tout.
 */
try {
    View::addNamespace('analytics', __DIR__.'/resources/views');
} catch (Throwable) {
    // L'analyse peut tourner sans conteneur amorce. Le controle des vues sera
    // alors inoperant, ce qui est son etat d'avant : rien de pire.
}
