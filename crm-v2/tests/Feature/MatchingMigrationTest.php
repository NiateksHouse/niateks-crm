<?php

namespace Tests\Feature;

use App\Services\CompanyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MatchingMigrationTest extends TestCase
{
    private function migration(): object
    {
        return require database_path('migrations/2026_09_30_000005_create_matching_dictionary.php');
    }

    public function test_rollback_restores_schema_and_preserves_unrelated_rows_then_upgrade_backfills(): void
    {
        $user = $this->user();
        $company = app(CompanyService::class)->save($user, $this->companyData());
        $this->actingAs($user)->post('/matching/aliases', ['company_id' => $company->id, 'alias' => 'Confirmed brand', 'reason' => 'Test confirmation', 'confirmed' => 1])->assertRedirect();
        $this->assertDatabaseCount('self_learning_company_dictionary', 1);
        $tables = ['users', 'companies', 'company_revisions', 'audit_events'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        foreach ($before['companies'] as &$row) {
            unset($row['website'], $row['tax_number']);
        }
        unset($row);
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]));
        foreach (['matching_events', 'matching_keys', 'self_learning_company_dictionary', 'matching_decisions', 'matching_settings', 'contacts'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('companies', 'website'));
        $this->assertFalse(Schema::hasColumn('companies', 'tax_number'));
        foreach (['identity_key', 'email', 'phone'] as $column) {
            $this->assertTrue(Schema::hasIndex('companies', 'companies_'.$column.'_unique', 'unique'));
            $this->assertFalse(Schema::hasIndex('companies', 'companies_'.$column.'_index'));
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        }
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertDatabaseHas('matching_keys', ['entity_type' => 'company', 'entity_id' => $company->id]);
        $this->assertDatabaseCount('self_learning_company_dictionary', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_persistent_environments_block_rollback_before_any_schema_change(): void
    {
        $user = $this->user();
        $company = app(CompanyService::class)->save($user, $this->companyData());
        $this->actingAs($user)->post('/matching/aliases', ['company_id' => $company->id, 'alias' => 'Keep brand', 'reason' => 'Verified', 'confirmed' => 1]);
        foreach (['production', 'staging', 'local', 'development', 'ci_http'] as $environment) {
            $this->app->instance('env', $environment);
            try {
                $this->migration()->down();
                $this->fail('Persistent rollback must throw.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('Forward-only', $error->getMessage());
            } finally {
                $this->app->instance('env', 'testing');
            }
            $this->assertDatabaseCount('self_learning_company_dictionary', 1);
            $this->assertTrue(Schema::hasColumn('companies', 'website'));
        }
    }

    public function test_testing_environment_alone_does_not_authorize_cleanup(): void
    {
        $database = config('database.connections.mysql.database');
        foreach ([null, 'another_ci', 'niatekscom_koza_test'] as $designation) {
            config(['database.disposable_test_database' => $designation]);
            try {
                $this->migration()->down();
                $this->fail('Explicit disposable database designation required.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('explicitly designated', $error->getMessage());
            }
            $this->assertTrue(Schema::hasTable('matching_decisions'));
        }
        config(['database.disposable_test_database' => $database]);
        // Even a misleading config cannot change the already resolved PDO database.
        config(['database.connections.mysql.database' => 'different_ci', 'database.disposable_test_database' => 'different_ci']);
        try {
            $this->migration()->down();
            $this->fail('Connected database mismatch must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('connected database', $error->getMessage());
        } finally {
            config(['database.connections.mysql.database' => $database, 'database.disposable_test_database' => $database]);
        }
        $this->assertTrue(Schema::hasTable('contacts'));
    }

    public function test_duplicate_companies_refuse_rollback_without_deleting_history(): void
    {
        $user = $this->user();
        $service = app(CompanyService::class);
        $company = $service->save($user, $this->companyData());
        $service->save($user, $this->companyData(), null, true);
        $this->actingAs($user)->post('/matching/aliases', ['company_id' => $company->id, 'alias' => 'Keep duplicate history', 'reason' => 'Verified', 'confirmed' => 1]);
        try {
            $this->migration()->down();
            $this->fail('Rollback must refuse incompatible old unique indexes.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('duplicates exist', $error->getMessage());
        }
        $this->assertDatabaseCount('companies', 2);
        $this->assertDatabaseCount('self_learning_company_dictionary', 1);
        $this->assertTrue(Schema::hasColumn('companies', 'website'));
    }

    public function test_disposable_migrate_refresh_can_repeat_the_migration_lifecycle(): void
    {
        $this->assertSame(0, Artisan::call('migrate:refresh', ['--force' => true]));
        $this->assertTrue(Schema::hasTable('self_learning_company_dictionary'));
        $this->assertDatabaseCount('matching_settings', 1);
    }
}
