{{--
    Ce qui enveloppe un ecran · son gabarit, et rien de plus.

    Le slot est calcule avant cette vue, donc avant l'en-tete du gabarit. C'est
    ce qui fait qu'une declaration d'assets executee pendant le rendu de l'ecran
    se trouve dans la pile quand le gabarit rencontre `@falconStyles`.

    `$layout` et `$title` viennent de la classe · le gabarit de l'hote quand il
    en nomme un, celui du paquet sinon.
--}}
<x-analytics::assets />

<x-dynamic-component :component="$layout" :title="$title">
    {{ $slot }}
</x-dynamic-component>
