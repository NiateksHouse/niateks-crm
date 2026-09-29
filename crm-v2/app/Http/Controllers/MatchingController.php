<?php
namespace App\Http\Controllers;
use App\Models\Company;
use App\Models\Contact;
use App\Services\DuplicateMatcher;
use App\Services\MatchingDecisions;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class MatchingController {
 public function preview(Request $request,string $type,array $data,array $result, ?int $sourceId=null) {
  $pending=$request->session()->get('matching',[]);
  $pending=array_filter($pending,fn($v)=>$v['expires']>=now()->timestamp);
  $request->session()->put('matching',array_slice($pending,-4,null,true));
  $token=(string)Str::uuid();
  $request->session()->put('matching.'.$token,['source_id'=>$sourceId,'type'=>$type,'input'=>$data,'result'=>$result,'expires'=>now()->addMinutes(30)->timestamp]);
  return response()->view('matching.review',compact('token','type','data','result','sourceId'));
 }
 public function cancel(Request $request) {
  $data=$request->validate(['token'=>['required','uuid']]);
  $draft=$request->session()->pull('matching.'.$data['token']); abort_unless($draft,419);
  if($draft['source_id']??null) return redirect()->route('companies.show',$draft['source_id']);
  return redirect()->route($draft['type']==='company'?'companies.create':'contacts.create')->withInput($draft['input']);
 }
 public function existing(Request $request,Company $company,DuplicateMatcher $matcher) {
  return $this->preview($request,'company',$company->toArray(),$matcher->find('company',$company->toArray(),$request->user(),$company->id),$company->id);
 }
 public function decide(Request $request,DuplicateMatcher $matcher,MatchingDecisions $decisions) {
  $d=$request->validate(['token'=>['required','uuid'],'action'=>['required','in:same,new'],'target_id'=>['nullable','integer'],'different'=>['nullable','array','max:100'],'different.*'=>['integer','distinct'],'reason'=>['required','string','min:3','max:1000']]);
  $draft=$request->session()->get('matching.'.$d['token']); abort_unless($draft && $draft['expires']>=now()->timestamp,419,'İnceleme süresi doldu; yeniden kontrol edin.');
  $type=$draft['type']; $input=$draft['input']; $sourceId=$draft['source_id']??null;
  if($sourceId) { $source=Company::findOrFail($sourceId); abort_unless($matcher->fingerprint($source->toArray())===$matcher->fingerprint($input),409); }
  $result=$matcher->find($type,$input,$request->user(),$sourceId);
  // Do not reuse a review after scores, candidate identity or settings changed.
  abort_unless($result===$draft['result'],409,'Eşleşmeler değişti; formu yeniden kontrol edin.');
  $allowed=array_column($result['matches'],'id');
  foreach($d['different']??[] as $id) abort_unless(in_array((int)$id,$allowed,true),422);
  if($d['action']==='same') abort_unless(in_array((int)($d['target_id']??0),$allowed,true),422);
  $record=DB::transaction(function()use($request,$d,$type,$input,$result,$decisions,$sourceId,$matcher){
   DB::table('matching_settings')->where('id',1)->lockForUpdate()->first();
   if($sourceId) abort_unless($matcher->fingerprint(Company::findOrFail($sourceId)->toArray())===$matcher->fingerprint($input),409);
   abort_unless($matcher->find($type,$input,$request->user(),$sourceId)===$result,409,'Eşleşmeler değişti; yeniden inceleyin.');
   if($d['action']==='same') {
    $target=$type==='company'?Company::findOrFail($d['target_id']):Contact::visibleTo($request->user())->findOrFail($d['target_id']);
    $decisions->record($request->user(),$type,$sourceId,$target->id,'same',$input,$target->toArray(),$d['reason'],$result);
    return $target;
   }
   $new=$sourceId?Company::findOrFail($sourceId):($type==='company'?app(CompanyService::class)->save($request->user(),$input,null,true):$this->createContact($request,$input));
   foreach($d['different']??[] as $id) {
    $target=$type==='company'?Company::findOrFail($id):Contact::visibleTo($request->user())->findOrFail($id);
    $decisions->record($request->user(),$type,$new->id,$target->id,'different',$new->toArray(),$target->toArray(),$d['reason'],$result);
   }
   $decisions->event($request->user(),$sourceId?'different_decision_saved':'separate_record_created',null,['entity_type'=>$type,'entity_id'=>$new->id,'reason'=>$d['reason']]);
   return $new;
  });
  $request->session()->forget('matching.'.$d['token']);
  return redirect()->route($type==='company'?'companies.show':'contacts.show',$record)->with('status',$d['action']==='same'?'Onayınız kaydedildi. Mevcut kayıt açıldı; hiçbir kayıt birleştirilmedi.':'İşaretlediğiniz farklılık kararları kaydedildi; kayıtlar ayrı tutuldu.');
 }
 public function createContact(Request $request,array $data): Contact {
  DB::table('matching_settings')->where('id',1)->lockForUpdate()->first();
  Company::findOrFail($data['company_id']);
  $contact=Contact::create(array_merge(collect($data)->only(['name','company_id','email','phone'])->all(),['created_by'=>$request->user()->id]));
  app(DuplicateMatcher::class)->index('contact',$contact->toArray());
  app(MatchingDecisions::class)->event($request->user(),'contact_created',null,['contact_id'=>$contact->id]);
  return $contact;
 }
 public function alias(Request $request,DuplicateMatcher $matcher,MatchingDecisions $decisions) {
  $data=$request->validate(['company_id'=>['required','integer'],'alias'=>['required','string','max:180'],'reason'=>['required','string','min:3','max:1000'],'confirmed'=>['accepted']]);
  $data['alias']=trim($data['alias']); abort_if($data['alias']==='',422);
  $company=Company::findOrFail($data['company_id']);
  $input=['name'=>$data['alias']]; $score=$matcher->score('company',$input,$company->toArray(),$matcher->settings());
  $decisions->record($request->user(),'company',null,$company->id,'same',$input,$company->toArray(),$data['reason'],['matches'=>[array_merge($score,['id'=>$company->id,'name'=>$company->name])],'settings'=>$matcher->settings()]);
  return back()->with('status','İsim varyasyonu açık onayınızla kaydedildi. Firma adı değiştirilmedi.');
 }
 public function index(Request $request) {
  $rows=DB::table('matching_decisions')->when(!$request->user()->isAdmin(),fn($q)=>$q->where('actor_id',$request->user()->id))->orderByDesc('id')->paginate(25);
  $aliases=DB::table('self_learning_company_dictionary as d')->join('matching_decisions as m','m.id','=','d.decision_id')->join('companies as c','c.id','=','d.company_id')->whereNull('m.revoked_at')->whereNull('c.deleted_at')->select('d.alias','c.name','c.id')->orderBy('d.id','desc')->limit(100)->get();
  $settings=app(DuplicateMatcher::class)->settings(); $version=DB::table('matching_settings')->where('id',1)->value('version');
  $events=$request->user()->isAdmin()?DB::table('matching_events')->orderByDesc('id')->paginate(25,['*'],'events'):null;
  $companies=Company::orderBy('name')->get(['id','name']);
  return view('matching.index',compact('companies','rows','aliases','settings','version','events'));
 }
 public function revoke(Request $request,int $decision,MatchingDecisions $decisions) {
  abort_unless($request->user()->isAdmin(),403);
  $d=$request->validate(['reason'=>['required','string','min:3','max:1000']]); $decisions->revoke($request->user(),$decision,$d['reason']);
  return back()->with('status','Karar geri alındı; geçmiş silinmedi.');
 }
 public function settings(Request $request,MatchingDecisions $decisions) {
  abort_unless($request->user()->isAdmin(),403); $rules=['version'=>['required','integer'],'reason'=>['required','string','min:3','max:1000']];
  foreach(array_keys(app(DuplicateMatcher::class)->settings()) as $key) $rules[$key]=['required','integer','min:'.(str_contains($key,'threshold')||$key==='name_similarity'?1:0),'max:100'];
  $d=$request->validate($rules); abort_unless($d['strong_threshold']>$d['review_threshold'],422,'Güçlü uyarı eşiği inceleme eşiğinden büyük olmalı.');
  DB::transaction(function()use($request,$d,$decisions){
   $old=DB::table('matching_settings')->where('id',1)->lockForUpdate()->first(); abort_unless($old->version===$d['version'] || (int)$old->version===(int)$d['version'],409);
   $values=collect($d)->except(['version','reason'])->map(fn($v)=>(int)$v)->all();
   DB::table('matching_settings')->where('id',1)->update(['values'=>json_encode($values),'version'=>$old->version+1,'updated_at'=>now()]);
   $decisions->event($request->user(),'settings_changed',null,['before'=>json_decode($old->values,true),'after'=>$values,'reason'=>$d['reason']]);
  }); return back()->with('status','Eşleşme ayarları kaydedildi.');
 }
}
