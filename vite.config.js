import { defineConfig } from 'vite';

/*
 * Le script que le paquet livre · le collecteur, et lui seul.
 *
 * Tailwind ecrit la feuille dans le meme dossier, apres, depuis la ligne de
 * commande. Deux producteurs, un dossier, aucun ne touchant les fichiers de
 * l'autre — donc cette etape passe en premier, et c'est elle qui a le droit de
 * vider le dossier.
 */
export default defineConfig({
    // `public` est l'endroit ou le paquet met ce qu'il livre, et Vite lit ce nom
    // comme « fichiers statiques a recopier ». Le dire evite qu'il recopie le
    // dossier dans lui-meme.
    publicDir: false,

    // Relatif, et il le faut · le paquet ne sait pas ou une application publiera
    // ses fichiers.
    base: './',

    build: {
        outDir: 'public',

        // La seule etape qui vide le dossier, et il en faut une.
        emptyOutDir: true,

        rollupOptions: {
            input: {
                analytics: 'resources/js/collector.js',
            },

            output: {
                /*
                 * **Une fonction immediate, pas un module**, et ce n'est pas un
                 * detail de gout · le kit emet une balise CLASSIQUE pour le
                 * fichier d'un paquet, `<script src … defer>`. Un module y
                 * serait charge comme un script ordinaire, et ses declarations
                 * d'export leveraient dans le navigateur.
                 *
                 * La source n'importe rien et n'exporte rien · elle est deja
                 * une fonction immediate. Ce format ne fait que la conserver.
                 */
                format: 'iife',
                entryFileNames: '[name].js',
            },
        },
    },
});
