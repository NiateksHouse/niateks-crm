@extends('layout')
@section('content')
<p class="eyebrow">BİR SONRAKİ ADIM</p><h1>{{ $company ? $company->name.' · Projeler' : 'Projeler' }}</h1>
<p>{{ auth()->user()->isAdmin() ? 'Ekibin projelerini buradan takip edebilirsiniz.' : 'Size ait projeler burada listelenir.' }}</p>
@if($company)<a class="button" href="{{ route('projects.create',$company) }}">Yeni proje</a>@else<p>Yeni proje açmak için <a href="{{ route('companies.index') }}">firma kartını seçin</a>.</p>@endif
<nav aria-label="Proje aşaması"><a href="{{ route('projects.index', array_filter(['company'=>$company?->id])) }}">Tümü</a>@foreach(\App\Models\Project::STAGES as $key=>$label)<a href="{{ route('projects.index',array_filter(['company'=>$company?->id,'stage'=>$key])) }}" @if(request('stage')===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
<div class="kanban" aria-label="Proje aşamaları">@foreach(\App\Models\Project::STAGES as $stage=>$label)<section class="kanban-column"><h2>{{ $label }} <span class="badge">{{ $projects->where('stage',$stage)->count() }}</span></h2>@forelse($projects->where('stage',$stage) as $project)<article class="card"><h3><a href="{{ route('projects.show',$project) }}">{{ $project->name }}</a></h3><p>{{ $project->company->name }}</p><p class="muted">Sorumlu: {{ $project->owner->name }}</p><a href="{{ route('projects.show',$project) }}">Projeyi aç →</a></article>@empty<p class="muted">Bu sayfada bu aşamada proje yok.</p>@endforelse</section>@endforeach</div><p class="muted">Kartı açarak proje bilgilerini güncelleyebilirsiniz. Sütunlar bu sayfadaki kayıtları gösterir.</p>
@include('partials.pagination',['records'=>$projects])
<p class="muted">Teklif ve sipariş aşamaları, ilgili modüller bağlandığında açılacak.</p>
@endsection
