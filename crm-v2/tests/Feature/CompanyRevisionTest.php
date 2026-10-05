<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SupplyCategory;
use App\Services\CompanyService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyRevisionTest extends TestCase
{
    public function test_both_roles_and_multiple_supply_areas_share_one_card_and_filter(): void
    {
        $u = $this->user();
        $ids = SupplyCategory::limit(2)->pluck('id')->all();
        $this->actingAs($u)->post('/companies', array_replace($this->companyData(), ['roles' => ['customer', 'supplier'], 'supply_category_ids' => $ids]))->assertRedirect();
        $c = Company::first();
        $this->assertCount(2, $c->supplyCategories);
        $this->assertDatabaseCount('companies', 1);
        $this->get('/companies?role=customer')->assertSee('Example Textile');
        $this->get('/companies?role=supplier&category='.$ids[0])->assertSee('Example Textile');
        $other = SupplyCategory::whereNotIn('id', $ids)->first();
        $this->get('/companies?role=supplier&category='.$other->id)->assertDontSee('Example Textile');
    }

    public function test_revision_preserves_old_values_and_actor_and_hides_old_information(): void
    {
        $owner = $this->user('owner');
        $other = $this->user('editor');
        $c = app(CompanyService::class)->save($owner, $this->companyData());
        $original = $c->revisions()->first()->snapshot;
        $this->actingAs($other)->put('/companies/'.$c->id, array_replace($this->companyData(), ['city' => 'Bath', 'roles' => ['supplier'], 'version' => 1]))->assertRedirect();
        $this->assertSame($original, $c->revisions()->where('version', 1)->first()->snapshot);
        $this->assertSame($other->id, $c->revisions()->where('version', 2)->first()->actor_id);
        $this->get('/companies/'.$c->id)->assertSee('Bath')->assertDontSee('London');
        $this->get('/companies/'.$c->id.'/history')->assertSee('London')->assertSee('owner')->assertDontSee('Bath');
        $this->put('/companies/'.$c->id, $this->companyData() + ['version' => 1])->assertStatus(409);
        $this->assertDatabaseCount('company_revisions', 2);
    }

    public function test_category_and_role_only_changes_are_audited(): void
    {
        $u = $this->user();
        $c = app(CompanyService::class)->save($u, $this->companyData());
        $this->actingAs($u)->put('/companies/'.$c->id, array_replace($this->companyData(), ['version' => 1, 'roles' => ['supplier'], 'supply_category_ids' => [SupplyCategory::first()->id]]))->assertRedirect();
        $fields = json_decode(DB::table('audit_events')->where('action', 'revision_added')->value('changed_fields'), true);
        $this->assertContains('roles', $fields);
        $this->assertContains('supply_categories', $fields);
    }

    public function test_invalid_roles_categories_and_malformed_fields_do_not_write(): void
    {
        $this->actingAs($this->user());
        foreach ([['roles' => []], ['roles' => ['admin']], ['roles' => ['supplier'], 'supply_category_ids' => [99999]], ['supply_category_ids' => [SupplyCategory::first()->id]], ['email' => ['bad']], ['name' => ['bad']]] as $invalid) {
            $this->post('/companies', array_replace($this->companyData(), $invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('company_revisions', 0);
    }

    public function test_only_admin_can_extend_categories_and_duplicates_are_rejected(): void
    {
        $this->actingAs($this->user())->post('/supply-categories', ['name' => 'Nakış'])->assertForbidden();
        $this->actingAs($this->user('admin', 'admin'))->post('/supply-categories', ['name' => 'Nakış'])->assertRedirect();
        $this->post('/supply-categories', ['name' => '  Nakış  '])->assertSessionHasErrors('name');
        $this->assertSame(1, SupplyCategory::where('name', 'Nakış')->count());
    }

    public function test_history_requires_login(): void
    {
        $c = app(CompanyService::class)->save($this->user(), $this->companyData());
        $this->get('/companies/'.$c->id.'/history')->assertRedirect('/login');
    }
}
