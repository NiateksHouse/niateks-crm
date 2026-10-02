<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Niateks House CRM</title><link rel="stylesheet" href="{{ asset('workspace-alpha26.css') }}"><script src="{{ asset('workspace-alpha26.js') }}" defer></script><link rel="stylesheet" href="{{ asset('documents-alpha27.css') }}"></head>
<body data-companion-user="{{ auth()->id() }}">
<a class="skip-link" href="#main">İçeriğe geç</a>
@include('partials.icons')
<aside class="sidebar" aria-label="Çalışma alanı"><a href="{{ route('workspace.home') }}" aria-label="Niateks House ana sayfa"><img class="logo" src="{{ asset('assets/niateks-house-logo.png') }}" alt="NIATEKS HOUSE" width="168" height="88"></a>
<nav aria-label="Ana menü">
@foreach(['home'=>['Başlangıç','grid'],'companies'=>['Firmalar','people'],'projects'=>['Projeler','grid'],'products'=>['Ürünler','apron'],'activities'=>['Görüşmeler','chat'],'tasks'=>['İş takibi','check'],'samples'=>['Numuneler','tea-towel'],'quotes'=>['Teklifler','file'],'orders'=>['Sipariş & üretim','box'],'finance'=>['Finans / Nigar','calc'],'documents'=>['Dokümanlar','file']] as $key=>$item)
@php($url = in_array($key,['companies','projects','documents']) ? route($key.'.index') : ($key==='home' ? route('workspace.home') : ($key==='activities' ? route('workspace.activities') : route('workspace.module',$key))))
@php($active = request()->routeIs($key.'.*') || request()->routeIs('workspace.'.$key) || request()->route('module')===$key)
<a href="{{ $url }}" @if($active) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="#i-{{ $item[1] }}"/></svg>{{ $item[0] }}</a>
@endforeach
</nav>
<details class="support-nav" @if(request()->routeIs('contacts.*','matching.*')) open @endif><summary>Firma araçları</summary><a href="{{ route('contacts.index') }}">Kişiler</a><a href="{{ route('matching.index') }}">Firma sözlüğü</a></details>
<div class="bottom"><p>Firma bilgileri ortak.<br>İşleriniz yetkinize göre görünür.</p><p>2.0.0-alpha.27 · v1.6.0 tasarımı</p></div></aside>
<div class="workspace"><header class="topbar"><span class="demo-label">@if(app()->environment('production')) CRM @else TEST ORTAMI @endif</span><button type="button" id="break-demo">☕ Mola mesajı</button><b>{{ auth()->user()->name }}</b><form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Çıkış</button></form></header>
<details class="music-box"><summary>♫ Çalışma müziği · Jazz / Relax / Akustik</summary><div class="music-controls"><label for="music-style">Müzik seç<select id="music-style"><option value="jazz">Jazz</option><option value="relax">Relax</option><option value="acoustic">Akustik</option></select></label><button type="button" id="music-play">Çal</button><button type="button" id="music-stop">Durdur</button><button type="button" id="music-mute" aria-pressed="false">Sessize al</button><label for="music-volume">Ses <span id="music-volume-value">30%</span><input id="music-volume" type="range" min="0" max="100" value="30"></label></div><span id="music-status" role="status">Kapalı · Siz başlatmadan çalmaz</span><p>Üç özgün enstrümantal müzik önizlemesi. Canlı radyo değildir. Sayfa değişiminde durur.</p></details>
<main id="main" class="page" tabindex="-1">
@if(session('status'))<div class="status-message" role="status"><img src="{{ asset('assets/always-smile.png') }}" alt="Always smile" width="48" height="48"><span>{{ session('status') }}</span></div>@endif
@if($errors->any())<div class="error-panel" role="alert"><strong>Kaydetmeden önce kontrol edin:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer class="page-footer">NIATEKS HOUSE · always smile<p>Yardım Merkezi son geliştirme aşamasında eklenecek.</p></footer></main></div>
<aside id="break-card" class="break-card" aria-label="Mola arkadaşı" hidden><div class="break-heading"><img src="{{ asset('assets/always-smile.png') }}" alt="Always smile"><span id="break-kind">BİLGİSAYARINDAN MESAJ VAR</span></div><p id="break-copy" role="status"></p><div class="break-actions"><button type="button" id="break-close">Tamam</button><button type="button" id="break-later">10 dakika sonra</button><button type="button" id="break-today">Bugün gösterme</button></div><small>Yalnız CRM içindeki etkin süreye göre; günde en fazla 4 otomatik mesaj.</small></aside>
</body></html>
