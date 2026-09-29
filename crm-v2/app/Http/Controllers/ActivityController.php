<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActivityController
{
    public function create(Request $request, Company $company)
    {
        $data = $request->validate(['project' => ['nullable', 'integer']]);
        $project = isset($data['project']) ? Project::visibleTo($request->user())->where('company_id', $company->id)->findOrFail($data['project']) : null;

        return view('activities.form', ['company' => $company, 'project' => $project, 'activity' => null, 'revision' => null]);
    }

    public function store(Request $request, Company $company)
    {
        $data = $this->data($request, false);
        $activity = DB::transaction(function () use ($request, $company, $data) {
            Company::query()->lockForUpdate()->findOrFail($company->id);
            $project = isset($data['project_id']) ? Project::visibleTo($request->user())->where('company_id', $company->id)->findOrFail($data['project_id']) : null;
            $activity = Activity::create(['company_id' => $company->id, 'project_id' => $project?->id, 'owner_id' => $project?->owner_id ?? $request->user()->id, 'kind' => $data['kind']]);
            $this->revision($activity, $request, $data);

            return $activity;
        });

        return $this->back($activity);
    }

    public function edit(Request $request, int $activity)
    {
        $activity = Activity::visibleTo($request->user())->findOrFail($activity);
        abort_if($activity->kind === 'system', 403);

        return view('activities.form', ['activity' => $activity, 'company' => $activity->company, 'project' => $activity->project, 'revision' => $activity->latestRevision]);
    }

    public function update(Request $request, int $activity)
    {
        $data = $this->data($request, true);
        $activity = DB::transaction(function () use ($activity, $request, $data) {
            $activity = Activity::visibleTo($request->user())->lockForUpdate()->findOrFail($activity);
            abort_if($activity->kind === 'system', 403);
            abort_unless($activity->version === (int) $data['version'], 409, 'Bu görüşme güncellendi. Sayfayı yenileyin.');
            $activity->version++;
            $activity->save();
            $this->revision($activity, $request, $data);

            return $activity;
        });

        return $this->back($activity);
    }

    public function history(Request $request, int $activity)
    {
        $activity = Activity::visibleTo($request->user())->findOrFail($activity);
        $revisions = $activity->revisions()->with('actor')->where('version', '<', $activity->version)->orderByDesc('version')->paginate(10);

        return view('activities.history', compact('activity', 'revisions'));
    }

    private function data(Request $request, bool $editing): array
    {
        return $request->validate(['kind' => [$editing ? 'prohibited' : 'required', Rule::in(array_keys(Activity::KINDS))], 'project_id' => [$editing ? 'prohibited' : 'nullable', 'integer'], 'version' => [$editing ? 'required' : 'prohibited', 'integer', 'min:1'], 'summary' => ['required', 'string', 'max:500'], 'body' => ['required', 'string', 'max:30000'], 'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i']]);
    }

    private function revision(Activity $activity, Request $request, array $data): void
    {
        $activity->revisions()->create(['actor_id' => $request->user()->id, 'version' => $activity->version, 'summary' => $data['summary'], 'body' => $data['body'], 'occurred_at' => Carbon::createFromFormat('Y-m-d\\TH:i', $data['occurred_at'], 'Europe/Istanbul')->utc(), 'created_at' => now()]);
        DB::table('audit_events')->insert(['actor_id' => $request->user()->id, 'entity_type' => 'activity', 'entity_id' => $activity->id, 'action' => $activity->version === 1 ? 'created' : 'revision_added', 'changed_fields' => json_encode(['summary', 'body', 'occurred_at']), 'created_at' => now()]);
    }

    private function back(Activity $activity)
    {
        return $activity->project_id ? redirect()->route('projects.show', $activity->project_id)->with('status', 'Görüşme kaydedildi; firma akışına da yansıdı.') : redirect()->route('companies.show', $activity->company_id)->with('status','Görüşme kaydedildi.');
    }
}
