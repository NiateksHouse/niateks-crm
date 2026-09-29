@extends('layout')
@section('content')
<h1>Olası mükerrer kayıtlar</h1><p><strong>{{ $data['name'] }}</strong> için inceleme. Puan istatistiksel olasılık değildir; eşleşen bilgilerin toplamıdır. Hiçbir kayıt otomatik birleştirilmez.</p>
@if(!$result['matches'])<p class="status-message">Görülebilen kayıtlarda eşleşme bulunamadı.</p>@endif
@if($result['suppressed'])<p>{{ $result['suppressed'] }} eşleşme, önceki “farklı” kararınız nedeniyle tekrar uyarı olarak gösterilmedi.</p>@endif
<form method="post" action="{{ route('matching.decide') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
@foreach($result['matches'] as $m)<section class="card"><h2>{{ $m['name'] }} · {{ $m['duplicate_confidence_score'] }}/100</h2>
<p>{{ ['strong'=>'Çok yüksek eşleşme — güçlü mükerrer uyarısı','review'=>'Olası mükerrer — inceleyin','low'=>'Düşük eşleşme — ayrı kayıt oluşturabilirsiniz'][$m['level']] }}</p>
<ul>@foreach($m['reasons'] as $reason)<li>✓ {{ $reason['text'] }} (+{{ $reason['points'] }})</li>@endforeach @foreach($m['conflicts'] as $conflict)<li>Fark: {{ $conflict }}</li>@endforeach</ul>
<a target="_blank" rel="noopener" href="{{ route($type==='company'?'companies.show':'contacts.show',$m['id']) }}">Mevcut kaydı aç</a>
<label><input type="radio" name="target_id" value="{{ $m['id'] }}"> Bu kayıtla aynı olduğunu doğruluyorum</label>
<label><input type="checkbox" name="different[]" value="{{ $m['id'] }}"> {{ $sourceId ? 'İncelediğim kayıt' : 'Yeni oluşturacağım kayıt' }}, bu kayıttan farklıdır</label>
</section>@endforeach
<label for="reason">Karar açıklaması</label><textarea id="reason" name="reason" required minlength="3" maxlength="1000"></textarea>
<p>“Aynı” seçeneğinde firma adının özgün yazımı onaylı varyasyon olarak saklanır; mevcut firmanın adı değişmez. “Farklı” işaretleri yalnız yeni kayıt seçeneğinde kaydedilir.</p>
@if($result['matches'])<button name="action" value="same">Seçtiğim mevcut kaydı kullan ve onayı kaydet</button>@endif
<button name="action" value="new">{{ $sourceId ? 'Farklılık kararlarını kaydet' : 'Ayrı yeni kayıt oluştur' }}</button></form><p><a href="{{ route($type==='company'?'companies.create':'contacts.create') }}">Bilgileri yeniden gir / iptal et</a></p>
@endsection
