<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\KozaRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KozaAccess
{
    public function domains(User $user): array
    {
        $row = DB::table('koza_access')->where('user_id', $user->id)->first();
        $domains = $row ? json_decode($row->domains, true) : [];
        if ($user->can_view_all_finance) {
            $domains[] = 'commercial';
        }

        return array_values(array_unique($domains));
    }

    public function has(User $user, string $domain): bool
    {
        return $user->active && in_array($domain, $this->domains($user), true);
    }

    public function money(User $user, string $kind = 'finance'): bool
    {
        if ($this->has($user, 'commercial')) {
            return true;
        }
        $row = DB::table('koza_access')->where('user_id', $user->id)->first();

        return $user->active && $row && (bool) ($kind === 'cost' ? $row->read_cost : $row->read_finance);
    }

    public function visible(User $user): Builder
    {
        $shared = ['account', 'product', 'variant', 'sku', 'supplier', 'manufacturer', 'material', 'certification', 'right'];
        $domains = $this->domains($user);
        $cross = ['market' => ['quote', 'offering', 'sample', 'order', 'lot', 'qc', 'shipment'], 'operations' => ['quote', 'offering', 'opportunity', 'service', 'insight', 'experiment'], 'commercial' => ['opportunity', 'sample', 'order', 'service', 'experiment', 'insight', 'shipment'], 'system' => []];
        $types = collect(config('koza.catalog'))->filter(fn ($c, $type) => $type !== 'capability' && in_array($c['domain'], $domains, true))->keys()->all();
        foreach ($domains as $domain) {
            $types = array_merge($types, $cross[$domain] ?? []);
        }
        // Domain ownership does not expose private learning plans or other legacy work.
        $q = KozaRecord::query()->where(fn ($q) => $q->whereNull('company_id')->orWhereHas('company'))->where(fn ($q) => $q->whereNull('contact_id')->orWhereIn('contact_id', Contact::visibleTo($user)->select('id')));
        if (! $user->active) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where(function ($q) use ($user, $shared, $types) {
            $q->whereIn('type', $shared)->orWhere('owner_id', $user->id)->orWhereIn('type', $types)
                ->orWhereIn('id', DB::table('koza_members')->select('record_id')->where('user_id', $user->id));
        });
    }

    public function canCreate(User $user, string $type): bool
    {
        if (! $user->active || $type === 'order') {
            return false;
        }
        if (in_array($type, ['task', 'capability'])) {
            return true;
        }
        if ($this->has($user, config('koza.catalog.'.$type.'.domain', 'none'))) {
            return true;
        }

        return $this->has($user, 'market') && in_array($type, ['sample', 'quote', 'offering']);
    }

    public function editable(User $user, KozaRecord $record): bool
    {
        if ($record->type === 'capability') {
            return $record->owner_id === $user->id;
        }
        if ($record->type === 'task' && $record->owner_id === $user->id) {
            return true;
        }

        return $this->has($user, config('koza.catalog.'.$record->type.'.domain'))
            || ($this->has($user, 'market') && in_array($record->type, ['sample', 'quote', 'offering']))
            || ($this->has($user, 'commercial') && in_array($record->type, ['order', 'experiment', 'opportunity']))
            || ($this->has($user, 'operations') && in_array($record->type, ['quote', 'service']));
    }

    public function fields(User $user, string $type, bool $write = false): array
    {
        $fields = array_filter(config('koza.catalog.'.$type.'.fields', []), fn ($f) => ! isset($f['sensitive']) || $this->money($user, $f['sensitive']));
        if (! $write) {
            return $fields;
        }
        // Lifecycle evidence is recorded only by the corresponding gated action.
        $actionFields = match ($type) {
            'quote' => ['dispatch_evidence', 'acceptance'],
            'sample' => ['dispatch_evidence', 'delivered_at', 'feedback'],
            default => [],
        };
        $fields = array_diff_key($fields, array_flip($actionFields));
        if ($this->has($user, config('koza.catalog.'.$type.'.domain'))) {
            return $fields;
        }
        $allowed = match ($type) {
            'quote' => $this->has($user, 'operations') ? ['cost_version', 'cost_checked_at', 'stock_checked_at', 'packaging_cost', 'sample_cost', 'freight_cost', 'commission_cost', 'reserve_cost', 'other_cost', 'technical_evidence'] : ['currency', 'packaging', 'moq', 'incoterm', 'delivery_place', 'lead_time', 'payment_days', 'valid_until', 'customer_reference'],
            'sample' => ['purpose', 'decision_owner', 'address', 'quantity', 'price_context', 'feedback_on', 'success_criteria', 'next_step'],
            'service' => ['root_cause', 'resolution', 'cost'],
            default => array_keys($fields),
        };

        return array_intersect_key($fields, array_flip($allowed));
    }

    public function serialize(User $user, KozaRecord $record): array
    {
        $data = array_intersect_key($record->data ?? [], $this->fields($user, $record->type));
        if ($record->type === 'contact' && $record->contact) {
            $data['email'] = $record->contact->email;
            $data['phone'] = $record->contact->phone;
        }
        $links = $record->links()->get();
        $visible = $this->visible($user)->whereIn('id', $links->pluck('target_id'))->get()->keyBy('id');
        $lines = DB::table('koza_lines')->where('record_id', $record->id)->orderBy('position')->get()->map(function ($line) use ($user) {
            $row = (array) $line;
            $row['specification'] = json_decode($line->specification, true);
            if (! $this->money($user, 'cost')) {
                unset($row['unit_cost']);
            }

            return $row;
        })->all();

        return [
            'id' => $record->id, 'type' => $record->type, 'title' => $record->type === 'account' ? ($record->company?->name ?? $record->title) : ($record->type === 'contact' ? ($record->contact?->name ?? $record->title) : $record->title),
            'contact_id' => $record->contact_id, 'company_id' => $record->company_id, 'company' => $record->company?->name, 'owner_id' => $record->owner_id,
            'owner' => $record->owner?->name, 'state' => $record->state, 'version' => $record->version, 'edition' => $record->edition,
            'parent_id' => $record->parent_id, 'data' => $data, 'source' => $record->source, 'due_at' => $record->due_at?->format('Y-m-d'),
            'verified_at' => $record->verified_at?->toIso8601String(), 'updated_at' => $record->updated_at->toIso8601String(),
            'immutable' => $record->immutable, 'demo' => $record->demo, 'editable' => $this->editable($user, $record),
            'write_fields' => array_keys($this->fields($user, $record->type, true)), 'lines' => $lines,
            'links' => $links->filter(fn ($l) => $visible->has($l->target_id))->map(fn ($l) => ['relation' => $l->relation, 'target_id' => $l->target_id, 'title' => $visible[$l->target_id]->title, 'type' => $visible[$l->target_id]->type, 'quantity' => $l->quantity, 'unit' => $l->unit])->values()->all(),
        ];
    }
}
