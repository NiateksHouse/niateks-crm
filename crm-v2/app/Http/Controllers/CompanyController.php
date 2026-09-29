<?php
namespace App\Http\Controllers;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class CompanyController
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Company::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim($data['q'] ?? '');
        $companies = Company::query()->when($q !== '', fn ($query) => $query->where('name', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%'))->orderBy('name')->paginate(25)->withQueryString();
        return view('companies.index', compact('companies', 'q'));
    }
    public function create() { Gate::authorize('create', Company::class); return view('companies.form', ['company' => new Company]); }
    public function show(Company $company) { Gate::authorize('view', $company); return view('companies.show', compact('company')); }
    public function edit(Company $company) { Gate::authorize('update', $company); return view('companies.form', compact('company')); }
    public function store(CompanyRequest $request, CompanyService $service)
    {
        $company = $service->save($request->user(), $request->validated());
        return redirect()->route('companies.show', $company)->with('status', 'Firma kartı oluşturuldu.');
    }
    public function update(CompanyRequest $request, Company $company, CompanyService $service)
    {
        $company = $service->save($request->user(), $request->validated(), $company);
        return redirect()->route('companies.show', $company)->with('status', 'Firma kartı güncellendi.');
    }
    public function destroy(Request $request, Company $company, CompanyService $service)
    {
        Gate::authorize('delete', $company);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $service->archive($request->user(), $company, (int) $data['version']);
        return redirect()->route('companies.index')->with('status', 'Firma arşivlendi.');
    }
}
