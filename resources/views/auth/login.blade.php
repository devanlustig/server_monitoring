<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Server Monitoring</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
        <div class="card-body text-center p-4">
            <h4 class="card-title fw-bold mb-3">Server Monitoring</h4>
            <p class="text-muted mb-4">
                Silahkan Login
            </p>

            @if(session('error'))
                <div class="alert alert-danger mb-4 text-start">
                    {{ session('error') }}
                </div>
            @endif

            <a href="{{ route('google.login') }}" class="btn btn-primary w-100 py-2">
                Continue with Google
            </a>
        </div>
    </div>
</body>
</html>
