{{-- Attribution ceiling --}}
@if ($truncatedAt !== null)
    <x-ui::alert type="warning" class="{{ $class ?? '' }}">{{ __('Plus de :count sessions de la période sont arrivées par un lien qui porte des paramètres, comme ceux des publicités ou des réseaux sociaux : seules les :count premières sont lues, donc ces chiffres sont en dessous de la réalité. Choisissez une période plus courte, ou faites relever cette limite par la personne qui maintient le site.', ['count' => number_format($truncatedAt, 0, ',', ' ')]) }}</x-ui::alert>
@endif
