@php
    /*
     * Dit pourquoi les localités manquent, sur les écrans qui en affichent.
     *
     * La géolocalisation dégrade en silence : base absente, base tronquée, adresse privée, tout
     * finissait en « Inconnu » dans la colonne, et rien ne disait lequel des trois corriger.
     * Muet dès qu'il n'y a rien à signaler.
     */
    $statut = app(\Falcon\Analytics\Support\GeoResolver::class)->status(request()->ip());
@endphp

@if ($statut !== \Falcon\Analytics\Enums\GeoStatus::Ready && $statut !== \Falcon\Analytics\Enums\GeoStatus::NotInDatabase)
    <x-ui.alert type="warning" :title="$statut->label()" class="mb-6">
        {{ $statut->hint() }}
    </x-ui.alert>
@endif
