<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name', 120); $t->string('username', 80)->unique(); $t->string('email', 190)->unique();
            $t->string('password'); $t->enum('role', ['admin', 'representative'])->default('representative');
            $t->boolean('active')->default(true); $t->boolean('can_view_all_finance')->default(false);
            $t->rememberToken(); $t->timestamps();
        });
        Schema::create('companies', function (Blueprint $t) {
            $t->id(); $t->string('name', 180)->index(); $t->char('identity_key', 64)->unique();
            $t->char('country_code', 2); $t->string('city', 100)->nullable();
            $t->string('email', 190)->nullable()->unique(); $t->string('phone', 40)->nullable()->unique();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('entity_type', 40); $t->unsignedBigInteger('entity_id'); $t->string('action', 40);
            $t->json('changed_fields'); $t->timestamp('created_at'); $t->index(['entity_type', 'entity_id', 'created_at']);
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary(); $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable(); $t->text('user_agent')->nullable();
            $t->longText('payload'); $t->integer('last_activity')->index();
        });
        Schema::create('cache', function (Blueprint $t) { $t->string('key')->primary(); $t->mediumText('value'); $t->integer('expiration'); });
        Schema::create('cache_locks', function (Blueprint $t) { $t->string('key')->primary(); $t->string('owner'); $t->integer('expiration'); });
    }
    public function down(): void
    {
        foreach (['cache_locks', 'cache', 'sessions', 'audit_events', 'companies', 'users'] as $table) Schema::dropIfExists($table);
    }
};
