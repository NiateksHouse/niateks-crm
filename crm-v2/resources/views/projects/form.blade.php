@extends('layout')
@section('content')
<a href="{{ $project->exists ? route('projects.show',$project) : route('companies.show',$company) }}">← Geri</a><h1>{{ $project->exists ? 'Yeni proje bilgisi' : 'Proje aç' }}</h1><p>{{ $company->name }} · Önce bu işin adını ve müşterinin beklentisini yazın.</p>
<form class="card" method="post" action="{{ $project->exists ? route('projects.update',$project) : route('projects.store',$company) }}">@csrf
@if($project->exists)@method('PUT')<input type="hidden" name="version" value="{{ old('version',$project->version) }}">@endif
<label for="name">Proje adı</label><input id="name" name="name" required maxlength="180" value="{{ old('name',$project->name) }}">
<label for="brief">Müşteri ne istiyor?</label><textarea id="brief" name="brief" rows="5" maxlength="10000">{{ old('brief',$project->brief) }}</textarea>
@if($project->exists)<label for="stage">Şu an hangi aşamada?</label><select id="stage" name="stage">@foreach(\App\Models\Project::STAGES as $key=>$label)<option value="{{ $key }}" @selected(old('stage',$project->stage)===$key)>{{ $label }}</option>@endforeach</select><label for="reason">Değişiklik açıklaması</label><textarea id="reason" name="reason" maxlength="1000" rows="3">{{ old('reason') }}</textarea><p class="muted">Önceki aşamaya dönüyorsanız nedenini yazın. Eski bilgiler geçmişte kalır.</p>@endif
<button>{{ $project->exists ? 'Yeni revizyonu kaydet' : 'Projeyi oluştur' }}</button></form>
@endsection
