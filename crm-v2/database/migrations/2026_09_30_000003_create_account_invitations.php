<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_invitations', function (Blueprint $t) {
            $t->id();
            $t->string('username', 80)->unique();
            $t->string('email', 190)->unique();
            $t->string('name', 120);
            $t->enum('role', ['admin', 'representative']);
            $t->boolean('can_view_all_finance')->default(false);
            $t->char('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('onboarding_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invitation_id')->constrained('account_invitations')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('action', 40);
            $t->timestamp('created_at');
        });
        Schema::create('onboarding_runs', function (Blueprint $t) {
            $t->string('id', 40)->primary();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_events');
        Schema::dropIfExists('onboarding_runs');
        Schema::dropIfExists('account_invitations');
    }
};
