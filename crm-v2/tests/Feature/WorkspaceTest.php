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
        $this->getJson('/koza/api/bootstrap')->assertOk()->assertJsonPath('projects.0.name', 'Restricted apron request');
        $this->get('/activities')->assertOk()->assertSee('Restricted customer notes');
        $this->actingAs($this->user('other'));
        $this->getJson('/koza/api/bootstrap')->assertOk()->assertJsonCount(0, 'projects')->assertJsonCount(1, 'companies');
        $this->get('/activities')->assertOk()->assertDontSee('Restricted customer notes')->assertDontSee('Restricted apron request');
        $this->actingAs($this->user('admin', 'admin'))->get('/activities')->assertOk()->assertSee('Restricted customer notes');
        $company->delete();
        $this->get('/activities')->assertOk()->assertDontSee('Restricted customer notes');
        $this->getJson('/koza/api/bootstrap')->assertOk()->assertJsonCount(0, 'projects');
    }

    public function test_legacy_navigation_opens_the_implemented_workspace(): void
    {
        $this->actingAs($this->user());
        $this->get('/home')->assertOk()->assertSee('koza-v1.js')->assertSee('English · UK')->assertSee('Türkçe · TR');
        foreach (['products' => 'products', 'tasks' => 'focus', 'samples' => 'samples', 'quotes' => 'quotes', 'orders' => 'orders', 'finance' => 'commercial'] as $module => $view) {
            $this->get('/workspace/'.$module)->assertRedirect('/home#'.$view);
        }
        $this->get('/workspace/unknown')->assertNotFound();
        $this->post('/workspace/orders')->assertStatus(405);
    }
}
