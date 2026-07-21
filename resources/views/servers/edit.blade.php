@extends('layouts.app')
@section('content')
    <h1 class="h2 mb-4">Edit {{ $server->name }}</h1>
    <form method="POST" action="{{ route('servers.update', $server) }}">@csrf @method('PUT') @include('servers._form', ['submitLabel' => 'Test and update server'])</form>
@endsection
