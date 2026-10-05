<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>KOZA · Niateks House</title>
<link rel="stylesheet" href="{{ asset('koza-v1.css').'?v=2.1.1' }}">
<script src="{{ asset('koza-language-v1.js').'?v=2.1.1' }}" defer></script>
<script src="{{ asset('koza-v1.js').'?v=2.1.1' }}" defer></script>
</head>
<body>
<a class="skip-link" href="#page">İçeriğe geç / Skip to content</a>
<div class="app">
<aside class="sidebar" id="sidebar" aria-label="KOZA">
<div class="brand" role="img" aria-label="KOZA · Niateks House"></div>
<nav class="nav" id="navigation"></nav>
<div class="side-foot"><div class="house-brand" role="img" aria-label="Niateks House — Home textiles with a soul"></div><span>KOZA · 2.1.2-test.1</span><br><span data-i18n="testEnvironment">Test ortamı</span><br><a href="/companies" data-i18n="existingWorkspace">Mevcut kayıt ekranları</a></div>
</aside>
<main class="main">
<div class="topbar">
<button class="chip mobile-menu" id="menuToggle" aria-controls="sidebar" aria-expanded="false" aria-label="Menü / Menu">☰</button>
<form class="search" id="searchForm"><span aria-hidden="true">⌕</span><input id="search" name="q" type="search" aria-label="Ara / Search" autocomplete="off"><span class="subtle key-hint">⌘ K</span></form>
<button class="notification-button" id="notificationButton" aria-label="Öncelikler / Priorities"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 17h14l-2-3V9a5 5 0 0 0-10 0v5l-2 3ZM10 20h4M12 2v2"/></svg></button><div class="role-switch"><label class="sr-only" for="localeSelect">Dil / Language</label><select id="localeSelect"><option value="tr-TR">TR</option><option value="en">ENG</option></select><button class="profile-control" id="profileButton" aria-label="Profil / Profile"><span class="avatar">K</span><span class="profile-name"></span><span aria-hidden="true">⌄</span></button></div><div class="brand-values">MEANINGFUL<br>BEAUTIFUL<br>DURABLE</div>
</div>
<div class="page" id="page" tabindex="-1" aria-busy="true"><div class="card" role="status">KOZA yükleniyor / Loading KOZA…</div></div>
</main>
</div>
<dialog id="detailDialog" class="detail-dialog" aria-labelledby="detailTitle"><div id="detailContent"></div></dialog>
<dialog id="formDialog" class="form-dialog" aria-labelledby="formTitle"><div id="formContent"></div></dialog>
<dialog id="pickerDialog" class="picker-dialog" aria-labelledby="pickerTitle"><div id="pickerContent"></div></dialog>
<div class="toast hidden" id="toast" role="status" aria-live="polite"></div>
<noscript>KOZA çalışma alanını kullanmak için JavaScript açık olmalıdır. / JavaScript is required for the KOZA workspace.</noscript>
</body>
</html>
