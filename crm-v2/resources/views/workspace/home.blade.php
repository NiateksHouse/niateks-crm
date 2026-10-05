@extends('layout')
@section('content')
<div class="page-heading"><div><h1>Bugün nereden başlayalım?</h1><p class="muted">Firma kartından projeye, görüşmeden bir sonraki adıma.</p></div><a class="button primary" href="{{ route('companies.create') }}">+ Firma kartı aç</a></div>
<div class="welcome"><span class="eyebrow">NIATEKS ÇALIŞMA ALANI</span><h2>Her işin bir sonraki adımı belli olsun.</h2><p>Önce firmayı bulun veya yeni bir kart açın. Talebi projeye dönüştürün; görüşmeleri o işin altında toplayın.</p><a class="button primary" href="{{ route('companies.index') }}">Firma kartlarına git <svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div>
<div class="stats">
@foreach([['companies.index',$companyCount,'Firma','people'],['projects.index',$projectCount,'Görünen proje','grid'],['workspace.activities',$activityCount,'Görüşme','chat'],['matching.index',null,'Firma sözlüğü','file']] as $stat)
<a class="stat" href="{{ route($stat[0]) }}"><svg class="icon" aria-hidden="true"><use href="#i-{{ $stat[3] }}"/></svg>@if($stat[1]!==null)<strong>{{ $stat[1] }}</strong>@else<strong>↗</strong>@endif<span>{{ $stat[2] }}</span></a>@endforeach
</div>
<section class="panel"><h2>Son çalışılan projeler</h2>@forelse($projects as $project)<div class="task-row"><div><svg class="icon" aria-hidden="true"><use href="#i-grid"/></svg><span><strong>{{ $project->name }}</strong><small>{{ $project->company->name }} · {{ \App\Models\Project::STAGES[$project->stage] }}</small></span></div><a class="button" href="{{ route('projects.show',$project) }}">Projeyi aç</a></div>@empty<p>İlk projenizi firma kartından oluşturabilirsiniz.</p>@endforelse</section>
<details class="panel"><summary>Çalışma alanının durumu</summary><p>Firma kartları, kişiler, projeler, görüşmeler ve firma sözlüğü veritabanına bağlıdır. Diğer ana menülerde hazırlık durumu açıkça gösterilir; henüz bu bölümlerde kayıt alınmaz.</p></details>
@endsection
