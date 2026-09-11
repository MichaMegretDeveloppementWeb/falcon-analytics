@extends($analyticsLayout)

@section($analyticsSection)
    <livewire:analytics::admin.session-detail-page :session="$session" />
@endsection
