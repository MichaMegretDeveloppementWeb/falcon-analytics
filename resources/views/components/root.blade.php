{{--
    Ce qui enveloppe une vue reactive.

    L'element est rendu, et pas seulement le slot · le paquet garde une prise
    unique pour le jour ou une de ses sous-interfaces devra fixer une police ou
    une couleur qui ne s'herite de nulle part. L'ajouter plus tard couterait de
    reprendre les memes trente-quatre vues une seconde fois.

    Les attributs sont transmis · une vue dont la racine portait des classes de
    placement les deplace ici plutot que de se retrouver un niveau plus bas.
--}}
<div {{ $attributes->merge(['class' => 'an-root']) }}>{{ $slot }}</div>
