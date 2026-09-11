{{--
    Ce que `@analyticsCollector` pose sur une page publique · le collecteur, et
    ce qu'il a besoin de savoir.

    **Rien quand la mesure est coupee**, ou quand le contexte est exclu · pas
    meme le telechargement du fichier. C'est la meilleure facon de ne pas
    mesurer.

    **Le fichier est declare au kit**, comme tout fichier d'un paquet. Le kit
    bat son adresse versionnee, la pousse dans sa pile reservee, et la pose
    avant `</body>` quand le gabarit de l'hote ne rend aucune directive · ce qui
    est le cas d'une page publique ordinaire.

    L'ordre ne se joue pas · la balise porte `defer`, donc le collecteur
    s'execute apres l'analyse du document, et la configuration ci-dessous est
    lue avant lui quelle que soit la place de la directive dans le gabarit.
--}}
@php
    $analyticsConfiguration = \Falcon\Analytics\View\Collector::configuration();
@endphp

@if ($analyticsConfiguration !== null)
    <x-ui::assets package="analytics" :files="['analytics.js']" />

    <script>window.__falconAnalytics={!! $analyticsConfiguration !!};</script>
@endif
