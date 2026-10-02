<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only, permission-scoped decision queue. Personal planning remains in My Focus. */
class KozaDashboard
{
    public function __construct(private KozaAccess $access) {}

    public function forUser(User $user): array
    {
        $timezone = DB::table('koza_preferences')->where('user_id', $user->id)->value('timezone') ?: 'Europe/Istanbul';
        $now = now($timezone);
        $today = $now->toDateString();
        $visible = $this->access->visible($user)->where('demo', false)->where('type', '!=', 'capability');
        $summary = fn ($r) => ['id' => $r->id, 'title' => $r->title, 'type' => $r->type, 'state' => $r->state, 'owner' => $r->owner?->name, 'due_at' => $r->due_at?->toDateString()];
        $services = (clone $visible)->where('type', 'service')->where('state', '!=', 'resolved');
        $customer = (clone $services)->with('owner')->orderByRaw('due_at IS NULL')->orderBy('due_at')->first();
        $decision = $this->access->has($user, 'commercial') ? (clone $visible)->whereIn('type', ['quote', 'sample'])->where('state', 'verified')->with('owner')->oldest('updated_at')->first() : null;
        $review = $this->access->money($user) ? (clone $visible)->where('type', 'order')->where('state', '!=', 'cancelled')->whereIn('data->payment_status', ['unpaid', 'partial'])->with('owner')->oldest('updated_at')->first() : null;
        $priorities = [];
        foreach (['customer' => [$customer, 'open_service'], 'decision' => [$decision, 'awaiting_commercial_approval'], 'commercial' => [$review, 'payment_review']] as $kind => [$record, $reason]) {
            $priorities[$kind] = $record ? $summary($record) + ['reason' => $reason] : null;
        }
        // The event timestamp defines the observation period; later edits cannot fabricate weekly outcomes.
        $events = DB::table('koza_events')->whereIn('record_id', (clone $visible)->select('id'))->whereBetween('created_at', [$now->copy()->startOfWeek()->utc(), $now->copy()->utc()]);
        $sets = ['progressed' => [], 'reordered' => [], 'promises' => [], 'resolved' => []];
        foreach ((clone $events)->orderBy('id')->cursor() as $event) {
            $r = json_decode($event->snapshot, true)['record'] ?? [];
            $type = $r['type'] ?? '';
            $state = $r['state'] ?? '';
            if ($type === 'opportunity' && (($event->action === 'transition' && in_array($state, ['commercially_qualified', 'solution_development', 'sample_quote', 'negotiation'])) || $event->action === 'won')) {
                $sets['progressed'][$event->record_id] = true;
            }
            if ($type === 'opportunity' && $event->action === 'won' && ($r['data']['kind'] ?? '') === 'reorder' && ! empty($r['company_id'])) {
                $sets['reordered'][$r['company_id']] = true;
            }
            if ($type === 'task' && $event->action === 'transition' && $state === 'done' && ! empty($r['due_at']) && substr($r['due_at'], 0, 10) >= Carbon::parse($event->created_at, 'UTC')->timezone($timezone)->toDateString()) {
                $sets['promises'][$event->record_id] = true;
            }
            if ($type === 'service' && $event->action === 'transition' && $state === 'resolved') {
                $sets['resolved'][$event->record_id] = true;
            }
        }
        $recent = DB::table('koza_events')->whereIn('record_id', (clone $visible)->select('id'))->orderByDesc('id')->limit(5)->get(['record_id', 'action', 'created_at']);
        $records = (clone $visible)->whereIn('id', $recent->pluck('record_id'))->with('owner')->get()->keyBy('id');
        $upcoming = (clone $visible)->where('type', 'task')->where('owner_id', $user->id)->whereNotIn('state', ['done', 'cancelled'])->whereNotNull('due_at')->with('owner')->orderBy('due_at')->limit(4)->get();
        $reorder = (clone $visible)->where('type', 'order')->where('state', 'delivered')->whereNotNull('data->reorder_on')->where('data->reorder_on', '<=', $today)->whereNotIn('company_id', (clone $services)->whereNotNull('company_id')->select('company_id'))->with('owner')->orderBy('data->reorder_on')->first();

        return [
            'date' => $today, 'week_from' => $now->copy()->startOfWeek()->toDateString(), 'week_to' => $today,
            'priorities' => $priorities, 'weekly' => array_map('count', $sets),
            'has_records' => (clone $visible)->exists(), 'can_review_commercial' => $this->access->money($user),
            'health' => [
                'blockers' => (clone $visible)->where('type', 'task')->where('owner_id', $user->id)->whereNotIn('state', ['done', 'cancelled'])->whereNotNull('data->blocker')->where('data->blocker', '!=', '')->count(),
                'open_cases' => (clone $services)->count(),
                'assumptions_due' => (clone $visible)->where('type', 'assumption')->where('due_at', '<=', $today)->whereNotIn('state', ['closed', 'stopped'])->count(),
            ],
            'upcoming' => $upcoming->map($summary)->values(),
            'recent' => $recent->filter(fn ($e) => $records->has($e->record_id))->map(fn ($e) => ['record' => $summary($records[$e->record_id]), 'action' => $e->action, 'occurred_at' => $e->created_at])->values(),
            'suggestion' => $reorder ? $summary($reorder) + ['reason' => 'reorder_due', 'review_on' => $reorder->data['reorder_on']] : null,
        ];
    }
}
