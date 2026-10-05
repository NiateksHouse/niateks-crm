<p class="muted">Son kayıt / revizyon en üstte. Görüşmenin kendi tarihi ayrıca gösterilir.</p>
@forelse($activities as $activity)
<article class="card timeline-entry"><p class="eyebrow">{{ \App\Models\Activity::KINDS[$activity->kind] ?? 'Sistem kaydı' }} · {{ $activity->latestRevision->occurred_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p><h3>{{ $activity->latestRevision->summary }}</h3>
@if(request()->routeIs('workspace.activities'))<p><a href="{{ route('companies.show',$activity->company) }}">{{ $activity->company->name }}</a></p>@endif
@if($activity->project)<p><a href="{{ route('projects.show',$activity->project) }}">{{ $activity->project->name }}</a></p>@endif
<details><summary>{{ str_starts_with($activity->kind,'email') ? 'Maili oku' : 'Ayrıntıyı oku' }}</summary><p class="preserve-lines">{{ $activity->latestRevision->body }}</p></details>
<p class="muted">{{ $activity->latestRevision->actor->name }} · Revizyon {{ $activity->version }} · Kayıt: {{ $activity->latestRevision->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
@if($activity->kind !== 'system')<a href="{{ route('activities.edit',$activity) }}">Yeni bilgi / düzeltme ekle</a>@endif
@if($activity->version>1)<p><a href="{{ route('activities.history',$activity) }}">Eski bilgileri göster</a></p>@endif</article>
@empty<div class="card"><p>Henüz görüşme yok. İlk görüşmenizi ekleyerek başlayın.</p></div>@endforelse
@include('partials.pagination',['records'=>$activities])
