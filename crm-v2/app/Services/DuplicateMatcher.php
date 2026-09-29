<?php
namespace App\Services;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class DuplicateMatcher {
 public function settings(): array { return json_decode(DB::table('matching_settings')->where('id',1)->value('values'),true); }
 public function normalize(?string $s): string { return trim(preg_replace('/[^\pL\pN]+/u',' ',mb_strtolower(str_replace(['İ','I','ı'],['i','i','i'],$s ?? '')))); }
 public function email(?string $s): string { return mb_strtolower(trim($s ?? '')); }
 public function phone(?string $s): string { $s=preg_replace('/[^0-9+]/','',$s ?? ''); return str_starts_with($s,'00') ? '+'.substr($s,2) : $s; }
 public function domain(?string $s): string {
  if (!$s) return ''; $host=parse_url(str_contains($s,'://')?$s:'https://'.$s,PHP_URL_HOST);
  return preg_replace('/^www\./','',strtolower(rtrim($host ?? '', '.')));
 }
 public function fingerprint(array $d): string {
  $values=[]; foreach(['name','country_code','city','email','phone','website','tax_number','company_id'] as $key) $values[$key]=(string)($d[$key]??'');
  return hash('sha256',json_encode($values,JSON_UNESCAPED_UNICODE));
 }
 public function keys(array $d): array {
  $keys=['name'=>$this->normalize($d['name']??''),'email'=>$this->email($d['email']??''),'phone'=>$this->phone($d['phone']??''),'domain'=>$this->domain($d['website']??'')];
  if (!empty($d['tax_number']) && !empty($d['country_code'])) $keys['tax']=$d['country_code'].'|'.$this->normalize($d['tax_number']);
  return array_filter($keys,fn($v)=>$v!=='');
 }
 public function index(string $type,array $d): void {
  DB::table('matching_keys')->where('entity_type',$type)->where('entity_id',$d['id'])->delete();
  $keys=$this->keys($d); $entries=[];
  foreach($keys as $kind=>$value) $entries[]=['entity_type'=>$type,'entity_id'=>$d['id'],'kind'=>$kind,'value'=>hash('sha256',$value)];
  $names=[$d['name']];
  if($type==='company') $names=array_merge($names,DB::table('self_learning_company_dictionary as d')->join('matching_decisions as m','m.id','=','d.decision_id')->whereNull('m.revoked_at')->where('d.company_id',$d['id'])->pluck('d.alias')->all());
  foreach(array_unique(explode(' ',$this->normalize(implode(' ',$names)))) as $token) if(mb_strlen($token)>=3) $entries[]=['entity_type'=>$type,'entity_id'=>$d['id'],'kind'=>'prefix','value'=>hash('sha256',mb_substr($token,0,3))];
  if($entries) DB::table('matching_keys')->insert($entries);
 }
 public function similarity(string $nameA,string $nameB): int {
  $aa=preg_split('//u',$nameA,-1,PREG_SPLIT_NO_EMPTY); $bb=preg_split('//u',$nameB,-1,PREG_SPLIT_NO_EMPTY);
  $prev=range(0,count($bb)); foreach($aa as $i=>$ca) { $row=[$i+1]; foreach($bb as $j=>$cb) $row[] = min($row[$j]+1,$prev[$j+1]+1,$prev[$j]+($ca===$cb?0:1)); $prev=$row; }
  return ($nameA!==''&&$nameB!=='') ? (int)round(100*(1-end($prev)/max(count($aa),count($bb)))) : 0;
 }
 public function score(string $type,array $input,array $candidate,array $weights,bool $alias=false): array {
  $a=$this->keys($input); $b=$this->keys($candidate); $points=0; $reasons=[]; $conflicts=[];
  $labels=['tax'=>'Aynı ülke ve vergi numarası','domain'=>'Aynı web sitesi alan adı','email'=>'Aynı e-posta','phone'=>'Aynı telefon'];
  foreach($labels as $key=>$label) {
   if(isset($a[$key],$b[$key])) {
    if($a[$key]===$b[$key]) { $points+=$weights[$key]; $reasons[]=['text'=>$label,'points'=>$weights[$key]]; }
    else $conflicts[]=['tax'=>'Vergi numarası farklı','domain'=>'Web sitesi alan adı farklı','email'=>'E-posta farklı','phone'=>'Telefon farklı'][$key];
   }
  }
  $nameA=$a['name']??''; $nameB=$b['name']??'';
  $similarity=$this->similarity($nameA,$nameB);
  if($similarity >= $weights['name_similarity']) { $points+=$weights['name']; $reasons[]=['text'=>'İsim benzerliği %'.$similarity,'points'=>$weights['name']]; }
  elseif($nameA!==''&&$nameB!=='') $conflicts[]='İsim benzerliği %'.$similarity.' (eşik altında)';
  if($alias) { $points+=$weights['alias']; $reasons[]=['text'=>'Kullanıcı tarafından onaylanmış isim varyasyonu','points'=>$weights['alias']]; }
  if($type==='contact' && !empty($input['company_id']) && (int)$input['company_id']===(int)($candidate['company_id']??0) && $nameA!=='' && $nameA===$nameB) { $points+=$weights['contact_company']; $reasons[]=['text'=>'Aynı kişi adı ve firma','points'=>$weights['contact_company']]; }
  if(!empty($input['country_code']) && $input['country_code']===($candidate['country_code']??null) && !empty($input['city']) && $this->normalize($input['city'])===$this->normalize($candidate['city']??'')) { $points+=$weights['location']; $reasons[]=['text'=>'Aynı şehir ve ülke','points'=>$weights['location']]; }
  $score=min(100,$points);
  return ['duplicate_confidence_score'=>$score,'level'=>$score >= $weights['strong_threshold']?'strong':($score >= $weights['review_threshold']?'review':'low'),'reasons'=>$reasons,'conflicts'=>$conflicts,'name_similarity'=>$similarity];
 }
 public function find(string $type,array $input,User $actor,?int $sourceId=null): array {
  $weights=$this->settings(); $keys=$this->keys($input); $pairs=[];
  foreach($keys as $kind=>$v) $pairs[]=[$kind,hash('sha256',$v)];
  foreach(explode(' ',$keys['name']??'') as $token) if(mb_strlen($token)>=3) $pairs[]=['prefix',hash('sha256',mb_substr($token,0,3))];
  $ids=DB::table('matching_keys')->where('entity_type',$type)->where(function($q)use($pairs){ foreach($pairs as [$k,$v]) $q->orWhere(fn($c)=>$c->where('kind',$k)->where('value',$v)); });
  $aliases=[];
  if($type==='company') $aliases=DB::table('self_learning_company_dictionary as d')->join('matching_decisions as m','m.id','=','d.decision_id')->whereNull('m.revoked_at')->where('d.normalized_alias',hash('sha256',$keys['name']??''))->pluck('d.company_id')->all();
  $query=$type==='company'?Company::query():Contact::visibleTo($actor);
  $query->where(fn($q)=>$q->whereIn('id',$ids->select('entity_id'))->orWhereIn('id',$aliases))->when($sourceId,fn($q)=>$q->where('id','!=',$sourceId));
  $found=[]; $suppressed=0;
  foreach($query->orderBy('id')->lazyById(100) as $record) {
   $candidate=$record->toArray();
   $aliasSimilarity=0;
   if($type==='company') foreach(DB::table('self_learning_company_dictionary as d')->join('matching_decisions as m','m.id','=','d.decision_id')->whereNull('m.revoked_at')->where('d.company_id',$record->id)->pluck('d.alias') as $known) $aliasSimilarity=max($aliasSimilarity,$this->similarity($keys['name']??'',$this->normalize($known)));
   $score=$this->score($type,$input,$candidate,$weights,$aliasSimilarity >= $weights['name_similarity']);
   if($aliasSimilarity >= $weights['name_similarity']) $score['reasons'][]=['text'=>'Onaylı varyasyonla isim benzerliği %'.$aliasSimilarity,'points'=>0];
   if($score['duplicate_confidence_score']===0) continue;
   $sourceFingerprint=$this->fingerprint($input); $targetFingerprint=$this->fingerprint($candidate);
   $different=DB::table('matching_decisions')->where('entity_type',$type)->where('decision','different')->whereNull('revoked_at')->where(function($q)use($sourceId,$record,$sourceFingerprint,$targetFingerprint){
    $q->where(fn($x)=>$x->where('source_id',$sourceId)->where('target_id',$record->id)->where('source_fingerprint',$sourceFingerprint)->where('target_fingerprint',$targetFingerprint));
    if($sourceId) $q->orWhere(fn($x)=>$x->where('source_id',$record->id)->where('target_id',$sourceId)->where('source_fingerprint',$targetFingerprint)->where('target_fingerprint',$sourceFingerprint));
   })->exists();
   if($different) { $suppressed++; continue; }
   $found[]=array_merge($score,['id'=>$record->id,'name'=>$record->name,'fingerprint'=>$this->fingerprint($candidate)]);
  }
  usort($found,fn($a,$b)=>$b['duplicate_confidence_score']<=>$a['duplicate_confidence_score'] ?: $a['id']<=>$b['id']);
  return ['matches'=>$found,'suppressed'=>$suppressed,'settings'=>$weights];
 }
}
