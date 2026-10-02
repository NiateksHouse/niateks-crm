@extends('layout')
@section('content')
<section class="card narrow" aria-labelledby="activation-title">
    <p class="eyebrow">İLK GİRİŞ</p>
    <h1 id="activation-title">Hesabınızı etkinleştirin</h1>
    <p>Size özel davet kodunu girin ve kendi parolanızı belirleyin. Kod bir kez kullanılabilir ve 24 saat geçerlidir.</p>
    <form method="post" action="{{ route('activation.store') }}">
        @csrf
        <label for="invitation_code">Davet kodu</label>
        <input id="invitation_code" name="invitation_code" type="password" autocomplete="off" required minlength="64" maxlength="64" aria-describedby="code-help">
        <p id="code-help" class="login-help">Size güvenli olarak verilen kodu buraya yapıştırın. Kodu kimseyle paylaşmayın.</p>
        <label for="new-password">Yeni parola</label>
        <input id="new-password" name="password" type="password" autocomplete="new-password" required minlength="14" maxlength="72" aria-describedby="password-help">
        <p id="password-help" class="login-help">En az 14 karakter; büyük ve küçük harf, sayı ve simge kullanın.</p>
        <label for="password_confirmation">Yeni parola tekrar</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="14" maxlength="72">
        <button>Hesabımı etkinleştir</button>
    </form>
    <p><a href="{{ route('login') }}">Giriş ekranına dön</a></p>
</section>
@endsection
