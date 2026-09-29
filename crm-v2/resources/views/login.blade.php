@extends('layout')
@section('content')
<div class="login-shell">
    <section class="intro" aria-label="KOZA">
        <p class="eyebrow">İLİŞKİLERDEN ÜRETİME</p>
        <h1>Her detay,<br>bir bütüne ait.</h1>
        <p>Müşterileriniz, projeleriniz ve ürünleriniz<br>aynı çalışma alanında.</p>
    </section>
    <section class="login-panel" aria-labelledby="login-title">
        <p class="eyebrow">KOZA ÇALIŞMA ALANI</p>
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
        <p><a href="{{ route('activation.create') }}">Davet kodum var · İlk giriş</a></p>
        <p class="login-help">Giriş bilgilerinizi bilmiyorsanız yöneticinizle görüşün.</p>
        @if(! app()->environment('production'))<p class="test-note">Test ortamı · Deneme kayıtlarıyla çalışın.</p>@endif
    </section>
</div>
@endsection
