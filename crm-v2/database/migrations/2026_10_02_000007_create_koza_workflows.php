<?php

use App\Support\DisposableTestDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('koza_access', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();
            $t->json('domains');
            $t->boolean('read_cost')->default(false);
            $t->boolean('read_finance')->default(false);
            $t->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('koza_preferences', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();
            $t->string('locale', 8)->default('tr-TR');
            $t->string('timezone', 64)->default('Europe/Istanbul');
            $t->timestamps();
        });
        Schema::create('koza_records', function (Blueprint $t) {
            $t->id();
            $t->string('type', 40)->index();
            $t->string('title', 180)->index();
            $t->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('koza_records')->restrictOnDelete();
            $t->string('state', 40)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('edition')->default(1);
            $t->json('data');
            $t->boolean('immutable')->default(false);
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('source', 240)->nullable();
            $t->date('due_at')->nullable()->index();
            $t->boolean('demo')->default(false);
            $t->string('dedup_key', 160)->nullable()->unique();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['type', 'owner_id', 'state']);
            $t->index(['company_id', 'type']);
        });
        Schema::create('koza_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('koza_records')->restrictOnDelete();
            $t->foreignId('target_id')->constrained('koza_records')->restrictOnDelete();
            $t->string('relation', 40);
            $t->decimal('quantity', 16, 4)->nullable();
            $t->string('unit', 20)->nullable();
            $t->unique(['record_id', 'relation', 'target_id']);
            $t->index(['target_id', 'relation']);
        });
        Schema::create('koza_members', function (Blueprint $t) {
            $t->foreignId('record_id')->constrained('koza_records')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->primary(['record_id', 'user_id']);
        });
        Schema::create('koza_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('koza_records')->restrictOnDelete();
            $t->unsignedInteger('position');
            $t->foreignId('variant_id')->constrained('koza_records')->restrictOnDelete();
            $t->foreignId('sku_id')->nullable()->constrained('koza_records')->restrictOnDelete();
            $t->decimal('quantity', 16, 4);
            $t->string('unit', 20);
            $t->decimal('unit_price', 16, 4);
            $t->decimal('unit_cost', 16, 4)->nullable();
            $t->json('specification');
            $t->unique(['record_id', 'position']);
        });
        Schema::create('koza_documents', function (Blueprint $t) {
            $t->foreignId('record_id')->constrained('koza_records')->restrictOnDelete();
            $t->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $t->primary(['record_id', 'document_id']);
        });
        Schema::create('koza_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->nullable()->constrained('koza_records')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 40);
            $t->unsignedInteger('version')->nullable();
            $t->json('snapshot');
            $t->text('reason')->nullable();
            $t->timestamp('created_at');
            $t->index(['record_id', 'created_at']);
        });
        Schema::create('koza_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('koza_records')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('kind', 30);
            $t->char('digest', 64);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['record_id', 'version', 'kind']);
        });
        Schema::create('koza_controls', function (Blueprint $t) {
            $t->string('key', 50)->primary();
            $t->json('value');
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        DisposableTestDatabase::assertSafe();
        foreach (['koza_controls', 'koza_approvals', 'koza_events', 'koza_documents', 'koza_lines', 'koza_members', 'koza_links', 'koza_records', 'koza_preferences', 'koza_access'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
