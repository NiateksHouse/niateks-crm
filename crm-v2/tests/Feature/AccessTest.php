<?php

namespace Tests\Feature;

use App\Services\CompanyService;
use Tests\TestCase;

class AccessTest extends TestCase
{
    public function test_guest_cannot_read_companies(): void
    {
        $this->get('/companies')->assertRedirect('/login');
    }

    public function test_valid_credentials_login_and_logout(): void
    {
        $u = $this->user();
        $this->post('/login', ['username' => 'rep', 'password' => 'Test-only-Password-782!'])->assertRedirect('/companies');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_disabled_account_cannot_login(): void
    {
        $this->user(active: false);
        $this->post('/login', ['username' => 'rep', 'password' => 'Test-only-Password-782!'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_deactivation_invalidates_existing_access(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        $u->active = false;
        $u->save();
        $this->get('/companies')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_repeated_bad_logins_are_throttled(): void
    {
        $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'rep', 'password' => 'wrong']);
        }
        $this->post('/login', ['username' => 'rep', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_company_is_shared_and_another_rep_can_add_revision_but_not_archive(): void
    {
        $owner = $this->user('owner');
        $company = app(CompanyService::class)->save($owner, $this->companyData());
        $this->actingAs($this->user('other'))->get('/companies/'.$company->id)->assertOk()->assertSee('Example Textile');
        $this->put('/companies/'.$company->id, $this->companyData() + ['version' => 1])->assertRedirect();
        $this->delete('/companies/'.$company->id, ['version' => 1])->assertForbidden();
    }

    public function test_admin_archives_without_destroying_record_and_writes_audit(): void
    {
        $company = app(CompanyService::class)->save($this->user(), $this->companyData());
        $this->actingAs($this->user('admin', 'admin'))->delete('/companies/'.$company->id, ['version' => 1])->assertRedirect('/companies');
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('audit_events', ['entity_id' => $company->id, 'action' => 'archived']);
    }

    public function test_duplicate_does_not_create_company_or_audit(): void
    {
        $u = $this->user();
        app(CompanyService::class)->save($u, $this->companyData());
        $this->actingAs($u)->post('/companies', $this->companyData())->assertSessionHasErrors('name');
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_stale_update_is_rejected(): void
    {
        $u = $this->user();
        $c = app(CompanyService::class)->save($u, $this->companyData());
        $this->actingAs($u)->put('/companies/'.$c->id, array_replace($this->companyData(), ['city' => 'Bath', 'version' => 1]))->assertRedirect();
        $this->put('/companies/'.$c->id, array_replace($this->companyData(), ['city' => 'York', 'version' => 1]))->assertStatus(409);
        $this->assertDatabaseHas('companies', ['id' => $c->id, 'city' => 'Bath', 'version' => 2]);
    }

    public function test_submitted_roles_and_finance_permissions_are_not_mass_assigned(): void
    {
        $u = $this->user();
        $this->actingAs($u)->post('/companies', $this->companyData() + ['role' => 'admin', 'can_view_all_finance' => true, 'created_by' => 999])->assertRedirect();
        $this->assertSame('representative', $u->fresh()->role);
        $this->assertDatabaseHas('companies', ['created_by' => $u->id]);
    }

    public function test_company_name_is_escaped(): void
    {
        $u = $this->user();
        $data = $this->companyData();
        $data['name'] = '<script>alert(1)</script>';
        $c = app(CompanyService::class)->save($u, $data);
        $this->actingAs($u)->get('/companies/'.$c->id)->assertOk()->assertDontSee($data['name'], false)->assertSee('&lt;script&gt;', false);
    }
}
