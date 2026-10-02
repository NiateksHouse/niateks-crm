<?php

namespace Tests\Feature;

use App\Models\KozaRecord;
use App\Services\KozaWorkflow;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KozaDashboardTest extends TestCase
{
    public function test_language_normalizes_existing_regional_choices_without_changing_timezone(): void
    {
        $user = $this->user('locale');
        DB::table('koza_preferences')->insert(['user_id' => $user->id, 'locale' => 'en-GB', 'timezone' => 'Europe/London', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->getJson('/koza/api/bootstrap')->assertOk()->assertJsonPath('locale', 'en')->assertJsonPath('timezone', 'Europe/London');
        $this->putJson('/koza/api/preferences', ['locale' => 'en-US', 'timezone' => 'America/New_York'])->assertOk()->assertJsonPath('locale', 'en');
        $this->putJson('/koza/api/preferences', ['locale' => 'tr-TR', 'timezone' => 'America/New_York'])->assertOk();
        $this->get('/home')->assertSee('>TR<', false)->assertSee('>ENG<', false)->assertDontSee('English · UK')->assertDontSee('English · USA');
    }

    public function test_dashboard_counts_events_in_week_once_and_never_exposes_private_or_demo_records(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
        $user = $this->user('reader');
        $other = $this->user('private');
        $make = fn ($type, $state, $owner, $demo = false) => KozaRecord::create(['type' => $type, 'state' => $state, 'owner_id' => $owner->id, 'title' => $type.' '.$owner->username, 'data' => [], 'demo' => $demo, 'version' => 1, 'edition' => 1]);
        $op = $make('opportunity', 'commercially_qualified', $user);
        $workflow = app(KozaWorkflow::class);
        $workflow->event($user, $op, 'transition');
        $op->update(['state' => 'solution_development']);
        $workflow->event($user, $op, 'transition');
        $private = $make('opportunity', 'negotiation', $other);
        $workflow->event($other, $private, 'transition');
        $demo = $make('opportunity', 'negotiation', $user, true);
        $workflow->event($user, $demo, 'transition');
        $old = $make('service', 'resolved', $user);
        $workflow->event($user, $old, 'transition');
        DB::table('koza_events')->where('record_id', $old->id)->update(['created_at' => now()->subWeeks(2)]);
        $task = $make('task', 'done', $user);
        $task->update(['due_at' => '2026-10-02']);
        $workflow->event($user, $task, 'transition');
        $late = $make('task', 'done', $user);
        $late->update(['due_at' => '2026-10-01']);
        $workflow->event($user, $late, 'transition');
        $r = $this->actingAs($user)->getJson('/koza/api/dashboard')->assertOk()->assertJsonPath('weekly.progressed', 1)->assertJsonPath('weekly.resolved', 0)->assertJsonPath('weekly.promises', 1)->assertJsonPath('can_review_commercial', false);
        $ids = collect($r->json('recent'))->pluck('record.id')->all();
        $this->assertNotContains($private->id, $ids);
        $this->assertNotContains($demo->id, $ids);
        $this->assertArrayNotHasKey('snapshot', $r->json('recent.0'));
    }

    public function test_empty_dashboard_does_not_invent_priorities_health_or_ai_suggestions(): void
    {
        $this->getJson('/koza/api/dashboard')->assertUnauthorized();
        $this->actingAs($this->user('empty'))->getJson('/koza/api/dashboard')->assertOk()->assertJsonPath('has_records', false)->assertJsonPath('priorities.customer', null)->assertJsonPath('priorities.decision', null)->assertJsonPath('priorities.commercial', null)->assertJsonPath('suggestion', null)->assertJsonPath('weekly.reordered', 0);
    }
}
