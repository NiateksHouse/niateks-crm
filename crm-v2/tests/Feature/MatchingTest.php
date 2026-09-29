<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Services\CompanyService;
use App\Services\DuplicateMatcher;
use App\Services\MatchingDecisions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatchingTest extends TestCase
{
    private function preview(array $data): string
    {
        $response = $this->post('/companies', $data + ['intent' => 'review']);
        $response->assertOk()->assertSee('Olası mükerrer');

        return $response->viewData('token');
    }

    public function test_high_score_requires_review_and_never_merges_or_learns_automatically(): void
    {
        $u = $this->user();
        $c = app(CompanyService::class)->save($u, $this->companyData());
        $this->actingAs($u);
        $token = $this->preview($this->companyData());
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('self_learning_company_dictionary', 0);
        $this->post('/matching/decide', ['token' => $token, 'action' => 'same', 'target_id' => $c->id, 'reason' => 'Firma telefonla doğrulandı.'])->assertRedirect('/companies/'.$c->id);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('self_learning_company_dictionary', 1);
        $this->post('/matching/decide', ['token' => $token, 'action' => 'same', 'target_id' => $c->id, 'reason' => 'Tekrar'])->assertStatus(419);
    }

    public function test_low_score_normal_workflow_creates_separate_company_without_learning(): void
    {
        $u = $this->user();
        app(CompanyService::class)->save($u, $this->companyData());
        $data = array_replace($this->companyData(), ['name' => 'Entirely Different', 'phone' => null, 'city' => 'Bath']);
        $this->actingAs($u)->post('/companies', $data)->assertRedirect();
        $this->assertDatabaseCount('companies', 2);
        $this->assertDatabaseCount('self_learning_company_dictionary', 0);
    }

    public function test_confirmed_alias_preserves_brand_spelling_and_revocation_removes_its_influence(): void
    {
        $u = $this->user();
        $admin = $this->user('admin', 'admin');
        $c = app(CompanyService::class)->save($u, array_replace($this->companyData(), ['name' => 'NIATEKS HOUSE']));
        $this->actingAs($u);
        $token = $this->preview(array_replace($this->companyData(), ['name' => 'Niateks Tekstil']));
        $this->post('/matching/decide', ['token' => $token, 'action' => 'same', 'target_id' => $c->id, 'reason' => 'Ticari ad doğrulandı'])->assertRedirect();
        $matcher = app(DuplicateMatcher::class);
        $input = ['name' => 'niateks tekstil', 'country_code' => 'TR'];
        $found = $matcher->find('company', $input, $u)['matches'];
        $this->assertSame($c->id, $found[0]['id']);
        $this->assertGreaterThanOrEqual(70, $found[0]['duplicate_confidence_score']);
        $this->assertSame('NIATEKS HOUSE', $c->fresh()->name);
        $this->assertDatabaseHas('self_learning_company_dictionary', ['alias' => 'Niateks Tekstil']);
        $id = DB::table('matching_decisions')->value('id');
        $this->post('/matching/'.$id.'/revoke', ['reason' => 'Hatalı bağlantı'])->assertForbidden();
        $this->actingAs($admin)->post('/matching/'.$id.'/revoke', ['reason' => 'Hatalı bağlantı'])->assertRedirect();
        $this->assertDatabaseCount('self_learning_company_dictionary', 1);
        $this->assertDatabaseHas('matching_events', ['action' => 'decision_revoked', 'decision_id' => $id]);
        foreach ($matcher->find('company', $input, $u)['matches'] as $match) {
            $this->assertLessThan(70, $match['duplicate_confidence_score']);
        }
    }

    public function test_different_pair_is_suppressed_symmetrically_until_revoke_or_identity_change(): void
    {
        $u = $this->user();
        $admin = $this->user('admin', 'admin');
        $service = app(CompanyService::class);
        $a = $service->save($u, $this->companyData());
        $b = $service->save($u, array_replace($this->companyData(), ['name' => 'Example Textiles']), null, true);
        $matcher = app(DuplicateMatcher::class);
        $decisions = app(MatchingDecisions::class);
        $id = $decisions->record($u, 'company', $a->id, $b->id, 'different', $a->toArray(), $b->toArray(), 'Ayrı tüzel kişiler', []);
        $this->assertSame([], $matcher->find('company', $a->toArray(), $u, $a->id)['matches']);
        $this->assertSame([], $matcher->find('company', $b->toArray(), $u, $b->id)['matches']);
        $b->tax_number = 'NEW';
        $b->save();
        $matcher->index('company', $b->toArray());
        $this->assertNotEmpty($matcher->find('company', $a->toArray(), $u, $a->id)['matches']);
        $b->tax_number = null;
        $b->save();
        $matcher->index('company', $b->toArray());
        $decisions->revoke($admin, $id, 'Karar yeniden incelenecek');
        $this->assertNotEmpty($matcher->find('company', $a->toArray(), $u, $a->id)['matches']);
    }

    public function test_score_boundaries_blank_values_and_country_scoped_tax(): void
    {
        $m = app(DuplicateMatcher::class);
        $w = $m->settings();
        $this->assertSame(0, $m->score('company', [], [], $w)['duplicate_confidence_score']);
        $a = ['name' => 'NIATEKS HOUSE', 'country_code' => 'TR', 'tax_number' => '01234', 'website' => 'https://www.niateks.com/a', 'email' => 'a@niateks.com', 'phone' => '+905551234567', 'city' => 'Izmir'];
        $b = array_replace($a, ['name' => 'Niateks House', 'website' => 'https://niateks.com', 'phone' => '00905551234567']);
        $score = $m->score('company', $a, $b, $w);
        $this->assertSame(100, $score['duplicate_confidence_score']);
        $this->assertSame('strong', $score['level']);
        $this->assertSame(100, $score['name_similarity']);
        $this->assertSame(0, $m->score('company', ['tax_number' => '123', 'country_code' => 'TR'], ['tax_number' => '123', 'country_code' => 'GB'], $w)['duplicate_confidence_score']);
        foreach ([69 => 'low', 70 => 'review', 94 => 'review', 95 => 'strong', 100 => 'strong'] as $value => $level) {
            $custom = $w;
            $custom['email'] = $value;
            $this->assertSame($level, $m->score('company', ['email' => 'a@b.test'], ['email' => 'a@b.test'], $custom)['level']);
        }
    }

    public function test_settings_require_admin_validate_order_and_audit_previous_values(): void
    {
        $u = $this->user();
        $w = app(DuplicateMatcher::class)->settings();
        $this->actingAs($u)->post('/matching/settings', $w + ['version' => 1, 'reason' => 'Yeni değerlendirme'])->assertForbidden();
        $this->actingAs($this->user('admin', 'admin'))->post('/matching/settings', array_replace($w, ['review_threshold' => 96, 'version' => 1, 'reason' => 'Yanlış sınır']))->assertStatus(422);
        $this->post('/matching/settings', array_replace($w, ['review_threshold' => 60, 'version' => 1, 'reason' => 'Yeni değerlendirme']))->assertRedirect();
        $this->assertSame(60, app(DuplicateMatcher::class)->settings()['review_threshold']);
        $this->assertDatabaseHas('matching_events', ['action' => 'settings_changed']);
        $this->post('/matching/settings', $w + ['version' => 1, 'reason' => 'Eski sürüm'])->assertStatus(409);
    }

    public function test_tampered_target_expired_preview_and_changed_candidate_do_not_learn(): void
    {
        $u = $this->user();
        $c = app(CompanyService::class)->save($u, $this->companyData());
        $this->actingAs($u);
        $token = $this->preview($this->companyData());
        $this->post('/matching/decide', ['token' => $token, 'action' => 'same', 'target_id' => 999, 'reason' => 'Geçersiz hedef'])->assertStatus(422);
        $c->tax_number = 'changed';
        $c->save();
        $this->post('/matching/decide', ['token' => $token, 'action' => 'same', 'target_id' => $c->id, 'reason' => 'Eski inceleme'])->assertStatus(409);
        $token = $this->preview($this->companyData());
        $this->travel(31)->minutes();
        $this->post('/matching/decide', ['token' => $token, 'action' => 'same', 'target_id' => $c->id, 'reason' => 'Süre doldu'])->assertStatus(419);
        $this->assertDatabaseCount('self_learning_company_dictionary', 0);
    }

    public function test_contacts_are_compared_only_with_visible_people(): void
    {
        $u = $this->user();
        $other = $this->user('other');
        $c = app(CompanyService::class)->save($u, $this->companyData());
        $data = ['name' => 'Demo Person', 'company_id' => $c->id, 'email' => 'person@example.test', 'phone' => '+442055555555'];
        $this->actingAs($u)->post('/contacts', $data)->assertRedirect();
        $contact = Contact::first();
        $this->post('/contacts', $data)->assertOk()->assertSee('Olası mükerrer');
        $this->actingAs($other)->get('/contacts/'.$contact->id)->assertNotFound();
        $this->assertSame([], app(DuplicateMatcher::class)->find('contact', $data, $other)['matches']);
        $this->post('/contacts', $data)->assertRedirect();
        $this->assertDatabaseCount('contacts', 2);
        $this->assertCount(2, app(DuplicateMatcher::class)->find('contact', $data, $this->user('admin', 'admin'))['matches']);
    }

    public function test_existing_company_review_does_not_create_or_merge_records(): void
    {
        $u = $this->user();
        $s = app(CompanyService::class);
        $a = $s->save($u, $this->companyData());
        $b = $s->save($u, $this->companyData(), null, true);
        $response = $this->actingAs($u)->post('/companies/'.$a->id.'/matching')->assertOk();
        $this->post('/matching/decide', ['token' => $response->viewData('token'), 'action' => 'new', 'different' => [$b->id], 'reason' => 'Ayrı tüzel kişiler'])->assertRedirect('/companies/'.$a->id);
        $this->assertDatabaseCount('companies', 2);
        $this->assertSame([], app(DuplicateMatcher::class)->find('company', $a->toArray(), $u, $a->id)['matches']);
    }

    public function test_dictionary_upgrade_backfills_existing_companies_without_changing_them(): void
    {
        $paths = array_map(fn ($p) => 'database/migrations/'.basename($p), glob(database_path('migrations/*.php')));
        $paths = array_values(array_filter($paths, fn ($p) => ! str_contains($p, '000005')));
        Artisan::call('migrate:fresh', ['--force' => true, '--path' => $paths]);
        $u = $this->user();
        $id = DB::table('companies')->insertGetId(array_replace($this->companyData(), ['roles' => json_encode(['customer']), 'created_by' => $u->id, 'identity_key' => hash('sha256', 'legacy'), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]));
        Artisan::call('migrate', ['--force' => true]);
        $this->assertDatabaseHas('companies', ['id' => $id, 'name' => 'Example Textile', 'created_by' => $u->id]);
        $this->assertSame($id, app(DuplicateMatcher::class)->find('company', $this->companyData(), $u)['matches'][0]['id']);
    }

    public function test_manual_alias_needs_explicit_confirmation_and_reconfirmation_does_not_duplicate_learning(): void
    {
        $u = $this->user();
        $c = app(CompanyService::class)->save($u, $this->companyData());
        $this->actingAs($u);
        $data = ['company_id' => $c->id, 'alias' => 'Tamamen Farklı Marka', 'reason' => 'Ticaret kaydı doğrulandı'];
        $this->post('/matching/aliases', $data)->assertSessionHasErrors('confirmed');
        $this->assertDatabaseCount('self_learning_company_dictionary', 0);
        $this->post('/matching/aliases', $data + ['confirmed' => 1])->assertRedirect();
        $this->post('/matching/aliases', $data + ['confirmed' => 1])->assertRedirect();
        $this->assertDatabaseCount('self_learning_company_dictionary', 1);
        $this->assertDatabaseHas('matching_events', ['action' => 'alias_reconfirmed']);
        $found = app(DuplicateMatcher::class)->find('company', ['name' => 'Tamamen Farklı Markaa'], $u)['matches'];
        $this->assertSame($c->id, $found[0]['id']);
        $this->assertGreaterThanOrEqual(70, $found[0]['duplicate_confidence_score']);
    }
}
