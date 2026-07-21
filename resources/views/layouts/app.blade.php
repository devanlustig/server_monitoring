<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Server Monitoring') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">Server Monitoring</a>
            <a class="nav-link d-inline-block text-white-50" href="{{ route('cpu.dashboard') }}">CPU</a>
        </div>
    </nav>
    <main class="container py-4">@yield('content')</main>
</body>
</html>
