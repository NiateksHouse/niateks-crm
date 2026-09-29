<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) { $t->json('roles')->nullable(); });
        Schema::create('supply_categories', function (Blueprint $t) {
            $t->id(); $t->string('name', 100); $t->string('normalized_name', 100)->unique(); $t->timestamps();
        });
        foreach (['Kumaş', 'İplik', 'Aksesuar', 'Ambalaj', 'Fason üretim', 'Lojistik'] as $name) {
            DB::table('supply_categories')->insert(['name'=>$name, 'normalized_name'=>mb_strtolower($name), 'created_at'=>now(), 'updated_at'=>now()]);
        }
        Schema::create('company_supply_category', function (Blueprint $t) {
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('supply_category_id')->constrained()->restrictOnDelete();
            $t->primary(['company_id', 'supply_category_id']);
        });
        Schema::create('company_revisions', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version'); $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->json('snapshot'); $t->timestamp('created_at'); $t->unique(['company_id','version']);
        });
        DB::table('companies')->orderBy('id')->chunkById(100, function ($companies) {
            foreach ($companies as $c) {
                DB::table('companies')->where('id',$c->id)->update(['roles'=>json_encode(['customer'])]);
                $snapshot = collect((array)$c)->only(['name','country_code','city','email','phone'])->all();
                $snapshot['roles']=['customer']; $snapshot['supply_categories']=[];
                DB::table('company_revisions')->insert(['company_id'=>$c->id,'version'=>$c->version,'actor_id'=>null,
                    'snapshot'=>json_encode($snapshot),'created_at'=>now()]);
            }
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('company_revisions'); Schema::dropIfExists('company_supply_category');
        Schema::dropIfExists('supply_categories'); Schema::table('companies',fn(Blueprint $t)=>$t->dropColumn('roles'));
    }
};
