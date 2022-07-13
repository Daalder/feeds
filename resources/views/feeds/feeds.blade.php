@extends('backoffice::layouts.backoffice')
@section('content')
    <daalder-feeds></daalder-feeds>
@stop

@push('headCss')
    <link rel="stylesheet" href="css/feeds-package-styles.css">
@endpush
@push('scripts')
    <script type="text/javascript" src="js/feeds.js"></script>
@endpush

@section('title')
    @lang('Feeds')
@stop




