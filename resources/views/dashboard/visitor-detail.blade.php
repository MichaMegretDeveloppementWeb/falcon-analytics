@extends($analyticsLayout)

@section($analyticsSection)
    <livewire:analytics::admin.visitor-detail-page :visitor="$visitor" />
@endsection
