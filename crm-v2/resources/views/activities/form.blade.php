@extends('layout')
@section('content')
<a href="{{ $project ? route('projects.show',$project) : route('companies.show',$company) }}">← Geri</a><h1>{{ $activity ? 'Yeni görüşme bilgisi' : 'Görüşme ekle' }}</h1><p>{{ $company->name }} @if($project) · {{ $project->name }} @endif</p><p>Akışta kısa özet görünür. Tam metin istendiğinde açılır.</p>
<form class="card" method="post" action="{{ $activity ? route('activities.update',$activity) : route('activities.store',$company) }}">@csrf
@if($activity)@method('PUT')<input type="hidden" name="version" value="{{ old('version',$activity->version) }}">@else
@if($project)<input type="hidden" name="project_id" value="{{ $project->id }}">@endif
<label for="kind">Görüşme türü</label><select id="kind" name="kind">@foreach(\App\Models\Activity::KINDS as $key=>$label)<option value="{{ $key }}" @selected(old('kind')===$key)>{{ $label }}</option>@endforeach</select>@endif
<label for="occurred_at">Görüşme tarihi ve saati (Türkiye saati)</label><input id="occurred_at" name="occurred_at" type="datetime-local" required value="{{ old('occurred_at',($revision?->occurred_at ?? now())->timezone('Europe/Istanbul')->format('Y-m-d\\TH:i')) }}">
<label for="summary">Kısa özet</label><textarea id="summary" name="summary" maxlength="500" rows="3" required>{{ old('summary',$revision?->summary) }}</textarea>
<label for="body">Görüşme notları / e-postanın tam metni</label><textarea id="body" name="body" maxlength="30000" rows="10" required>{{ old('body',$revision?->body) }}</textarea>
<p class="muted">E-postayı buraya yapıştırabilirsiniz. Bu sürümde özet elle girilir; otomatik AI analizi henüz bağlı değildir.</p><button>{{ $activity ? 'Yeni revizyonu kaydet' : 'Görüşmeyi kaydet' }}</button></form>
@endsection
