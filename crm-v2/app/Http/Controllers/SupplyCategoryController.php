<?php
namespace App\Http\Controllers;
use App\Models\SupplyCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;
class SupplyCategoryController
{
    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(),403);
        $data=$request->validate(['name'=>['required','string','max:100']]);
        $name=preg_replace('/\s+/u',' ',trim($data['name']));
        if ($name==='') throw ValidationException::withMessages(['name'=>'Tedarik alanı adı girin.']);
        try {
            DB::transaction(function () use ($request,$name) {
                $c=SupplyCategory::create(['name'=>$name,'normalized_name'=>mb_strtolower($name)]);
                DB::table('audit_events')->insert(['actor_id'=>$request->user()->id,'entity_type'=>'supply_category','entity_id'=>$c->id,'action'=>'created','changed_fields'=>json_encode(['name']),'created_at'=>now()]);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['name'=>'Bu tedarik alanı zaten var.']);
        }
        return redirect()->route('companies.index',['role'=>'supplier'])->with('status','Tedarik alanı eklendi.');
    }
}
