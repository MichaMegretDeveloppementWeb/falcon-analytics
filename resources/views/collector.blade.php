{{--
    Ce que `@analyticsCollector` pose sur une page publique · le collecteur, et
    ce qu'il a besoin de savoir.

    **Rien quand la mesure est coupee**, ou quand le contexte est exclu · pas
    meme le telechargement du fichier. C'est la meilleure facon de ne pas
    mesurer.

    **La balise est ecrite ici, et non declaree au kit**, et c'est une decision
    mesuree. Declarer aurait ete la voie normale · le kit place alors ce qui
    manque avant `</body>`, meme dans une page qui ne connait rien de la suite.
    Mais son injection, une fois declenchee, pose AUSSI ses propres balises ·
    son reset, ses soixante kilo-octets de feuille, son script et son conteneur
    de notifications. Constate le 2026-09-12 sur une page publique ordinaire ·
    le site de l'hote s'en serait trouve redessine.

    Ce qui est employe ici est le constructeur de balises public du kit · la
    meme balise, a la meme adresse versionnee, sans rien declencher. La page
    d'un hote qui n'emploie pas le kit ne recoit donc que le collecteur.

    L'ordre ne se joue pas · cette balise porte `defer`, donc le collecteur
    s'execute apres l'analyse du document, et la configuration ci-dessous est
    lue avant lui quelle que soit la place de la directive dans le gabarit.
--}}
@php
    $analyticsConfiguration = \Falcon\Analytics\View\Collector::configuration();
@endphp

@if ($analyticsConfiguration !== null)
    <script>window.__falconAnalytics={!! $analyticsConfiguration !!};</script>

    {!! \Falcon\Ui\Assets::tagsFor('analytics', ['analytics.js'])['scripts'] !!}
@endif
