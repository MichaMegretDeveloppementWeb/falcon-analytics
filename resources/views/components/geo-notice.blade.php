@php
    /*
     * Says why localities are missing, on the screens that show them.
     * Geolocation degrades silently: a missing database, a truncated one and a
     * private address all read as « Inconnu ». Silent when there is nothing to
     * report.
     */
    $statut = app(\Falcon\Analytics\Support\GeoResolver::class)->status(request()->ip());
@endphp

@if ($statut !== \Falcon\Analytics\Enums\GeoStatus::Ready && $statut !== \Falcon\Analytics\Enums\GeoStatus::NotInDatabase)
    <x-ui.alert type="warning" :title="$statut->label()" class="mb-6">
        {{ $statut->hint() }}
    </x-ui.alert>
@endif
