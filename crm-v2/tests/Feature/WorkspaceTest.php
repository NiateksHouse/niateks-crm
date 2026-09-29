<?php

namespace Tests\Feature;

use App\Services\CompanyService;
use App\Services\ProjectService;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    public function test_workspace_routes_require_an_active_account(): void
    {
        foreach (['/home', '/activities', '/workspace/orders'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->actingAs($this->user(active: false))->get('/home')->assertRedirect('/login');
    }

    public function test_home_and_global_timeline_respect_private_work(): void
    {
        $owner = $this->user();
        $company = app(CompanyService::class)->save($owner, $this->companyData());
        $project = app(ProjectService::class)->save($owner, $company, ['name' => 'Restricted apron request']);
        $this->actingAs($owner)->post('/companies/'.$company->id.'/activities', ['kind' => 'note', 'summary' => 'Restricted customer notes', 'body' => 'Original conversation', 'occurred_at' => '2026-09-30T10:30', 'project_id' => $project->id])->assertRedirect();
        $this->get('/home')->assertOk()->assertSee('Restricted apron request')->assertViewHas('projectCount', 1)->assertViewHas('activityCount', 1);
        $this->get('/activities')->assertOk()->assertSee('Restricted customer notes');
        $this->actingAs($this->user('other'));
        $this->get('/home')->assertOk()->assertDontSee('Restricted apron request')->assertViewHas('projectCount', 0)->assertViewHas('activityCount', 0)->assertViewHas('companyCount', 1);
        $this->get('/activities')->assertOk()->assertDontSee('Restricted customer notes')->assertDontSee('Restricted apron request');
        $this->actingAs($this->user('admin', 'admin'))->get('/activities')->assertOk()->assertSee('Restricted customer notes');
        $company->delete();
        $this->get('/activities')->assertOk()->assertDontSee('Restricted customer notes');
        $this->get('/home')->assertOk()->assertViewHas('projectCount', 0);
    }

    public function test_approved_navigation_and_preparation_pages_are_explicit(): void
    {
        $this->actingAs($this->user());
        $this->get('/home')->assertOk()->assertSee('Başlangıç')->assertSee('Sipariş &amp; üretim', false)->assertSee('workspace-alpha26.js')->assertSee('Çalışma müziği');
        foreach (['products', 'tasks', 'samples', 'quotes', 'orders', 'finance'] as $module) {
            $this->get('/workspace/'.$module)->assertOk()->assertSee('Henüz kayıt alınmıyor');
        }
        $this->get('/workspace/unknown')->assertNotFound();
        $this->post('/workspace/orders')->assertStatus(405);
    }
}
