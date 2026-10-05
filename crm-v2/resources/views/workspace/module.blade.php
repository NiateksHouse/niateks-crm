@extends('layout')
@section('content')
<div class="page-heading"><div><h1>{{ $info[0] }}</h1><p class="muted">{{ $info[2] }}</p></div><span class="pending-label">Hazırlanıyor · Henüz kayıt alınmıyor</span></div>
<div class="atelier module-preview"><section class="atelier-hero"><div><span class="eyebrow">NIATEKS HOUSE</span><h2>{{ $info[2] }}</h2><p>{{ $info[3] }}</p><a class="button" href="{{ route('projects.index') }}">Projelerime dön</a></div><div class="atelier-emblem" aria-hidden="true"><svg class="icon"><use href="#i-{{ $info[1] }}"/></svg><span>always smile</span></div></section>
@if($module==='orders')<section class="panel"><h2>Siparişin yolculuğu</h2><div class="journey-steps">@foreach(['Hammadde','Üretim','Kalite kontrol','Paketleme','Sevkiyat','Teslimat'] as $phase)<div class="journey-step"><span class="step-icon">{{ $loop->iteration }}</span><b>{{ $phase }}</b></div>@endforeach</div></section>@endif
<div class="hint"><svg class="icon" aria-hidden="true"><use href="#i-info"/></svg><span>Bu bölümün görünümü çalışma alanına eklendi. Kayıt ve işlem bağlantıları tamamlandığında kullanıma açılacak; burada görünen metinler müşteri veya sipariş kaydı değildir.</span></div></div>
@endsection
