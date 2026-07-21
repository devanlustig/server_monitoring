@extends('layouts.app')
@section('content')
    <h1 class="h2 mb-4">Add monitored server</h1>
    <form method="POST" action="{{ route('servers.store') }}">@csrf @include('servers._form', ['submitLabel' => 'Test and save server'])</form>
@endsection
