<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Niateks House CRM</title>
    <link rel="stylesheet" href="{{ asset('app-alpha22.css') }}">
</head>
<body class="{{ auth()->check() ? 'workspace-page' : 'login-page' }}">
<a class="skip-link" href="#main">İçeriğe geç</a>
@guest<div class="backdrop" aria-hidden="true"></div>@endguest
<header class="site-header">
    <a class="brand" href="{{ auth()->check() ? route('companies.index') : route('login') }}" aria-label="Niateks House ana sayfa"><img src="{{ asset('assets/niateks-house-logo.png') }}" alt="Niateks House" width="190" height="100"></a>
    <span class="environment-label">CRM @if(! app()->environment('production')) · Test ortamı @endif</span>
    @auth<span class="user-name">{{ auth()->user()->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="quiet-button">Çıkış</button></form>@endauth
</header>
@auth
<aside class="sidebar" aria-label="Çalışma alanı">
    <p class="eyebrow">ÇALIŞMA ALANIM</p>
    <nav aria-label="Ana menü">
        <a href="{{ route('companies.index') }}" @if(request()->routeIs('companies.*') && !request('role')) aria-current="page" @endif><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 21V3h16v18M2 21h20M8 7h2m4 0h2M8 11h2m4 0h2M10 21v-6h4v6"/></svg> Firmalar</a>
        <a href="{{ route('companies.index', ['role'=>'customer']) }}" @if(request('role')==='customer') aria-current="page" @endif><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m1-16a3 3 0 0 1 0 6m3 10v-3a6 6 0 0 0-2-4"/></svg> Müşteriler</a>
        <a href="{{ route('companies.index', ['role'=>'supplier']) }}" @if(request('role')==='supplier') aria-current="page" @endif><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 7 9-4 9 4v10l-9 4-9-4V7Zm0 0 9 4 9-4m-9 4v10M7 5l10 4"/></svg> Tedarikçiler</a>
        <a href="{{ route('projects.index') }}" @if(request()->routeIs('projects.*')) aria-current="page" @endif><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 5h7l2 3h9v12H3z"/></svg> Projeler</a>
    </nav>
    <p class="sidebar-note">Firma bilgileri ortak.<br>Her yeni bilgi, geçmişi korunarak kaydedilir.</p>
    <p class="version">2.0.0-alpha.23</p>
</aside>
@endauth
<main id="main">
    @if(session('status'))<p class="status-message" role="status">{{ session('status') }}</p>@endif
    @if(! request()->routeIs('login') && $errors->any())
        <div role="alert"><p>Kaydetmeden önce kontrol edin:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
@guest<footer class="login-footer"><span>NIATEKS HOUSE</span><span>2.0.0-alpha.23</span></footer>@endguest
</body>
</html>
