<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Activity;
use App\Models\Company;
use App\Models\SupplyCategory;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyController
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Company::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', 'in:customer,supplier'], 'category' => ['nullable', 'integer', 'exists:supply_categories,id']]);
        $q = trim($data['q'] ?? '');
        $companies = Company::query()->when($data['role'] ?? null, fn ($query, $role) => $query->whereJsonContains('roles', $role))->when($data['category'] ?? null, fn ($query, $id) => $query->whereJsonContains('roles', 'supplier')->whereHas('supplyCategories', fn ($c) => $c->where('supply_categories.id', $id)))->when($q !== '', fn ($query) => $query->where('name', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%'))->orderBy('name')->paginate(25)->withQueryString();

        return view('companies.index', ['companies' => $companies, 'q' => $q, 'categories' => SupplyCategory::orderBy('name')->get()]);
    }

    public function create()
    {
        Gate::authorize('create', Company::class);

        return view('companies.form', ['company' => new Company, 'categories' => SupplyCategory::orderBy('name')->get()]);
    }

    public function show(Request $request, Company $company)
    {
        Gate::authorize('view', $company);

        $activities = Activity::visibleTo($request->user())->where('company_id', $company->id)->with(['latestRevision.actor', 'project'])->latest('updated_at')->orderByDesc('id')->paginate(20);

        return view('companies.show', compact('company', 'activities'));
    }

    public function edit(Company $company)
    {
        Gate::authorize('update', $company);

        return view('companies.form', ['company' => $company, 'categories' => SupplyCategory::orderBy('name')->get()]);
    }

    public function history(Company $company)
    {
        Gate::authorize('view', $company);
        $revisions = $company->revisions()->with('actor:id,name')->where('version', '<', $company->version)->orderByDesc('version')->paginate(10);

        return view('companies.history', compact('company', 'revisions'));
    }

    public function store(CompanyRequest $request, CompanyService $service)
    {
        $data = $request->validated();
        $result = app(\App\Services\DuplicateMatcher::class)->find('company', $data, $request->user());
        if ($request->input('intent') === 'review' || collect($result['matches'])->contains(fn($m) => $m['level'] !== 'low')) {
            return app(MatchingController::class)->preview($request, 'company', $data, $result);
        }
        $company = $service->save($request->user(), $data, null, true);

        return redirect()->route('companies.show', $company)->with('status', 'Firma kartı oluşturuldu.');
    }

    public function update(CompanyRequest $request, Company $company, CompanyService $service)
    {
        $company = $service->save($request->user(), $request->validated(), $company);

        return redirect()->route('companies.show', $company)->with('status', 'Yeni bilgi kaydedildi; önceki bilgiler geçmişte korundu.');
    }

    public function destroy(Request $request, Company $company, CompanyService $service)
    {
        Gate::authorize('delete', $company);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $service->archive($request->user(), $company, (int) $data['version']);

        return redirect()->route('companies.index')->with('status', 'Firma arşivlendi.');
    }
}
