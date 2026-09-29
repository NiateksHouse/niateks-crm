<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Niateks House CRM</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body class="{{ request()->routeIs('login') ? 'login-page' : 'workspace-page' }}">
<a class="skip-link" href="#main">İçeriğe geç</a>
<header>
    <a class="brand" href="{{ auth()->check() ? route('companies.index') : route('login') }}" aria-label="Niateks House ana sayfa"><img src="{{ asset('assets/niateks-house-logo.png') }}" alt="Niateks House" width="190" height="100"></a>
    <span class="environment-label">CRM @if(! app()->environment('production')) · Test ortamı @endif</span>
    @auth<form method="post" action="{{ route('logout') }}">@csrf<button>Çıkış</button></form>@endauth
</header>
<main id="main">
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if(! request()->routeIs('login') && $errors->any())
        <div role="alert"><p>Kaydetmeden önce kontrol edin:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
</body>
</html>
