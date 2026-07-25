<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Server Monitoring') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
    .bg-orange{
        background:#fd7e14 !important;
        color:#fff !important;
    }
    .table-middle td{
        vertical-align:middle;
    }
    .badge-lg{
        font-size:.85rem;
        padding:.45rem .75rem;
        border-radius:50rem;
    }
    .query-code{
        color:#d63384;
        font-size:.82rem;
    }
    </style>

</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">Server Monitoring</a>
            <a class="nav-link d-inline-block text-white-50" href="{{ route('servers.index') }}">Servers</a>
            <a class="nav-link d-inline-block text-white-50" href="{{ route('cpu.dashboard') }}">CPU</a>
        </div>
    </nav>
    <main class="container py-4">@yield('content')</main>
    @stack('scripts')
</body>
</html>
