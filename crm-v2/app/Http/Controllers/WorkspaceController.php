<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\Request;

class WorkspaceController
{
    public function home(Request $request)
    {
        return view('workspace.home', [
            'companyCount' => Company::count(),
            'projectCount' => Project::visibleTo($request->user())->count(),
            'activityCount' => Activity::visibleTo($request->user())->where('kind', '!=', 'system')->count(),
            'projects' => Project::visibleTo($request->user())->with('company')->latest('updated_at')->limit(5)->get(),
        ]);
    }

    public function activities(Request $request)
    {
        $activities = Activity::visibleTo($request->user())->with(['latestRevision.actor', 'project', 'company'])->latest('updated_at')->orderByDesc('id')->paginate(20);

        return view('workspace.activities', compact('activities'));
    }

    public function module(string $module)
    {
        $modules = ['products' => 'products', 'tasks' => 'focus', 'samples' => 'samples', 'quotes' => 'quotes', 'orders' => 'orders', 'finance' => 'commercial'];
        abort_unless(isset($modules[$module]), 404);

        return redirect('/home#'.$modules[$module]);
    }
}
