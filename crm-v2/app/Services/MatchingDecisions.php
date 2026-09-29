<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class MatchingDecisions {
 public function record(User $actor,string $type,?int $sourceId,int $targetId,string $decision,array $input,array $target,string $reason,array $evidence): int {
  abort_unless($actor->active && in_array($type,['company','contact'],true) && in_array($decision,['same','different'],true),403);
  return DB::transaction(function()use($actor,$type,$sourceId,$targetId,$decision,$input,$target,$reason,$evidence){
  DB::table('matching_settings')->where('id',1)->lockForUpdate()->first();
  $matcher=app(DuplicateMatcher::class);
  if($type==='company' && $decision==='same') {
   $existing=DB::table('matching_decisions')->where('entity_type','company')->where('decision','same')->where('target_id',$targetId)->where('alias_key',hash('sha256',$matcher->normalize($input['name'])))->whereNull('revoked_at')->first();
   if($existing) { $this->event($actor,'alias_reconfirmed',$existing->id,['reason'=>$reason,'source_id'=>$sourceId]); return $existing->id; }
  }
  $id=DB::table('matching_decisions')->insertGetId(['entity_type'=>$type,'source_id'=>$sourceId,'target_id'=>$targetId,'decision'=>$decision,'alias'=>$type==='company'&&$decision==='same'?$input['name']:null,'alias_key'=>$type==='company'&&$decision==='same'?hash('sha256',$matcher->normalize($input['name'])):null,'source_fingerprint'=>$matcher->fingerprint($input),'target_fingerprint'=>$matcher->fingerprint($target),'actor_id'=>$actor->id,'reason'=>$reason,'evidence'=>json_encode($evidence),'created_at'=>now()]);
  if($type==='company' && $decision==='same') DB::table('self_learning_company_dictionary')->insert(['company_id'=>$targetId,'decision_id'=>$id,'alias'=>$input['name'],'normalized_alias'=>hash('sha256',$matcher->normalize($input['name'])),'created_at'=>now()]);
  if($type==='company' && $decision==='same') $matcher->index('company',$target);
  $this->event($actor,'decision_confirmed',$id,['decision'=>$decision,'entity_type'=>$type,'source_id'=>$sourceId,'target_id'=>$targetId]);
  return $id;
  });
 }
 public function revoke(User $actor,int $id,string $reason): void {
  abort_unless($actor->isAdmin(),403);
  DB::transaction(function()use($actor,$id,$reason){
   DB::table('matching_settings')->where('id',1)->lockForUpdate()->first();
   $row=DB::table('matching_decisions')->where('id',$id)->lockForUpdate()->first(); abort_unless($row,404); abort_if($row->revoked_at,409,'Karar zaten geri alınmış.');
   DB::table('matching_decisions')->where('id',$id)->update(['revoked_at'=>now(),'revoked_by'=>$actor->id,'revoke_reason'=>$reason]);
   if($row->entity_type==='company' && $row->decision==='same') { $company=\App\Models\Company::find($row->target_id); if($company) app(DuplicateMatcher::class)->index('company',$company->toArray()); }
   $this->event($actor,'decision_revoked',$id,['reason'=>$reason]);
  });
 }
 public function event(User $actor,string $action,?int $id,array $details): void { DB::table('matching_events')->insert(['actor_id'=>$actor->id,'action'=>$action,'decision_id'=>$id,'details'=>json_encode($details),'created_at'=>now()]); }
}
