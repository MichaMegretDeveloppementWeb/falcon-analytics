@php
    /*
     * Says why localities are missing, on the screens that show them.
     * Geolocation degrades silently: a missing database, a truncated one and a
     * private address all read as « Inconnu ». Silent when there is nothing to
     * report.
     */
    $status = app(\Falcon\Analytics\Support\GeoResolver::class)->status(request()->ip());
@endphp

@if ($status !== \Falcon\Analytics\Enums\GeoStatus::Ready && $status !== \Falcon\Analytics\Enums\GeoStatus::NotInDatabase)
    <x-ui::alert type="warning" :title="$status->label()" class="mb-6">
        {{ $status->hint() }}
    </x-ui::alert>
@endif
