@extends('layout')

@section('title', $who)

@push('scripts')
<script>{{ $who }}</script>
@endpush

@section('body')
before {{ $who }}
@php(\Venusian\Serve\Tests\Fixtures\LoopPause::yield())
after {{ $who }} {{ $greeting }} {{ $mood }}
@endsection
