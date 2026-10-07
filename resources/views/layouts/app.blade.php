@extends('laravel-usp-theme::master')

@section('javascripts_bottom')
    @parent
    @stack('modals')
    @stack('scripts')
@endsection