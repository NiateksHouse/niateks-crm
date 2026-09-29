<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Services\CompanyService;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProjectActivityTest extends TestCase
{
    public function test_upgrade_keeps_existing_users_and_companies(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        $owner = $this->user();
        $company = app(CompanyService::class)->save($owner, $this->companyData());
        Artisan::call('migrate', ['--force' => true]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'username' => 'rep']);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Example Textile']);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('activities', 0);
    }

    private function fixture(): array
    {
        $owner = $this->user();
        $company = app(CompanyService::class)->save($owner, $this->companyData());
        $project = app(ProjectService::class)->save($owner, $company, ['name' => 'Private apron project', 'brief' => 'Customer request']);

        return [$owner, $company, $project];
    }

    private function note(): array
    {
        return ['kind' => 'email_in', 'summary' => 'Pricing requested', 'body' => '<script>alert(1)</script> Original email', 'occurred_at' => '2026-09-30T10:30'];
    }

    public function test_project_and_notes_are_private_inside_shared_company(): void
    {
        [$owner, $company, $project] = $this->fixture();
        $this->actingAs($owner)->post('/companies/'.$company->id.'/activities', $this->note() + ['project_id' => $project->id])->assertRedirect();
        $activity = Activity::where('kind', 'email_in')->firstOrFail();
        $this->actingAs($this->user('other'));
        $this->get('/companies/'.$company->id)->assertOk()->assertDontSee('Private apron project')->assertDontSee('Pricing requested');
        $this->get('/projects')->assertOk()->assertDontSee('Private apron project');
        $this->get('/projects/'.$project->id)->assertNotFound();
        $this->get('/projects/'.$project->id.'/edit')->assertNotFound();
        $this->get('/projects/'.$project->id.'/history')->assertNotFound();
        $this->put('/projects/'.$project->id, ['name' => 'Overwrite', 'stage' => 'opened', 'version' => 1])->assertNotFound();
        $this->get('/activities/'.$activity->id.'/edit')->assertNotFound();
        $this->get('/activities/'.$activity->id.'/history')->assertNotFound();
        $this->put('/activities/'.$activity->id, array_diff_key($this->note(), ['kind' => true]) + ['version' => 1])->assertNotFound();
        $this->post('/companies/'.$company->id.'/activities', $this->note() + ['project_id' => $project->id])->assertNotFound();
        $this->actingAs($this->user('admin', 'admin'))->get('/projects/'.$project->id)->assertOk()->assertSee('Pricing requested');
    }

    public function test_project_note_appears_once_in_both_timelines_with_safe_email_display(): void
    {
        [$owner, $company, $project] = $this->fixture();
        $this->actingAs($owner)->post('/companies/'.$company->id.'/activities', $this->note() + ['project_id' => $project->id])->assertRedirect('/projects/'.$project->id);
        $this->assertDatabaseCount('activities', 2);
        foreach (['/projects/'.$project->id, '/companies/'.$company->id] as $url) {
            $this->get($url)->assertOk()->assertSee('Pricing requested')->assertSee('Maili oku')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        }
        $this->assertDatabaseHas('activity_revisions', ['occurred_at' => '2026-09-30 07:30:00']);
    }

    public function test_activity_revisions_preserve_original_and_reject_stale_writes(): void
    {
        [$owner, $company, $project] = $this->fixture();
        $this->actingAs($owner)->post('/companies/'.$company->id.'/activities', $this->note());
        $activity = Activity::where('kind', 'email_in')->firstOrFail();
        $update = ['version' => 1, 'summary' => 'Updated request', 'body' => 'Second email information', 'occurred_at' => '2026-09-30T11:00'];
        $this->put('/activities/'.$activity->id, $update)->assertRedirect();
        $this->put('/activities/'.$activity->id, $update)->assertStatus(409);
        $this->assertDatabaseHas('activity_revisions', ['activity_id' => $activity->id, 'version' => 1, 'summary' => 'Pricing requested']);
        $this->assertDatabaseHas('activity_revisions', ['activity_id' => $activity->id, 'version' => 2, 'summary' => 'Updated request']);
        $this->get('/companies/'.$company->id)->assertOk()->assertSee('Updated request')->assertDontSee('Pricing requested');
        $this->get('/activities/'.$activity->id.'/history')->assertOk()->assertSee('Pricing requested');
    }

    public function test_project_owner_cannot_be_forged_and_cross_company_note_is_rejected(): void
    {
        [$owner, $company, $project] = $this->fixture();
        $other = $this->user('other');
        $this->actingAs($owner)->post('/companies/'.$company->id.'/projects', ['name' => 'Second project', 'owner_id' => $other->id])->assertRedirect();
        $this->assertDatabaseHas('projects', ['name' => 'Second project', 'owner_id' => $owner->id]);
        $otherCompany = app(CompanyService::class)->save($owner, array_replace($this->companyData(), ['name' => 'Other company', 'email' => null, 'phone' => null]));
        $this->post('/companies/'.$otherCompany->id.'/activities', $this->note() + ['project_id' => $project->id])->assertNotFound();
    }

    public function test_project_revision_audit_and_stage_guards(): void
    {
        [$owner, $company, $project] = $this->fixture();
        $this->actingAs($owner);
        $this->put('/projects/'.$project->id, ['name' => 'Updated project', 'stage' => 'discussing', 'version' => 1])->assertRedirect();
        $this->put('/projects/'.$project->id, ['name' => 'Stale project', 'stage' => 'opened', 'reason' => 'Retry', 'version' => 1])->assertStatus(409);
        $this->put('/projects/'.$project->id, ['name' => 'Updated project', 'stage' => 'opened', 'version' => 2])->assertSessionHasErrors('reason');
        $this->put('/projects/'.$project->id, ['name' => 'Updated project', 'stage' => 'accepted', 'version' => 2])->assertSessionHasErrors('stage');
        $this->get('/projects/'.$project->id.'/history')->assertOk()->assertSee('Private apron project');
        $this->assertDatabaseHas('audit_events', ['entity_type' => 'project', 'entity_id' => $project->id, 'action' => 'revision_added']);
        $this->post('/companies/'.$company->id.'/projects', ['name' => 'Updated project'])->assertSessionHasErrors('name');
    }

    public function test_admin_note_retains_project_owner_visibility_and_system_records_are_immutable(): void
    {
        [$owner, $company, $project] = $this->fixture();
        $this->actingAs($this->user('admin', 'admin'))->post('/companies/'.$company->id.'/activities', $this->note() + ['project_id' => $project->id])->assertRedirect();
        $this->actingAs($owner)->get('/projects/'.$project->id)->assertOk()->assertSee('Pricing requested');
        $system = Activity::where('kind', 'system')->firstOrFail();
        $this->get('/activities/'.$system->id.'/edit')->assertForbidden();
        $this->put('/activities/'.$system->id, ['version' => 1, 'summary' => 'Replace', 'body' => 'Replace', 'occurred_at' => '2026-09-30T10:00'])->assertForbidden();
    }

    public function test_guest_and_archived_company_cannot_expose_project_work(): void
    {
        [$owner, $company, $project] = $this->fixture();
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/projects/'.$project->id)->assertRedirect('/login');
        $company->delete();
        $this->actingAs($owner)->get('/projects/'.$project->id)->assertNotFound();
        $this->get('/projects')->assertOk()->assertDontSee('Private apron project');
    }
}
