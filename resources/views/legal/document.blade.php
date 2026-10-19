@extends('layouts.app')
@section('title', $title.' | SpaceMatch')
@section('content')
<article class="container py-5 legal-document">
    <div class="eyebrow">Owner and legal review required</div><h1 class="page-heading my-3">{{ $title }}</h1>
    <p>Draft version {{ $version }} &middot; Proposed effective date: {{ config('marketplace.legal_effective_date') }}</p>
    <div class="alert alert-warning">This draft requires the platform owner's and legal adviser's approval. It does not guarantee legal compliance.
        @unless(config('marketplace.operator_name') && config('marketplace.privacy_contact')) The operator identity and privacy contact are not yet configured. These details must be completed before public launch. @endunless
    </div>
    @foreach($sections as $heading => $text)<section class="mb-4"><h2 class="panel-title">{{ $heading }}</h2><p>{{ $text }}</p></section>@endforeach
    <section><h2 class="panel-title">Contact and inquiries</h2><p>Operator: {{ config('marketplace.operator_name') ?: 'Pending owner confirmation' }}.</p>
    @if(config('marketplace.privacy_contact'))<a href="mailto:{{ config('marketplace.privacy_contact') }}">{{ config('marketplace.privacy_contact') }}</a>@else<p>Privacy and support contact: pending owner confirmation. No Data Protection Officer or postal address has been invented.</p>@endif</section>
    <p class="mt-4"><a href="{{ route('terms') }}">Terms of Service</a> &middot; <a href="{{ route('privacy') }}">Privacy Notice</a></p>
</article>
@endsection
