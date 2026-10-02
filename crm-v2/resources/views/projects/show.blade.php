@extends('layout')
@section('content')
<a href="{{ route('companies.show',$project->company) }}">← {{ $project->company->name }}</a><h1>{{ $project->name }}</h1><p><span class="badge">{{ \App\Models\Project::STAGES[$project->stage] }}</span> Sorumlu: {{ $project->owner->name }}</p>
<div class="detail-grid"><section class="card"><h2>Müşterinin beklentisi</h2><p class="preserve-lines">{{ $project->brief ?: 'Henüz açıklama eklenmedi.' }}</p><a class="button" href="{{ route('projects.edit',$project) }}">Yeni bilgi / aşama değişikliği</a><p><a href="{{ route('projects.history',$project) }}">Geçmiş bilgileri göster</a></p></section><section class="card"><h2>Sıradaki adım</h2><p>Telefon, toplantı veya e-postayı kaydedin. Görüşme bu projenin yanında firma kartına da yansır.</p><a class="button" href="{{ route('activities.create',['company'=>$project->company_id,'project'=>$project->id]) }}">Görüşme ekle</a></section></div>
<h2>Projenin görüşme akışı</h2>@include('partials.timeline')
@endsection
