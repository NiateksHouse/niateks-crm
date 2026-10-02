<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DocumentMigrationTest extends TestCase
{
    public function test_additive_migration_preserves_existing_users_and_is_forward_only_on_hosting(): void
    {
        $owner = $this->user('owner', 'admin');
        $migration = require database_path('migrations/2026_09_30_000006_create_documents.php');
        $this->app->instance('env', 'staging');
        try {
            $migration->down();
            $this->fail('Persistent rollback must be refused.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('explicitly designated disposable MySQL _ci database', $error->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertTrue(Schema::hasTable('documents'));
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 2, '--force' => true]));
        foreach (['documents', 'document_categories', 'document_versions', 'document_events'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertTrue(Schema::hasTable('self_learning_company_dictionary'));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertDatabaseCount('document_categories', 14);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }
}
