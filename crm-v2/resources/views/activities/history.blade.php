@extends('layout')
@section('content')
<a href="{{ $activity->project_id ? route('projects.show',$activity->project_id) : route('companies.show',$activity->company_id) }}">← Güncel akışa dön / Geçmişi gizle</a><h1>Görüşme geçmişi</h1>
@foreach($revisions as $revision)<article class="card"><p>Revizyon {{ $revision->version }} · {{ $revision->actor->name }} · {{ $revision->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p><h2>{{ $revision->summary }}</h2><p>Görüşme: {{ $revision->occurred_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p><p class="preserve-lines">{{ $revision->body }}</p></article>@endforeach
@include('partials.pagination',['records'=>$revisions])
@endsection
