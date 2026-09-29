<?php
namespace App\Http\Controllers;
use App\Models\Company;
use App\Models\Contact;
use App\Services\DuplicateMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ContactController {
 public function index(Request $r) { return view('contacts.index',['contacts'=>Contact::visibleTo($r->user())->with('company')->orderBy('name')->paginate(25)]); }
 public function create() { return view('contacts.form',['companies'=>Company::orderBy('name')->get(['id','name'])]); }
 public function show(Request $r,int $contact) { return view('contacts.show',['contact'=>Contact::visibleTo($r->user())->with('company')->findOrFail($contact)]); }
 public function store(Request $r,DuplicateMatcher $matcher,MatchingController $review) {
  $data=$r->validate(['name'=>['required','string','max:180'],'company_id'=>['required','integer','exists:companies,id'],'email'=>['nullable','email:rfc','max:190'],'phone'=>['nullable','string','max:40']]);
  $data['name']=trim($data['name']); abort_if($data['name']==='',422);
  $data['email']=$matcher->email($data['email']??'')?:null; $data['phone']=$matcher->phone($data['phone']??'')?:null;
  return DB::transaction(function()use($r,$data,$matcher,$review){
  DB::table('matching_settings')->where('id',1)->lockForUpdate()->first();
  Company::findOrFail($data['company_id']); $result=$matcher->find('contact',$data,$r->user());
  if($r->input('intent')==='review' || collect($result['matches'])->contains(fn($x)=>$x['level']!=='low')) return $review->preview($r,'contact',$data,$result);
  $contact=DB::transaction(fn()=>$review->createContact($r,$data));
  return redirect()->route('contacts.show',$contact)->with('status','Kişi kaydedildi.');
  });
 }
}
