<?php

use App\Support\DisposableTestDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120)->collation('utf8mb4_turkish_ci');
            $t->foreignId('parent_id')->nullable()->constrained('document_categories')->restrictOnDelete();
            $t->timestamps();
            $t->index(['parent_id', 'name']);
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->string('name', 180)->collation('utf8mb4_turkish_ci')->index();
            $t->foreignId('category_id')->constrained('document_categories')->restrictOnDelete();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->text('description')->nullable()->collation('utf8mb4_turkish_ci');
            $t->string('visibility', 20)->default('private')->index();
            $t->json('allowed_users');
            $t->json('allowed_roles');
            $t->date('document_date');
            $t->date('expires_at')->nullable()->index();
            $t->unsignedInteger('current_version')->default(0);
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['owner_id', 'deleted_at']);
        });
        Schema::create('document_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('original_name', 240)->collation('utf8mb4_turkish_ci');
            $t->string('path', 120)->unique();
            $t->string('mime', 100);
            $t->string('extension', 8);
            $t->unsignedBigInteger('size');
            $t->char('sha256', 64);
            $t->text('note')->nullable();
            $t->timestamp('created_at');
            $t->unique(['document_id', 'number']);
        });
        Schema::create('document_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('version')->nullable();
            $t->string('action', 40);
            $t->json('details');
            $t->timestamp('created_at');
            $t->index(['document_id', 'created_at']);
        });
        foreach (['Mali Belgeler' => ['Vergi Levhası', 'Faaliyet Belgesi', 'İmza Sirküleri', 'Banka Belgeleri'], 'Şirket Belgeleri' => ['Ticaret Sicil Gazetesi', 'Yetki Belgeleri'], 'Sözleşmeler' => ['Müşteri Sözleşmeleri', 'Tedarikçi Sözleşmeleri'], 'İnsan Kaynakları' => [], 'Ürün & Teknik' => [], 'Sertifikalar' => []] as $parent => $children) {
            $id = DB::table('document_categories')->insertGetId(['name' => $parent, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($children as $child) {
                DB::table('document_categories')->insert(['name' => $child, 'parent_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DisposableTestDatabase::assertSafe();
        foreach (['document_events', 'document_versions', 'documents', 'document_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
