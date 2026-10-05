<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('name', 180);
            $t->text('brief')->nullable();
            $t->string('stage', 40)->default('opened');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['owner_id', 'company_id', 'stage']);
        });
        Schema::create('project_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->timestamp('created_at');
            $t->unique(['project_id', 'version']);
        });
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('kind', 30);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['company_id', 'owner_id', 'updated_at']);
            $t->index(['project_id', 'updated_at']);
        });
        Schema::create('activity_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('summary', 500);
            $t->text('body');
            $t->timestamp('occurred_at');
            $t->timestamp('created_at');
            $t->unique(['activity_id', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['activity_revisions', 'activities', 'project_revisions', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
