{{--
    Ce qui enveloppe une vue reactive.

    L'element est rendu, et pas seulement le slot · le paquet garde une prise
    unique pour le jour ou une de ses sous-interfaces devra fixer une police ou
    une couleur qui ne s'herite de nulle part. L'ajouter plus tard couterait de
    reprendre les memes trente-quatre vues une seconde fois.

    Les attributs sont transmis · une vue dont la racine portait des classes de
    placement les deplace ici plutot que de se retrouver un niveau plus bas.

    La declaration d'assets passe ici aussi · un bloc reactif peut etre dessine
    dans la page d'un hote qui n'est pas un ecran du paquet, et il doit alors
    amener sa feuille lui-meme.

    **Elle est DANS l'element, jamais avant.** Ce composant laisse des blancs
    derriere lui, et Livewire releve le nom de balise racine de ses composants
    enfants sur ce qu'il trouve en tete du rendu · un blanc avant l'element et
    il releve autre chose qu'un nom de balise, puis leve au rechargement
    suivant.
--}}
<div {{ $attributes->merge(['class' => 'an-root']) }}><x-analytics::assets />{{ $slot }}</div>
