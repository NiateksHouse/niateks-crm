<?php

use App\Services\DuplicateMatcher;
use App\Support\DisposableTestDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropUnique('companies_identity_key_unique');
            $t->index('identity_key');
            $t->dropUnique('companies_email_unique');
            $t->index('email');
            $t->dropUnique('companies_phone_unique');
            $t->index('phone');
            $t->string('website', 190)->nullable();
            $t->string('tax_number', 80)->nullable();
        });
        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('name', 180);
            $t->string('email', 190)->nullable();
            $t->string('phone', 40)->nullable();
            $t->timestamps();
        });
        Schema::create('matching_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->json('values');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        DB::table('matching_settings')->insert(['id' => 1, 'values' => json_encode(['tax' => 50, 'domain' => 40, 'email' => 40, 'phone' => 35, 'name' => 20, 'contact_company' => 20, 'location' => 5, 'alias' => 70, 'review_threshold' => 70, 'strong_threshold' => 95, 'name_similarity' => 85]), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('matching_decisions', function (Blueprint $t) {
            $t->id();
            $t->string('entity_type', 20);
            $t->unsignedBigInteger('source_id')->nullable();
            $t->unsignedBigInteger('target_id');
            $t->string('decision', 20);
            $t->string('alias', 180)->nullable();
            $t->char('alias_key', 64)->nullable()->index();
            $t->char('source_fingerprint', 64)->nullable();
            $t->char('target_fingerprint', 64)->nullable();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->json('evidence');
            $t->timestamp('created_at');
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('revoke_reason')->nullable();
            $t->index(['entity_type', 'source_id', 'target_id']);
        });
        Schema::create('self_learning_company_dictionary', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('decision_id')->constrained('matching_decisions')->restrictOnDelete();
            $t->string('alias', 180);
            $t->char('normalized_alias', 64)->index();
            $t->timestamp('created_at');
        });
        Schema::create('matching_keys', function (Blueprint $t) {
            $t->id();
            $t->string('entity_type', 20);
            $t->unsignedBigInteger('entity_id');
            $t->string('kind', 20);
            $t->char('value', 64);
            $t->index(['entity_type', 'kind', 'value']);
            $t->index(['entity_type', 'entity_id']);
        });
        Schema::create('matching_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 40);
            $t->unsignedBigInteger('decision_id')->nullable();
            $t->json('details');
            $t->timestamp('created_at');
        });
        DB::table('companies')->whereNull('deleted_at')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                app(DuplicateMatcher::class)->index('company', (array) $row);
            }
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Forward-only matching migration: restore reviewed backup instead of deleting learning history.');
        }
        DisposableTestDatabase::assertSafe();

        // Restore the old uniqueness rules without deleting or rewriting company rows.
        // Refuse BEFORE DDL if intentional duplicates now make those rules impossible.
        foreach (['identity_key', 'email', 'phone'] as $column) {
            if (DB::table('companies')->select($column)->whereNotNull($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Cannot restore company uniqueness while duplicates exist; use migrate:fresh only on the disposable database.');
            }
        }
        foreach (['matching_events', 'matching_keys', 'self_learning_company_dictionary', 'matching_decisions', 'matching_settings', 'contacts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('companies', function (Blueprint $t) {
            foreach (['identity_key', 'email', 'phone'] as $column) {
                $t->dropIndex('companies_'.$column.'_index');
                $t->unique($column);
            }
            $t->dropColumn(['website', 'tax_number']);
        });
    }
};
