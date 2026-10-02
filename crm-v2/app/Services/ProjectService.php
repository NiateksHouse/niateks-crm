<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function save(User $actor, Company $company, array $data, ?Project $project = null): Project
    {
        return DB::transaction(function () use ($actor, $company, $data, $project) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            abort_unless($actor->active, 403);
            if ($project) {
                $project = Project::visibleTo($actor)->lockForUpdate()->findOrFail($project->id);
                abort_unless($project->company_id === $company->id, 404);
                abort_unless($project->version === (int) $data['version'], 409, 'Bu proje güncellendi. Sayfayı yenileyin.');
                $previous = array_search($project->stage, array_keys(Project::STAGES), true);
                $next = array_search($data['stage'], array_keys(Project::STAGES), true);
                if ($next < $previous && empty($data['reason'])) {
                    throw ValidationException::withMessages(['reason' => 'Önceki aşamaya dönme nedenini yazın.']);
                }
            } else {
                $project = new Project(['company_id' => $company->id, 'owner_id' => $actor->id, 'version' => 0]);
            }
            if (Project::where('company_id', $company->id)->where('owner_id', $project->owner_id)->where('name', $data['name'])->when($project->exists, fn ($q) => $q->where('id', '!=', $project->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'Bu firmada aynı isimde bir projeniz var. Mevcut projeyi açın.']);
            }
            $project->name = $data['name'];
            $project->brief = $data['brief'] ?? null;
            $project->stage = $project->exists ? $data['stage'] : 'opened';
            $project->version++;
            $project->save();
            $project->revisions()->create(['actor_id' => $actor->id, 'version' => $project->version, 'snapshot' => $project->only(['name', 'brief', 'stage']) + ['reason' => $data['reason'] ?? null], 'created_at' => now()]);
            $event = Activity::create(['company_id' => $company->id, 'project_id' => $project->id, 'owner_id' => $project->owner_id, 'kind' => 'system']);
            $event->revisions()->create(['actor_id' => $actor->id, 'version' => 1, 'summary' => $project->version === 1 ? 'Proje açıldı' : 'Proje bilgisi güncellendi · Revizyon '.$project->version, 'body' => Project::STAGES[$project->stage], 'occurred_at' => now(), 'created_at' => now()]);
            DB::table('audit_events')->insert(['actor_id' => $actor->id, 'entity_type' => 'project', 'entity_id' => $project->id, 'action' => $project->version === 1 ? 'created' : 'revision_added', 'changed_fields' => json_encode(['name', 'brief', 'stage']), 'created_at' => now()]);

            return $project;
        });
    }
}
