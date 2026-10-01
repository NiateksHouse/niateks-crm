<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\KozaRecord;
use App\Models\User;
use Brick\Math\BigDecimal as D;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KozaWorkflow
{
    public function __construct(private KozaAccess $access, private KozaEconomics $economics) {}

    public function fail(string $code, string $field = 'workflow'): never
    {
        throw ValidationException::withMessages([$field => $code]);
    }

    public function required(KozaRecord $record, array $keys): void
    {
        foreach ($keys as $key) {
            if (! isset($record->data[$key]) || $record->data[$key] === '' || $record->data[$key] === []) {
                $this->fail('required', $key);
            }
        }
    }

    public function policy(): ?array
    {
        $row = DB::table('koza_controls')->where('key', 'commercial')->first();

        return $row ? json_decode($row->value, true) + ['version' => $row->version] : null;
    }

    public function event(User $actor, ?KozaRecord $record, string $action, string $reason = '', array $extra = []): void
    {
        DB::table('koza_events')->insert(['record_id' => $record?->id, 'actor_id' => $actor->id, 'action' => $action,
            'version' => $record?->version, 'snapshot' => json_encode($record ? $this->snapshot($record) + $extra : $extra, JSON_THROW_ON_ERROR),
            'reason' => $reason, 'created_at' => now()]);
    }

    public function snapshot(KozaRecord $r): array
    {
        return ['record' => $r->attributesToArray(), 'links' => $r->links()->get()->toArray(),
            'lines' => DB::table('koza_lines')->where('record_id', $r->id)->orderBy('position')->get()->map(fn ($l) => (array) $l)->all()];
    }

    public function digest(KozaRecord $r): string
    {
        $data = $r->data;
        foreach (['dispatch_evidence', 'acceptance', 'delivered_at', 'feedback', 'next_step'] as $key) {
            unset($data[$key]);
        }
        ksort($data);
        $lines = DB::table('koza_lines')->where('record_id', $r->id)->orderBy('position')->get()->map(fn ($l) => [(int) $l->variant_id, $l->sku_id, $l->quantity, $l->unit, $l->unit_price, $l->unit_cost, $l->specification])->all();
        $links = $r->links()->orderBy('relation')->orderBy('target_id')->get()->map(fn ($l) => [$l->relation, $l->target_id, $l->quantity, $l->unit])->all();

        return hash('sha256', json_encode([$r->type, $r->company_id, $data, $lines, $links, $this->policy()['version'] ?? null], JSON_THROW_ON_ERROR));
    }

    public function approved(KozaRecord $r, string $kind): bool
    {
        return DB::table('koza_approvals')->where('record_id', $r->id)->where('kind', $kind)->where('digest', $this->digest($r))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }

    private function approval(User $actor, KozaRecord $r, string $kind, string $reason): void
    {
        DB::table('koza_approvals')->insert(['record_id' => $r->id, 'version' => $r->version, 'kind' => $kind,
            'digest' => $this->digest($r), 'actor_id' => $actor->id, 'reason' => $reason,
            'expires_at' => isset($r->data['valid_until']) ? $r->data['valid_until'].' 23:59:59' : null, 'created_at' => now()]);
    }

    public function save(User $actor, array $input, ?int $id = null): KozaRecord
    {
        return DB::transaction(function () use ($actor, $input, $id) {
            $r = $id ? $this->access->visible($actor)->lockForUpdate()->findOrFail($id) : new KozaRecord;
            $type = $id ? $r->type : ($input['type'] ?? '');
            abort_unless(array_key_exists($type, config('koza.catalog')), 422, 'invalid_type');
            abort_unless($id ? $this->access->editable($actor, $r) : $this->access->canCreate($actor, $type), 403);
            if ($id) {
                abort_unless($r->version === (int) ($input['version'] ?? 0), 409, 'version_conflict');
                if ($r->immutable) {
                    $this->fail('immutable');
                }
            }
            $v = Validator::make($input, ['title' => 'required|string|max:180', 'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
                'contact_id' => ['nullable', 'integer'], 'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('active', true)], 'source' => 'nullable|string|max:240', 'due_at' => 'nullable|date_format:Y-m-d', 'data' => 'present|array', 'links' => 'sometimes|array|max:100', 'lines' => 'sometimes|array|max:100']);
            $v->validate();
            if ($id && $r->company_id !== ($input['company_id'] ?? null) && $r->company_id !== (int) ($input['company_id'] ?? 0)) {
                $this->fail('company_immutable');
            }
            $fields = $this->access->fields($actor, $type, true);
            $raw = $input['data'];
            foreach (array_keys($raw) as $key) {
                if (! isset($fields[$key])) {
                    abort(403, 'field_forbidden');
                }
            }
            $rules = [];
            foreach ($fields as $key => $field) {
                $rules[$key] = match ($field['type']) {
                    'money','number' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
                    'date' => ['nullable', 'date_format:Y-m-d'], 'datetime' => ['nullable', 'date'], 'bool' => ['boolean'],
                    'select' => ['nullable', Rule::in($field['options'])], 'email' => ['nullable', 'email', 'max:254'],
                    'url' => ['nullable', 'url:http,https', 'max:1000'], default => ['nullable', 'string', 'max:'.($field['type'] === 'textarea' ? 6000 : 1000)],
                };
            }
            Validator::make($raw, $rules)->validate();
            $data = array_replace($r->data ?? [], $raw);
            if ($id && (($type === 'order') || ($type === 'shipment' && $r->state !== 'draft'))) {
                $fixed = $type === 'order' ? ['po', 'currency'] : ['direction', 'quantity', 'unit', 'shipped_at'];
                foreach ($fixed as $field) {
                    if (($data[$field] ?? null) !== ($r->data[$field] ?? null)) {
                        $this->fail('frozen_scope');
                    }
                }
                if (isset($input['links'])) {
                    $normalize = fn ($links) => collect($links)->map(fn ($l) => ($l['relation'].':'.$l['target_id'].':'.(($l['quantity'] ?? null) === null ? '' : D::of((string) $l['quantity'])->stripTrailingZeros()).':'.($l['unit'] ?? '')))->sort()->values()->all();
                    $prior = $r->links()->get(['relation', 'target_id', 'quantity', 'unit'])->toArray();
                    if ($normalize($prior) !== $normalize($input['links'])) {
                        $this->fail('frozen_scope');
                    }
                }
            }
            if ($type === 'order' && $id) {
                foreach (['payment_status', 'payment_evidence', 'payment_plan'] as $f) {
                    if (($data[$f] ?? null) !== ($r->data[$f] ?? null) && ! $this->access->has($actor, 'commercial')) {
                        abort(403);
                    }
                }
                if (! empty($r->data['original_promise']) && ($data['original_promise'] ?? null) !== $r->data['original_promise']) {
                    $this->fail('original_promise_immutable');
                }
                if (($data['revised_promise'] ?? null) !== ($r->data['revised_promise'] ?? null) && empty($data['change_reason'])) {
                    $this->fail('required', 'change_reason');
                }
                if (in_array($data['payment_status'] ?? 'unknown', ['partial', 'paid']) && empty($data['payment_evidence'])) {
                    $this->fail('required', 'payment_evidence');
                }
            }
            if (! $id) {
                $r->type = $type;
                $r->owner_id = $actor->id;
                $r->state = $type === 'account' ? 'research' : 'draft';
                $r->version = 0;
                $r->edition = 1;
                if (in_array($type, ['account', 'contact', 'opportunity', 'quote', 'sample', 'offering', 'service']) && empty($input['company_id'])) {
                    $this->fail('required', 'company_id');
                }
                if ($type === 'account') {
                    $r->dedup_key = 'account:'.$input['company_id'];
                }
            }
            if ($type === 'account' && ! $id && KozaRecord::where('dedup_key', $r->dedup_key)->exists()) {
                $this->fail('duplicate_account');
            }
            if ($type === 'cost') {
                if (empty($data['external_reference'])) {
                    $this->fail('required', 'external_reference');
                }
                $r->dedup_key = 'cost:'.hash('sha256', trim($data['external_reference']));
                if (KozaRecord::where('dedup_key', $r->dedup_key)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                    $this->fail('duplicate_cost');
                }
            }
            if (! empty($input['owner_id']) && (int) $input['owner_id'] !== $r->owner_id) {
                abort_unless($type !== 'capability' && $this->access->has($actor, config('koza.catalog.'.$type.'.domain')), 403);
                $r->owner_id = (int) $input['owner_id'];
            }
            if ($type === 'contact' && ($data['kind'] ?? null) === 'person') {
                $canonicalId = $r->contact_id ?: ($input['contact_id'] ?? null);
                DB::table('matching_settings')->where('id', 1)->lockForUpdate()->first();
                if ($canonicalId) {
                    $contact = Contact::visibleTo($actor)->lockForUpdate()->findOrFail($canonicalId);
                    if ($contact->company_id != $input['company_id']) {
                        $this->fail('company_mismatch');
                    }
                    if ($id) {
                        abort_unless($contact->created_by === $actor->id || $actor->isAdmin(), 403);
                        $identity = ['name' => $input['title'], 'email' => app(DuplicateMatcher::class)->email($data['email'] ?? '') ?: null, 'phone' => app(DuplicateMatcher::class)->phone($data['phone'] ?? '') ?: null];
                        $matches = app(DuplicateMatcher::class)->find('contact', $identity + ['company_id' => $contact->company_id], $actor, $contact->id);
                        if (collect($matches['matches'])->contains(fn ($m) => $m['level'] !== 'low')) {
                            $this->fail('duplicate_contact');
                        }
                        $contact->fill($identity);
                        $contact->save();
                        app(DuplicateMatcher::class)->index('contact', $contact->toArray());
                        app(MatchingDecisions::class)->event($actor, 'contact_updated', null, ['contact_id' => $contact->id]);
                    } else {
                        // Selecting an existing person adopts their canonical identity.
                        $input['title'] = $contact->name;
                        $data['email'] = $contact->email;
                        $data['phone'] = $contact->phone;
                    }
                } else {
                    $matcher = app(DuplicateMatcher::class);
                    $identity = ['name' => $input['title'], 'company_id' => $input['company_id'], 'email' => $matcher->email($data['email'] ?? '') ?: null, 'phone' => $matcher->phone($data['phone'] ?? '') ?: null];
                    $matches = $matcher->find('contact', $identity, $actor);
                    if (collect($matches['matches'])->contains(fn ($m) => $m['level'] !== 'low')) {
                        $this->fail('duplicate_contact');
                    }
                    $contact = Contact::create($identity + ['created_by' => $actor->id]);
                    $matcher->index('contact', $contact->toArray());
                    app(MatchingDecisions::class)->event($actor, 'contact_created', null, ['contact_id' => $contact->id]);
                }
                if (KozaRecord::where('contact_id', $contact->id)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                    $this->fail('duplicate_contact');
                }
                $r->contact_id = $contact->id;
            }
            $r->fill(['title' => $input['title'], 'company_id' => $input['company_id'] ?? null, 'data' => $data, 'source' => $input['source'] ?? null, 'due_at' => $input['due_at'] ?? null]);
            $r->version++;
            $r->verified_at = null;
            $r->verified_by = null;
            if (in_array($type, ['quote', 'sample', 'variant', 'offering'])) {
                $r->state = 'draft';
            }
            $r->save();
            if (isset($input['links'])) {
                $this->saveLinks($actor, $r, $input['links']);
            }
            if (isset($input['lines'])) {
                if ($type !== 'quote') {
                    $this->fail('lines_immutable');
                }
                $this->saveLines($actor, $r, $input['lines']);
            }
            // Opt-out cancels marketing tasks only. It cannot suppress service obligations.
            if (in_array($type, ['account', 'contact']) && ($data['opt_out'] ?? false)) {
                $tasks = KozaRecord::where('type', 'task')->where('company_id', $r->company_id)->whereNotIn('state', ['done', 'cancelled'])->where('data->purpose', 'marketing')->lockForUpdate()->get();
                foreach ($tasks as $task) {
                    $task->state = 'cancelled';
                    $task->version++;
                    $task->save();
                    $this->event($actor, $task, 'opt_out_cancelled');
                }
            }
            $this->event($actor, $r, $id ? 'updated' : 'created');

            return $r->refresh();
        }, 3);
    }

    private function saveLinks(User $actor, KozaRecord $r, array $links): void
    {
        Validator::make(['links' => $links], ['links.*.relation' => 'required|string|max:40', 'links.*.target_id' => 'required|integer',
            'links.*.quantity' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,4})?$/'], 'links.*.unit' => 'nullable|string|max:20'])->validate();
        $rows = [];
        foreach ($links as $link) {
            $types = config('koza.catalog.'.$r->type.'.relations.'.$link['relation'], []);
            $target = $this->access->visible($actor)->lockForUpdate()->findOrFail($link['target_id']);
            if (! in_array($target->type, $types, true) || $r->id === $target->id) {
                $this->fail('invalid_relation');
            }
            if ($r->company_id && $target->company_id && $r->company_id !== $target->company_id) {
                $this->fail('company_mismatch');
            }
            if ($r->type === 'task' && $target->type === 'task') {
                $this->fail('invalid_relation');
            }
            $key = $link['relation'].':'.$target->id;
            if (isset($rows[$key])) {
                $this->fail('duplicate_relation');
            }
            $rows[$key] = ['record_id' => $r->id, 'target_id' => $target->id, 'relation' => $link['relation'], 'quantity' => $link['quantity'] ?? null, 'unit' => $link['unit'] ?? null];
        }
        $r->links()->delete();
        if ($rows) {
            DB::table('koza_links')->insert(array_values($rows));
        }
    }

    private function saveLines(User $actor, KozaRecord $r, array $lines): void
    {
        Validator::make(['lines' => $lines], ['lines.*.variant_id' => 'required|integer', 'lines.*.sku_id' => 'nullable|integer',
            'lines.*.quantity' => ['required', 'regex:/^\d{1,12}(\.\d{1,4})?$/', 'numeric', 'gt:0'], 'lines.*.unit' => 'required|string|max:20',
            'lines.*.unit_price' => ['required', 'regex:/^\d{1,12}(\.\d{1,4})?$/'], 'lines.*.unit_cost' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,4})?$/']])->validate();
        $old = DB::table('koza_lines')->where('record_id', $r->id)->get()->keyBy('id');
        $rows = [];
        $identities = [];
        foreach ($lines as $i => $line) {
            $identity = $line['variant_id'].':'.$line['unit'];
            if (isset($identities[$identity])) {
                $this->fail('duplicate_variant_line');
            }
            $identities[$identity] = true;
            $variant = $this->access->visible($actor)->where('type', 'variant')->lockForUpdate()->findOrFail($line['variant_id']);
            if (! empty($line['sku_id'])) {
                $sku = $this->access->visible($actor)->where('type', 'sku')->findOrFail($line['sku_id']);
                if (! $sku->links()->where('relation', 'variant')->where('target_id', $variant->id)->exists()) {
                    $this->fail('sku_variant_mismatch');
                }
            }
            $prior = $old[$line['id'] ?? 0] ?? null;
            if (! $this->access->money($actor, 'cost') && array_key_exists('unit_cost', $line)) {
                abort(403);
            }
            if ($this->access->has($actor, 'operations') && ! $this->access->has($actor, 'commercial') && ! $this->access->has($actor, 'market')) {
                if (! $prior || $prior->variant_id != $line['variant_id'] || D::of($prior->quantity)->compareTo($line['quantity']) !== 0 || D::of($prior->unit_price)->compareTo($line['unit_price']) !== 0 || $prior->unit !== $line['unit'] || $prior->sku_id != ($line['sku_id'] ?? null)) {
                    abort(403);
                }
            }
            $cost = $this->access->money($actor, 'cost') ? ($line['unit_cost'] ?? null) : ($prior && $prior->variant_id === $variant->id ? $prior->unit_cost : null);
            $rows[] = ['record_id' => $r->id, 'position' => $i + 1, 'variant_id' => $variant->id, 'sku_id' => $line['sku_id'] ?? null, 'quantity' => $line['quantity'], 'unit' => $line['unit'],
                'unit_price' => $line['unit_price'], 'unit_cost' => $cost, 'specification' => json_encode(['id' => $variant->id, 'edition' => $variant->edition, 'version' => $variant->version, 'title' => $variant->title, 'data' => $variant->data], JSON_THROW_ON_ERROR)];
        }
        DB::table('koza_lines')->where('record_id', $r->id)->delete();
        if ($rows) {
            DB::table('koza_lines')->insert($rows);
        }
    }

    public function linked(KozaRecord $r, string $relation, bool $required = true): Collection
    {
        $records = KozaRecord::whereIn('id', $r->links()->where('relation', $relation)->select('target_id'))->lockForUpdate()->get();
        if ($required && $records->isEmpty()) {
            $this->fail('required_relation', $relation);
        }

        return $records;
    }

    public function fresh(KozaRecord $r): void
    {
        $policy = $this->policy();
        if (! $policy) {
            $this->fail('policy_missing');
        }
        if (KozaRecord::where('type', 'dependency')->where('state', 'degraded')->whereIn('data->dependency_kind', ['cost', 'stock'])->exists()) {
            $this->fail('source_degraded');
        }
        foreach (['cost_checked_at' => 'cost_hours', 'stock_checked_at' => 'stock_hours'] as $field => $limit) {
            $this->required($r, [$field]);
            $time = CarbonImmutable::parse($r->data[$field]);
            if ($time->isFuture() || $time->lt(now()->subHours($policy[$limit]))) {
                $this->fail('stale_data', $field);
            }
        }
        if (isset($r->data['valid_until']) && $r->data['valid_until'] < now()->toDateString()) {
            $this->fail('expired');
        }
        foreach (DB::table('koza_lines')->where('record_id', $r->id)->get() as $line) {
            $v = KozaRecord::lockForUpdate()->findOrFail($line->variant_id);
            $snapshot = json_decode($line->specification, true);
            if ($v->state !== 'verified' || ($v->data['concept'] ?? false) || $v->version !== $snapshot['version']) {
                $this->fail('technical_version_changed');
            }
            if (empty($v->data['valid_until']) || $v->data['valid_until'] < now()->toDateString()) {
                $this->fail('technical_expired');
            }
            if ($line->sku_id) {
                $sku = KozaRecord::lockForUpdate()->findOrFail($line->sku_id);
                if (empty($sku->data['stock_checked_at']) || CarbonImmutable::parse($sku->data['stock_checked_at'])->lt(now()->subHours($policy['stock_hours']))) {
                    $this->fail('stale_stock');
                }
                if (D::of($sku->data['stock'] ?? '0')->isLessThan($line->quantity)) {
                    $this->fail('insufficient_stock');
                }
            }
        }
    }

    public function action(User $actor, int $id, array $input): KozaRecord
    {
        Validator::make($input, ['version' => 'required|integer', 'action' => 'required|string|max:40', 'reason' => 'required|string|max:3000', 'state' => 'nullable|string|max:40', 'evidence' => 'nullable|string|max:6000', 'occurred_at' => 'nullable|date'])->validate();

        return DB::transaction(function () use ($actor, $id, $input) {
            $r = $this->access->visible($actor)->lockForUpdate()->findOrFail($id);
            $action = $input['action'];
            abort_unless($this->access->editable($actor, $r) || $action === 'stop_ai' || (in_array($action, ['approve', 'right_business']) && $this->access->has($actor, 'commercial')) || ($action === 'verify' && $this->access->has($actor, 'operations')) || ($action === 'reorder' && $this->access->has($actor, 'market')), 403);
            if ($action === 'won' && $r->state === 'won') {
                return KozaRecord::where('dedup_key', 'order:'.$r->id)->firstOrFail();
            }
            abort_unless($r->version === (int) $input['version'], 409, 'version_conflict');
            $reason = $input['reason'];
            if ($action === 'revision') {
                return $this->revision($actor, $r, $reason);
            }
            if ($action === 'won') {
                return $this->won($actor, $r, $reason);
            }
            if ($action === 'reorder') {
                return $this->reorder($actor, $r, $reason);
            }
            if ($action === 'stop_ai') {
                if ($r->type !== 'right') {
                    $this->fail('invalid_action');
                }
                $r->state = 'stopped';
                $r->immutable = true;
            } elseif ($action === 'verify') {
                abort_unless($this->access->has($actor, 'operations'), 403);
                if ($r->immutable || ! in_array($r->type, ['variant', 'sample', 'quote', 'offering'])) {
                    $this->fail('invalid_action');
                }
                if ($r->type !== 'variant') {
                    abort_unless($this->access->money($actor, 'cost'), 403);
                }
                if ($r->type === 'variant') {
                    $this->required($r, ['composition', 'dimensions', 'weight', 'colour', 'workmanship', 'packaging', 'unit', 'moq', 'lead_time', 'capacity', 'physical_evidence', 'rights_evidence', 'valid_until']);
                    $this->linked($r, 'product');
                    if (($r->data['concept'] ?? false) || $r->data['valid_until'] < now()->toDateString()) {
                        $this->fail('concept_not_saleable');
                    }
                    $r->immutable = true;
                } elseif ($r->type === 'sample') {
                    $this->required($r, ['purpose', 'decision_owner', 'address', 'quantity', 'price_context', 'sample_cost', 'shipping_cost', 'currency', 'feedback_on', 'success_criteria', 'physical_evidence']);
                    $this->linked($r, 'opportunity');
                    foreach ($this->linked($r, 'variant') as $v) {
                        if ($v->state !== 'verified') {
                            $this->fail('technical_verification_required');
                        }
                    }
                } else {
                    $this->required($r, ['technical_evidence', 'stock_checked_at', 'cost_checked_at', 'cost_version']);
                    if (! DB::table('koza_lines')->where('record_id', $r->id)->exists()) {
                        $this->fail('lines_required');
                    }
                    $this->fresh($r);
                    if (! $this->economics->quote($r)['complete']) {
                        $this->fail('cost_incomplete');
                    }
                }
                $r->state = 'verified';
                $r->verified_at = now();
                $r->verified_by = $actor->id;
                $this->approval($actor, $r, 'technical', $reason);
            } elseif ($action === 'approve') {
                abort_unless($this->access->has($actor, 'commercial'), 403);
                if ($r->immutable || ! in_array($r->type, ['sample', 'quote', 'offering'])) {
                    $this->fail('invalid_action');
                }
                if (! $this->approved($r, 'technical')) {
                    $this->fail('technical_verification_required');
                }
                $policy = $this->policy();
                if (! $policy) {
                    $this->fail('policy_missing');
                }
                if ($r->type === 'sample') {
                    if (($r->data['currency'] ?? null) !== $policy['sample_currency']) {
                        $this->fail('policy_currency_mismatch');
                    }
                    if (D::of($r->data['sample_cost'])->plus($r->data['shipping_cost'])->isGreaterThan($policy['sample_budget'])) {
                        $this->fail('sample_budget_exceeded');
                    }
                } else {
                    $this->fresh($r);
                    $this->required($r, ['currency', 'packaging', 'moq', 'incoterm', 'delivery_place', 'lead_time', 'payment_days', 'valid_until']);
                    $calc = $this->economics->quote($r);
                    if (! $calc['complete'] || $calc['rate'] === null) {
                        $this->fail('cost_incomplete');
                    }
                    if (D::of($calc['rate'])->isLessThan($policy['minimum_contribution'])) {
                        $this->fail('contribution_below_policy');
                    }
                    if (D::of($r->data['payment_days'])->isGreaterThan($policy['max_payment_days'])) {
                        $this->fail('payment_terms_exceeded');
                    }
                }
                $this->approval($actor, $r, 'commercial', $reason);
                $r->state = 'approved';
                $r->immutable = true;
            } elseif ($action === 'dispatch') {
                if (! in_array($r->type, ['sample', 'quote']) || $r->state !== 'approved') {
                    $this->fail('approval_required');
                }
                $policy = $this->policy();
                if (! $policy || ! in_array($actor->id, $policy['senders'], true)) {
                    abort(403);
                }
                if (! $this->approved($r, 'commercial') || ! $this->approved($r, 'technical')) {
                    $this->fail('approval_required');
                }
                if ($r->type === 'quote') {
                    $this->fresh($r);
                }
                if (empty($input['evidence'])) {
                    $this->fail('dispatch_evidence_required');
                }
                $data = $r->data;
                $data['dispatch_evidence'] = $input['evidence'];
                $r->data = $data;
                $r->state = 'sent';
            } elseif ($action === 'accept') {
                if ($r->type !== 'quote' || $r->state !== 'sent' || empty($input['evidence'])) {
                    $this->fail('acceptance_required');
                }
                $this->fresh($r);
                if (! $this->approved($r, 'commercial')) {
                    $this->fail('approval_required');
                }
                $data = $r->data;
                $data['acceptance'] = $input['evidence'];
                $r->data = $data;
                $r->state = 'accepted';
            } elseif (in_array($action, ['sample_delivered', 'sample_feedback'])) {
                if ($r->type !== 'sample' || ! in_array($r->state, ['sent', 'delivered']) || empty($input['evidence'])) {
                    $this->fail('invalid_action');
                }
                $data = $r->data;
                if ($action === 'sample_delivered') {
                    if (empty($input['occurred_at']) || CarbonImmutable::parse($input['occurred_at'])->isFuture()) {
                        $this->fail('valid_event_time_required');
                    }
                    $data['delivered_at'] = $input['occurred_at'];
                    $r->state = 'delivered';
                } else {
                    $data['feedback'] = $input['evidence'];
                    $r->state = 'feedback_received';
                }
                $r->data = $data;
            } elseif ($action === 'transition') {
                if ($r->immutable) {
                    $this->fail('immutable');
                }
                $state = $input['state'] ?? '';
                $this->transition($actor, $r, $state, $reason);
                $r->state = $state;
            } elseif ($action === 'right_business' || $action === 'right_technical') {
                if ($r->type !== 'right' || $r->immutable) {
                    $this->fail('invalid_action');
                }
                abort_unless($this->access->has($actor, $action === 'right_business' ? 'commercial' : 'system'), 403);
                $this->required($r, ['decision_class', 'human_owner', 'level', 'scope', 'valid_until', 'freshness', 'stop_method', 'rollback', 'domain']);
                if ($r->data['valid_until'] < now()->toDateString()) {
                    $this->fail('expired');
                }
                // No autonomous execution adapter is connected in this release.
                if ($r->data['level'] === 'execute') {
                    $this->fail('execute_adapter_unconfigured');
                }
                $this->approval($actor, $r, $action, $reason);
                if ($this->approved($r, 'right_business') && $this->approved($r, 'right_technical')) {
                    $r->state = 'active';
                    $r->immutable = true;
                }
            } else {
                $this->fail('invalid_action');
            }
            $r->version++;
            $r->save();
            $this->event($actor, $r, $action, $reason, ['evidence' => $input['evidence'] ?? null]);

            return $r->refresh();
        }, 3);
    }

    private function revision(User $actor, KozaRecord $r, string $reason): KozaRecord
    {
        if (! in_array($r->type, ['variant', 'sample', 'quote', 'offering', 'right'])) {
            $this->fail('invalid_action');
        }
        $root = $r->parent_id ?? $r->id;
        KozaRecord::where('id', $root)->lockForUpdate()->firstOrFail();
        $next = $r->replicate(['dedup_key', 'verified_at', 'verified_by']);
        $next->parent_id = $root;
        $next->edition = max($r->edition, (int) KozaRecord::where('parent_id', $root)->max('edition')) + 1;
        $next->version = 1;
        $next->state = 'draft';
        $next->immutable = false;
        $next->owner_id = $actor->id;
        $data = $next->data;
        foreach (['dispatch_evidence', 'acceptance', 'delivered_at', 'feedback'] as $key) {
            unset($data[$key]);
        } $next->data = $data;
        $next->save();
        foreach ($r->links()->get() as $link) {
            $copy = $link->replicate();
            $copy->record_id = $next->id;
            $copy->save();
        }
        foreach (DB::table('koza_lines')->where('record_id', $r->id)->get() as $line) {
            $row = (array) $line;
            unset($row['id']);
            $row['record_id'] = $next->id;
            DB::table('koza_lines')->insert($row);
        }
        foreach (DB::table('koza_members')->where('record_id', $r->id)->get() as $member) {
            DB::table('koza_members')->insert(['record_id' => $next->id, 'user_id' => $member->user_id]);
        }
        $this->event($actor, $next, 'revision_created', $reason, ['previous_id' => $r->id]);

        return $next;
    }

    private function won(User $actor, KozaRecord $r, string $reason): KozaRecord
    {
        if ($r->type !== 'opportunity' || $r->state !== 'negotiation') {
            $this->fail('invalid_stage');
        }
        $this->required($r, ['closing_evidence', 'reorder_on']);
        $quotes = $this->linked($r, 'quote');
        if ($quotes->count() !== 1) {
            $this->fail('one_accepted_quote_required');
        } $quote = $quotes->first();
        if ($quote->state !== 'accepted' || ! $this->approved($quote, 'commercial') || ! $this->approved($quote, 'technical')) {
            $this->fail('accepted_current_quote_required');
        }
        $this->fresh($quote);
        $existing = KozaRecord::where('dedup_key', 'order:'.$r->id)->first();
        if ($existing) {
            return $existing;
        }
        $order = KozaRecord::create(['type' => 'order', 'title' => $r->title, 'company_id' => $r->company_id, 'owner_id' => $actor->id, 'state' => 'confirmed', 'version' => 1, 'edition' => 1,
            'data' => ['po' => $r->data['closing_evidence'], 'currency' => $quote->data['currency'], 'payment_plan' => $quote->data['payment_days'].' days', 'payment_status' => 'unpaid', 'reorder_on' => $r->data['reorder_on'], 'approved_economics' => $this->economics->quote($quote)], 'dedup_key' => 'order:'.$r->id, 'source' => 'quote:'.$quote->id]);
        $order->links()->createMany([['relation' => 'quote', 'target_id' => $quote->id], ['relation' => 'opportunity', 'target_id' => $r->id]]);
        foreach (DB::table('koza_lines')->where('record_id', $quote->id)->get() as $line) {
            $row = (array) $line;
            unset($row['id']);
            $row['record_id'] = $order->id;
            DB::table('koza_lines')->insert($row);
        }
        $this->event($actor, $order, 'order_created', $reason);
        $task = KozaRecord::create(['type' => 'task', 'title' => $r->title.' · Reorder', 'company_id' => $r->company_id, 'owner_id' => $r->owner_id, 'state' => 'next', 'version' => 1, 'edition' => 1, 'due_at' => $r->data['reorder_on'], 'data' => ['next_step' => 'Post-delivery feedback and reorder review', 'purpose' => 'service'], 'dedup_key' => 'followup:'.$order->id]);
        $task->links()->create(['relation' => 'context', 'target_id' => $order->id]);
        $this->event($actor, $task, 'followup_created');
        $r->state = 'won';
        $r->version++;
        $r->immutable = true;
        $r->save();
        $this->event($actor, $r, 'won', $reason);

        return $order;
    }

    private function reorder(User $actor, KozaRecord $r, string $reason): KozaRecord
    {
        abort_unless($this->access->has($actor, 'market') || $this->access->has($actor, 'commercial'), 403);
        if ($r->type !== 'order' || $r->state !== 'delivered') {
            $this->fail('delivered_order_required');
        }
        $next = KozaRecord::create(['type' => 'opportunity', 'title' => $r->title.' · Reorder', 'company_id' => $r->company_id, 'owner_id' => $actor->id, 'state' => 'draft', 'version' => 1, 'edition' => 1, 'data' => ['kind' => 'reorder', 'next_step' => 'Validate current need, quantity, cost and delivery'], 'source' => 'order:'.$r->id]);
        $next->links()->create(['relation' => 'prior_order', 'target_id' => $r->id]);
        $this->event($actor, $next, 'reorder_created', $reason);
        $r->version++;
        $r->save();
        $this->event($actor, $r, 'reorder_linked', $reason, ['opportunity_id' => $next->id]);

        return $next;
    }

    private function transition(User $actor, KozaRecord $r, string $state, string $reason): void
    {
        if (! in_array($state, config('koza.catalog.'.$r->type.'.states', []), true) || $state === $r->state || $state === 'draft') {
            $this->fail('invalid_stage');
        }
        if (in_array($r->type, ['quote', 'sample', 'variant', 'offering', 'right'])) {
            $this->fail('use_workflow_action');
        }
        if ($r->type === 'account') {
            $stages = ['research', 'qualified', 'contact_ready', 'contacted', 'engaged', 'discovery'];
            if (in_array($state, $stages)) {
                $index = array_search($state, $stages);
                $before = array_search($r->state, $stages);
                if ($before !== false && $index > $before + 1) {
                    $this->fail('invalid_stage');
                }
                if ($index >= 1) {
                    $this->required($r, ['segment', 'product_evidence', 'offer_hypothesis', 'research_date', 'next_step']);
                    if (! $r->source) {
                        $this->fail('source_required');
                    }
                }
                if ($index >= 2) {
                    $this->required($r, ['contact_route', 'personalization', 'cta', 'contact_basis']);
                }
                if ($index >= 3) {
                    $this->required($r, ['contacted_at', 'message_evidence']);
                    if ($this->optedOut($r->company_id)) {
                        $this->fail('marketing_opt_out');
                    }
                }
                if ($index >= 4) {
                    $this->required($r, ['meaningful_response']);
                }
                if ($index >= 5) {
                    $this->required($r, ['need']);
                }
            }
            if ($state === 'nurture') {
                $this->required($r, ['return_on', 'next_step']);
            }
        } elseif ($r->type === 'opportunity') {
            if ($state === 'won') {
                $this->fail('use_won_action');
            }
            if (in_array($state, ['lost', 'nurture', 'stopped'])) {
                $this->required($r, ['loss_reason', 'next_step']);
            } else {
                $stages = ['draft', 'commercially_qualified', 'solution_development', 'sample_quote', 'negotiation'];
                if (array_search($state, $stages) !== array_search($r->state, $stages) + 1) {
                    $this->fail('invalid_stage');
                }
                $this->required($r, ['need', 'category', 'offer_path', 'quantity', 'price_context', 'purchase_on', 'decision_path', 'economic_prefit', 'next_step']);
                if (! $r->company_id) {
                    $this->fail('required', 'company_id');
                }
                if ($r->data['offer_path'] === 'Other' && ! $this->access->has($actor, 'commercial')) {
                    $this->fail('other_path_commercial_approval');
                }
                if (in_array($state, ['sample_quote', 'negotiation'])) {
                    $this->required($r, ['purpose']);
                }
                if ($state === 'negotiation') {
                    $quotes = $this->linked($r, 'quote');
                    if (! $quotes->contains(fn ($q) => in_array($q->state, ['sent', 'accepted']))) {
                        $this->fail('sent_quote_required');
                    }
                    $samples = $this->linked($r, 'sample', false);
                    if (! $samples->contains(fn ($s) => $s->state === 'feedback_received') && empty($r->data['sample_waiver'])) {
                        $this->fail('sample_feedback_or_waiver_required');
                    }
                }
            }
        } elseif ($r->type === 'qc') {
            abort_unless($this->access->has($actor, 'operations'), 403);
            $this->required($r, ['method', 'result', 'tested_on', 'inspector', 'evidence']);
            $this->linked($r, 'lot');
            if ($state === 'released' && ($r->data['result'] !== 'pass' || empty($r->data['release_reason']))) {
                $this->fail('qc_pass_required');
            }
            if ($state === 'released' || $state === 'held') {
                $r->immutable = true;
            }
        } elseif ($r->type === 'lot') {
            $this->required($r, ['quantity', 'unit', 'facility']);
            $this->linked($r, 'variant');
            $this->linked($r, 'manufacturer');
            $this->linked($r, 'material');
            $this->linked($r, 'order');
            if (! empty($r->data['origin']) && empty($r->data['origin_evidence'])) {
                $this->fail('origin_evidence_required');
            }
            if ($state === 'released') {
                foreach ($r->links()->where('relation', 'material')->get() as $allocation) {
                    if (! $allocation->quantity || empty($allocation->unit) || D::of($allocation->quantity)->isLessThanOrEqualTo('0')) {
                        $this->fail('quantity_unit_required');
                    }
                }
                $qc = KozaRecord::where('type', 'qc')->whereHas('links', fn ($q) => $q->where('relation', 'lot')->where('target_id', $r->id))->latest('id')->first();
                if (! $qc || $qc->state !== 'released' || $qc->data['result'] !== 'pass') {
                    $this->fail('qc_pass_required');
                }
                $r->immutable = true;
            }
        } elseif ($r->type === 'shipment') {
            $this->shipment($r, $state);
        } elseif ($r->type === 'order') {
            $states = ['confirmed', 'production', 'quality', 'shipment', 'delivered'];
            if ($state === 'cancelled') {
                abort_unless($this->access->has($actor, 'commercial'), 403);
            } elseif (array_search($state, $states) !== array_search($r->state, $states) + 1) {
                $this->fail('invalid_stage');
            }
            if ($state === 'production') {
                $this->required($r, ['original_promise']);
            }
            if ($state === 'delivered') {
                $shipments = KozaRecord::where('type', 'shipment')->where('state', 'delivered')->whereHas('links', fn ($q) => $q->where('relation', 'order')->where('target_id', $r->id))->get();
                foreach (DB::table('koza_lines')->where('record_id', $r->id)->get() as $line) {
                    $quantity = D::of('0');
                    foreach ($shipments as $ship) {
                        foreach ($ship->links()->where('relation', 'lot')->get() as $link) {
                            if (KozaRecord::find($link->target_id)?->links()->where('relation', 'variant')->where('target_id', $line->variant_id)->exists() && $link->unit === $line->unit) {
                                $quantity = $quantity->plus($link->quantity ?? '0');
                            }
                        }
                    }
                    if ($quantity->isLessThan($line->quantity)) {
                        $this->fail('delivery_incomplete');
                    }
                }
                $this->required($r, ['delivered_at']);
            }
        } elseif ($r->type === 'service') {
            $this->required($r, ['issue', 'severity', 'impact', 'opened_at', 'next_step', 'communication_on']);
            if ($state === 'resolved') {
                $this->required($r, ['root_cause', 'resolution', 'resolved_at']);
                if (strtotime($r->data['resolved_at']) < strtotime($r->data['opened_at'])) {
                    $this->fail('invalid_event_order');
                }
            }
        } elseif ($r->type === 'experiment') {
            $this->required($r, ['hypothesis', 'cohort', 'starts_on', 'ends_on', 'success_criteria']);
            if ($state === 'running' && ! $this->access->has($actor, 'commercial')) {
                $this->fail('commercial_decision_required');
            }
            if (in_array($state, ['scale', 'adapt', 'stop'])) {
                abort_unless($this->access->has($actor, 'commercial'), 403);
                $this->required($r, ['result', 'learning', 'capacity_impact', 'closure_plan']);
            }
        } elseif ($r->type === 'task') {
            if (($r->data['purpose'] ?? null) === 'marketing' && $this->optedOut($r->company_id) && $state !== 'cancelled') {
                $this->fail('marketing_opt_out');
            }
            if ($state === 'done') {
                $this->required($r, ['result']);
            }
        } elseif ($r->type === 'dependency') {
            $this->required($r, ['service', 'impact', 'backup_owner', 'alternative', 'export_method', 'rpo', 'rto']);
            if ($state === 'active') {
                $this->required($r, ['tested_on', 'result']);
            }
        } elseif ($r->type === 'cost' && $state === 'active') {
            $this->required($r, ['amount', 'currency', 'category', 'incurred_on', 'method', 'external_reference']);
            if (! $r->company_id) {
                $this->fail('required', 'company_id');
            }
        } elseif ($r->type === 'insight' && $state === 'validated') {
            $this->required($r, ['question', 'observation', 'level', 'method', 'sample', 'evidence', 'observed_on', 'confidence']);
        }
    }

    private function optedOut(?int $company): bool
    {
        return $company && KozaRecord::where('company_id', $company)->whereIn('type', ['account', 'contact'])->where('data->opt_out', true)->exists();
    }

    private function shipment(KozaRecord $r, string $state): void
    {
        $this->required($r, ['direction', 'quantity', 'unit', 'carrier', 'tracking', 'communication_on']);
        $orders = $this->linked($r, 'order');
        if ($orders->count() !== 1) {
            $this->fail('one_order_required');
        } $order = $orders->first();
        // Serialize all partial shipments for an order to prevent concurrent over-shipment.
        KozaRecord::where('id', $order->id)->lockForUpdate()->firstOrFail();
        if (($r->data['direction'] ?? null) === 'return') {
            $originals = $this->linked($r, 'return_of');
            if ($state !== 'returned' || $originals->count() !== 1 || $r->state !== 'draft') {
                $this->fail('return_state_required');
            }
            $original = $originals->first();
            if ($original->state !== 'delivered' || ! $original->links()->where('relation', 'order')->where('target_id', $order->id)->exists()) {
                $this->fail('delivered_order_required');
            }
            $this->required($r, ['delivered_at', 'delivery_evidence']);
            $previous = KozaRecord::where('type', 'shipment')->where('state', 'returned')->whereHas('links', fn ($q) => $q->where('relation', 'return_of')->where('target_id', $original->id))->get();
            $sum = D::of($r->data['quantity']);
            foreach ($previous as $returned) {
                $sum = $sum->plus($returned->data['quantity']);
            }
            if ($r->data['unit'] !== ($original->data['unit'] ?? null) || $sum->isGreaterThan($original->data['quantity'])) {
                $this->fail('order_quantity_exceeded');
            }
            $r->immutable = true;

            return;
        }
        $allowed = ['draft' => 'dispatched', 'dispatched' => 'in_transit', 'in_transit' => 'delivered'];
        if (($allowed[$r->state] ?? null) !== $state) {
            $this->fail('invalid_stage');
        }
        if ($state === 'dispatched') {
            $this->required($r, ['shipped_at']);
            $lots = $this->linked($r, 'lot');
            $total = D::of('0');
            foreach ($lots as $lot) {
                if ($lot->state !== 'released' || ! $lot->links()->where('relation', 'order')->where('target_id', $order->id)->exists()) {
                    $this->fail('released_lot_required');
                }
                $qc = KozaRecord::where('type', 'qc')->whereHas('links', fn ($q) => $q->where('relation', 'lot')->where('target_id', $lot->id))->latest('id')->first();
                if (! $qc || $qc->state !== 'released' || ($qc->data['result'] ?? null) !== 'pass') {
                    $this->fail('qc_pass_required');
                }
                $variantIds = $lot->links()->where('relation', 'variant')->pluck('target_id');
                if ($variantIds->count() !== 1 || ! DB::table('koza_lines')->where('record_id', $order->id)->where('variant_id', $variantIds[0])->where('unit', $r->data['unit'])->exists()) {
                    $this->fail('shipment_variant_mismatch');
                }
                $link = $r->links()->where('relation', 'lot')->where('target_id', $lot->id)->firstOrFail();
                if (! $link->quantity || $link->unit !== $r->data['unit'] || $link->unit !== ($lot->data['unit'] ?? null)) {
                    $this->fail('quantity_unit_required');
                }
                $otherLots = KozaRecord::where('type', 'lot')->whereHas('links', fn ($q) => $q->where('relation', 'variant')->where('target_id', $variantIds[0]))->pluck('id');
                $priorVariant = DB::table('koza_links as l')->join('koza_records as s', 's.id', '=', 'l.record_id')->where('s.type', 'shipment')->whereIn('s.state', ['dispatched', 'in_transit', 'delivered'])->where('s.id', '!=', $r->id)->where('l.relation', 'lot')->whereIn('l.target_id', $otherLots)->whereIn('s.id', DB::table('koza_links')->where('relation', 'order')->where('target_id', $order->id)->select('record_id'))->sum('l.quantity');
                $currentVariant = $r->links()->where('relation', 'lot')->whereIn('target_id', $otherLots)->sum('quantity');
                $orderedVariant = DB::table('koza_lines')->where('record_id', $order->id)->where('variant_id', $variantIds[0])->where('unit', $link->unit)->sum('quantity');
                if (D::of((string) $priorVariant)->plus((string) $currentVariant)->isGreaterThan((string) $orderedVariant)) {
                    $this->fail('order_quantity_exceeded');
                }
                $total = $total->plus($link->quantity);
                $prior = DB::table('koza_links as l')->join('koza_records as r', 'r.id', '=', 'l.record_id')->where('l.relation', 'lot')->where('l.target_id', $lot->id)->where('r.type', 'shipment')->whereIn('r.state', ['dispatched', 'in_transit', 'delivered'])->where('r.id', '!=', $r->id)->sum('l.quantity');
                if (D::of((string) $prior)->plus($link->quantity)->isGreaterThan($lot->data['quantity'])) {
                    $this->fail('lot_quantity_exceeded');
                }
            }
            if ($total->compareTo($r->data['quantity']) !== 0) {
                $this->fail('allocation_mismatch');
            }
            $ordered = DB::table('koza_lines')->where('record_id', $order->id)->where('unit', $r->data['unit'])->sum('quantity');
            $shipped = KozaRecord::where('type', 'shipment')->whereIn('state', ['dispatched', 'in_transit', 'delivered'])->whereHas('links', fn ($q) => $q->where('relation', 'order')->where('target_id', $order->id))->where('id', '!=', $r->id)->get();
            $sum = $total;
            foreach ($shipped as $ship) {
                if (($ship->data['unit'] ?? null) === $r->data['unit']) {
                    $sum = $sum->plus($ship->data['quantity']);
                }
            }
            if ($sum->isGreaterThan((string) $ordered)) {
                $this->fail('order_quantity_exceeded');
            }
        }
        if ($state === 'delivered') {
            $this->required($r, ['delivered_at', 'delivery_evidence']);
            if (strtotime($r->data['delivered_at']) < strtotime($r->data['shipped_at'] ?? '')) {
                $this->fail('invalid_event_order');
            }$r->immutable = true;
        }
    }
}
