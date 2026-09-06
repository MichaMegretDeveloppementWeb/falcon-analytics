<?php

/*
 * Le collecteur n'est plus servi par le paquet.
 *
 * Il l'etait par une route, avec un an de cache et une empreinte dans
 * l'adresse. L'hote l'importe desormais dans son entree JavaScript publique ·
 *
 *     import '../../vendor/falcon/analytics/resources/js/collector.js';
 *
 * C'est son build qui le nomme, le versionne et le sert. Le fichier ne passe
 * plus par PHP, et une mise a jour du paquet le rejoue au prochain
 * `npm run build`.
 *
 * Cet essai garde la route retiree · si elle revenait, deux exemplaires du meme
 * collecteur pourraient se retrouver sur une page et compter chaque visite deux
 * fois.
 */

it('no longer serves the collector script itself', function () {
    $this->get('/__analytics.js')->assertNotFound();
});

/*
 * Le fichier reste livre, et il reste autonome · aucun `import`, aucune
 * dependance npm. C'est ce qui permet a l'hote de l'importer tel quel, sans
 * rien installer.
 */
it('ships a self-contained collector for the host to import', function () {
    $path = dirname(__DIR__, 2).'/resources/js/collector.js';

    expect($path)->toBeFile();

    $source = (string) file_get_contents($path);

    expect($source)->toContain('sendBeacon')
        ->toContain('__falconAnalytics')
        // Le repli sur `fetch` avale son rejet · un endpoint injoignable ne
        // doit jamais faire remonter une promesse non geree dans la console de
        // l'hote.
        ->toContain('.catch(');

    expect(preg_match('/^\s*(import|export)\s/m', $source))->toBe(0);
});
