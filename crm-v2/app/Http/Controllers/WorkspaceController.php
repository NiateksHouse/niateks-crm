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
        $modules = [
            'products' => ['Ürünler', 'apron', 'Ürün tipi → ürün ailesi → ürün özellikleri.', 'Renk, ölçü, adet veya set bilgileri ürün kartında birlikte yer alacak.'],
            'tasks' => ['İş takibi', 'check', 'Yapılacak iş, sorumlu ve tarih bir arada.', 'Görüşmeden doğan işlerinizi ve tamamlanma durumunu burada takip edeceksiniz.'],
            'samples' => ['Numuneler', 'tea-towel', 'Numune geliştirme ve onay akışı.', 'Numune sürümleri ve müşteri geri bildirimleri bu bölümde toplanacak.'],
            'quotes' => ['Teklifler', 'file', 'Her teklif revizyonu kendi geçmişini korusun.', 'Ürünlerden fiyat çalışmasına, müşteri PDF’inden teklif kararına tek akış.'],
            'orders' => ['Sipariş & üretim', 'box', 'Her aşamada bir sonraki adım belli olsun.', 'Hammaddeden teslimata günlük ilerleme ve tarihsel kayıtlar.'],
            'finance' => ['Finans / Nigar', 'calc', 'Tahsilatı ve vade değişikliklerini takip edin.', 'Yetkili olduğunuz tahsilatlar, ödeme tarihleri ve açıklamalı değişiklik geçmişi.'],
        ];
        abort_unless(isset($modules[$module]), 404);

        return view('workspace.module', ['module' => $module, 'info' => $modules[$module]]);
    }
}
