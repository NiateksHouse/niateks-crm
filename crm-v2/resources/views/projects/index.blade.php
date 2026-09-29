@extends('layout')
@section('content')
<p class="eyebrow">BİR SONRAKİ ADIM</p><h1>{{ $company ? $company->name.' · Projeler' : 'Projeler' }}</h1>
<p>{{ auth()->user()->isAdmin() ? 'Ekibin projelerini buradan takip edebilirsiniz.' : 'Size ait projeler burada listelenir.' }}</p>
@if($company)<a class="button" href="{{ route('projects.create',$company) }}">Yeni proje</a>@else<p>Yeni proje açmak için <a href="{{ route('companies.index') }}">firma kartını seçin</a>.</p>@endif
<nav aria-label="Proje aşaması"><a href="{{ route('projects.index', array_filter(['company'=>$company?->id])) }}">Tümü</a>@foreach(\App\Models\Project::STAGES as $key=>$label)<a href="{{ route('projects.index',array_filter(['company'=>$company?->id,'stage'=>$key])) }}" @if(request('stage')===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
<div class="project-grid">@forelse($projects as $project)<article class="card"><p class="eyebrow">{{ \App\Models\Project::STAGES[$project->stage] }}</p><h2><a href="{{ route('projects.show',$project) }}">{{ $project->name }}</a></h2><p>{{ $project->company->name }}</p><p class="muted">Sorumlu: {{ $project->owner->name }}</p><a href="{{ route('projects.show',$project) }}">Projeyi aç →</a></article>@empty<div class="card"><h2>İlk projeyle başlayın.</h2><p>Firma kartından proje açın; o işe ait görüşmeleri tek yerde toplayın.</p></div>@endforelse</div>
@include('partials.pagination',['records'=>$projects])
<p class="muted">Teklif ve sipariş aşamaları, ilgili modüller bağlandığında açılacak.</p>
@endsection
