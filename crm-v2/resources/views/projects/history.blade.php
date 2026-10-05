@extends('layout')
@section('content')
<a href="{{ route('projects.show',$project) }}">← Güncel projeye dön / Geçmişi gizle</a><h1>Proje bilgi geçmişi</h1>
@forelse($revisions as $revision)<article class="card"><p>Revizyon {{ $revision->version }} · {{ $revision->actor->name }} · {{ $revision->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p><h2>{{ $revision->snapshot['name'] }}</h2><p>{{ \App\Models\Project::STAGES[$revision->snapshot['stage']] }}</p><p class="preserve-lines">{{ $revision->snapshot['brief'] }}</p><p>{{ $revision->snapshot['reason'] }}</p></article>@empty<p>Henüz önceki bir revizyon yok.</p>@endforelse
@include('partials.pagination',['records'=>$revisions])
@endsection
