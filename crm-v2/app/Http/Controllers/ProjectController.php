<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController
{
    public function index(Request $request)
    {
        $data = $request->validate(['company'=>['nullable','integer'], 'stage'=>['nullable',Rule::in(array_keys(Project::STAGES))]]);
        $company = isset($data['company']) ? Company::findOrFail($data['company']) : null;
        $projects = Project::visibleTo($request->user())->with(['company','owner'])->when($company, fn ($q) => $q->where('company_id', $company->id))->when($data['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))->latest('updated_at')->paginate(25)->withQueryString();
        return view('projects.index', compact('projects','company'));
    }

    public function create(Company $company)
    {
        return view('projects.form', ['company'=>$company,'project'=>new Project]);
    }

    public function store(Request $request, Company $company, ProjectService $service)
    {
        $project = $service->save($request->user(), $company, $this->data($request, false));
        return redirect()->route('projects.show', $project)->with('status','Proje açıldı. Görüşmeleri bu proje altında kaydedebilirsiniz.');
    }

    public function show(Request $request, int $project)
    {
        $project = Project::visibleTo($request->user())->with(['company','owner'])->findOrFail($project);
        $activities = Activity::visibleTo($request->user())->where('project_id', $project->id)->with(['latestRevision.actor','project'])->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString();
        return view('projects.show', compact('project','activities'));
    }

    public function edit(Request $request, int $project)
    {
        $project = Project::visibleTo($request->user())->findOrFail($project);
        return view('projects.form', ['project'=>$project,'company'=>$project->company]);
    }

    public function update(Request $request, int $project, ProjectService $service)
    {
        $project = Project::visibleTo($request->user())->findOrFail($project);
        $service->save($request->user(), $project->company, $this->data($request, true), $project);
        return redirect()->route('projects.show', $project)->with('status','Yeni revizyon kaydedildi. Eski bilgiler korundu.');
    }

    public function history(Request $request, int $project)
    {
        $project = Project::visibleTo($request->user())->findOrFail($project);
        $revisions = $project->revisions()->with('actor')->where('version','<',$project->version)->orderByDesc('version')->paginate(10);
        return view('projects.history', compact('project','revisions'));
    }

    private function data(Request $request, bool $editing): array
    {
        return $request->validate(['name'=>['required','string','max:180'],'brief'=>['nullable','string','max:10000'],'stage'=>[$editing ? 'required' : 'prohibited',Rule::in(array_keys(Project::STAGES))],'version'=>[$editing ? 'required' : 'prohibited','integer','min:1'],'reason'=>['nullable','string','max:1000']]);
    }
}
