@component('mail::layout')
    @slot('header')
        @component('mail::header', ['url' => config('app.url')])
            {{ config('app.name') }}
        @endcomponent
    @endslot

    # Feed error

    {{-- Adding indentation breaks this component --}}
    @if($missingFeeds->count() > 0)
The following feeds are missing on S3:
@component('mail::table')
| Feed          | Store          |
|:------------- |:-------------- |
@foreach($missingFeeds as $missingFeed)
| {{ $missingFeed['feedName'] }} | {{ $missingFeed['storeCode'] }} |
@endforeach
@endcomponent
    @endif

    {{-- Adding indentation breaks this component --}}
    @if($outdatedFeeds->count() > 0)
The following feeds are outdated on S3:
@component('mail::table')
| Feed          | Store          | Last generated at |
|:------------- |:-------------- |:----------------- |
@foreach($outdatedFeeds as $outdatedFeed)
| {{ $outdatedFeed['feedName'] }} | {{ $outdatedFeed['storeCode'] }} | {{ $outdatedFeed['lastDate'] }}
@endforeach
@endcomponent
    @endif

    @slot('footer')
        @component('mail::footer')
            Deze email is automatisch gegenereerd en verzonden vanuit {{ config('app.name') }}.
        @endcomponent
    @endslot
@endcomponent
