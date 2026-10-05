@extends('layout')
@section('content')
<section class="card narrow" aria-labelledby="activation-title">
    <p class="eyebrow">İLK GİRİŞ</p>
    <h1 id="activation-title">Hesabınızı etkinleştirin</h1>
    <p>Size özel davet kodunu girin ve kendi parolanızı belirleyin. Kod bir kez kullanılabilir ve 24 saat geçerlidir.</p>
    <form method="post" action="{{ route('activation.store') }}">
        @csrf
        <label for="invitation_code">Davet kodu</label>
        <input id="invitation_code" name="invitation_code" type="password" autocomplete="off" required maxlength="64" aria-describedby="code-help" aria-invalid="{{ $errors->has('invitation_code') ? 'true' : 'false' }}">
        <p id="code-help" class="login-help">Size güvenli olarak verilen 64 karakterlik kodu eksiksiz yapıştırın. Kodu kimseyle paylaşmayın.</p>
        <label for="new-password">Yeni parola</label>
        <input id="new-password" name="password" type="password" autocomplete="new-password" required maxlength="72" aria-describedby="password-help" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
        <p id="password-help" class="login-help">En az 14 karakter; büyük ve küçük harf, sayı ve simge kullanın.</p>
        <label for="password_confirmation">Yeni parola tekrar</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="72" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}">
        <button type="submit">Hesabımı etkinleştir</button>
    </form>
    <p><a href="{{ route('login') }}">Giriş ekranına dön</a></p>
</section>
@endsection
