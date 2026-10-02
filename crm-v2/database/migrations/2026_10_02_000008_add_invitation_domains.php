<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_invitations', function (Blueprint $t) {
            $t->json('domains')->nullable();
            $t->foreignId('granted_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_invitations', function (Blueprint $t) {
            $t->dropForeign(['granted_by']);
            $t->dropColumn(['domains', 'granted_by']);
        });
    }
};
