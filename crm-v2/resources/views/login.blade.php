@extends('layout')
@section('content')
<div class="login-shell">
    <section class="login-scene" aria-label="Niateks House tekstil atölyesi">
        <img src="{{ asset('assets/login-bg.jpg') }}" alt="Doğal kumaşlarla hazırlanmış, ahşap çalışma masalı Niateks House atölyesi" fetchpriority="high" width="1536" height="1024">
        <div class="scene-caption"><p>NIATEKS HOUSE</p><h1>Birlikte güzel<br>işler üretelim.</h1><p>İnsan, tasarım ve emeğin buluştuğu yer.</p></div>
    </section>
    <section class="login-panel" aria-labelledby="login-title">
        <p class="eyebrow">ÇALIŞMA ALANINIZ</p>
        <h2 id="login-title">Hoş geldiniz.</h2>
        <p class="login-intro">Kaldığınız yerden devam etmek için giriş yapın.</p>
        @if($errors->any())<div class="login-error" role="alert">Kullanıcı adı veya parolanızı kontrol edip tekrar deneyin.</div>@endif
        <form method="post" action="{{ route('login') }}">
            @csrf
            <label for="username">Kullanıcı adı</label>
            <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" spellcheck="false" required maxlength="80">
            <label for="password">Parola</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="256">
            <button class="login-submit">Giriş yap <span aria-hidden="true">→</span></button>
        </form>
        <p class="login-help">Giriş bilgilerinizi bilmiyorsanız yöneticinizle görüşün.</p>
        @if(! app()->environment('production'))<p class="test-note">Test ortamı · Deneme kayıtlarıyla çalışın.</p>@endif
    </section>
</div>
@endsection
