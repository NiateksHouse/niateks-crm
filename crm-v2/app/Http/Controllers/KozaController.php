<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Project;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\DocumentFiles;
use App\Services\DocumentService;
use App\Services\KozaAccess;
use App\Services\KozaEconomics;
use App\Services\KozaWorkflow;
use Brick\Math\BigDecimal as D;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KozaController
{
    public function __construct(private KozaAccess $access, private KozaWorkflow $workflow) {}

    public function home()
    {
        return view('koza.app');
    }

    public function bootstrap(Request $r)
    {
        $u = $r->user();
        $catalog = config('koza.catalog');
        foreach ($catalog as $type => &$c) {
            $c['fields'] = $this->access->fields($u, $type);
            $c['write_fields'] = array_keys($this->access->fields($u, $type, true));
            $c['can_create'] = $this->access->canCreate($u, $type);
        }
        $pref = DB::table('koza_preferences')->where('user_id', $u->id)->first();
        $counts = $this->access->visible($u)->selectRaw('type, count(*) as aggregate')->groupBy('type')->pluck('aggregate', 'type');

        return response()->json(['user' => ['id' => $u->id, 'name' => $u->name, 'domains' => $this->access->domains($u), 'read_cost' => $this->access->money($u, 'cost'), 'read_finance' => $this->access->money($u), 'business_owner' => (bool) $u->can_view_all_finance],
            'locale' => $pref?->locale ?? 'tr-TR', 'timezone' => $pref?->timezone ?? 'Europe/Istanbul', 'catalog' => $catalog, 'counts' => $counts,
            'people' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
            'companies' => Company::orderBy('name')->get(['id', 'name', 'country_code', 'city', 'email', 'phone', 'website', 'tax_number', 'roles', 'version']),
            'projects' => Project::visibleTo($u)->with('company:id,name')->latest('updated_at')->limit(20)->get(['id', 'name', 'company_id', 'updated_at']),
            'contacts' => Contact::visibleTo($u)->with('company:id,name')->orderBy('name')->limit(250)->get(['id', 'name', 'company_id', 'email', 'phone']),
            'policy_configured' => $this->workflow->policy() !== null, 'release' => '2.1.0-test.1']);
    }

    public function index(Request $r)
    {
        $filter = $r->validate(['type' => ['nullable', Rule::in(array_keys(config('koza.catalog')))], 'q' => 'nullable|string|max:100', 'company_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'mine' => 'nullable|boolean', 'state' => 'nullable|string|max:40']);
        $query = $this->access->visible($r->user())->with(['company', 'owner']);
        if (! empty($filter['type'])) {
            $query->where('type', $filter['type']);
        }
        if (! empty($filter['company_id'])) {
            $query->where('company_id', $filter['company_id']);
        }
        if (! empty($filter['q'])) {
            $query->where('title', 'like', '%'.addcslashes($filter['q'], '%_\\').'%');
        }
        if (! empty($filter['mine'])) {
            $query->where('owner_id', $r->user()->id);
        }
        if (! empty($filter['state'])) {
            $query->where('state', $filter['state']);
        }
        $page = $query->latest('updated_at')->orderByDesc('id')->paginate(40);

        return response()->json(['records' => $page->getCollection()->map(fn ($record) => $this->access->serialize($r->user(), $record)), 'page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()]);
    }

    public function show(Request $r, int $record)
    {
        $model = $this->access->visible($r->user())->findOrFail($record);
        $result = $this->access->serialize($r->user(), $model);
        $result['related'] = $model->company_id ? $this->access->visible($r->user())->where('company_id', $model->company_id)->where('id', '!=', $model->id)->where('type', '!=', 'capability')->latest('updated_at')->limit(30)->get()->map(fn ($item) => ['id' => $item->id, 'title' => $item->title, 'type' => $item->type, 'state' => $item->state]) : [];
        $result['history'] = DB::table('koza_events')->join('users', 'users.id', '=', 'koza_events.actor_id')->where('record_id', $record)->orderByDesc('koza_events.id')->limit(100)->get(['koza_events.id', 'action', 'version', 'koza_events.created_at', 'users.name']);
        $result['documents'] = $this->documentRows($r, Document::visibleTo($r->user())->whereIn('id', DB::table('koza_documents')->where('record_id', $record)->select('document_id'))->get());
        if (in_array($model->type, ['quote', 'order']) && $this->access->money($r->user())) {
            $result['economics'] = $model->type === 'order' ? ($model->data['approved_economics'] ?? null) : app(KozaEconomics::class)->quote($model);
        }
        $result['approvals'] = DB::table('koza_approvals')->where('record_id', $record)->get(['kind', 'version', 'created_at', 'expires_at'])->map(fn ($a) => (array) $a + ['current' => $this->workflow->approved($model, $a->kind)]);

        return response()->json($result);
    }

    public function store(Request $r)
    {
        $record = $this->workflow->save($r->user(), $r->all());

        return response()->json($this->access->serialize($r->user(), $record), 201);
    }

    public function update(Request $r, int $record)
    {
        $model = $this->workflow->save($r->user(), $r->all(), $record);

        return response()->json($this->access->serialize($r->user(), $model));
    }

    public function action(Request $r, int $record)
    {
        $model = $this->workflow->action($r->user(), $record, $r->all());

        return response()->json($this->access->serialize($r->user(), $model));
    }

    public function history(Request $r, int $record, int $event)
    {
        $model = $this->access->visible($r->user())->findOrFail($record);
        $event = DB::table('koza_events')->where('record_id', $record)->where('id', $event)->first();
        abort_unless($event, 404);
        $snapshot = json_decode($event->snapshot, true);
        $data = $snapshot['record']['data'] ?? [];
        $snapshot['record']['data'] = array_intersect_key($data, $this->access->fields($r->user(), $model->type));
        unset($snapshot['record']['dedup_key']);
        if (! $this->access->money($r->user(), 'cost')) {
            foreach ($snapshot['lines'] as &$line) {
                unset($line['unit_cost']);
            }
        }
        $ids = $this->access->visible($r->user())->whereIn('id', array_column($snapshot['links'] ?? [], 'target_id'))->pluck('id')->all();
        $snapshot['links'] = array_values(array_filter($snapshot['links'] ?? [], fn ($link) => in_array($link['target_id'], $ids, true)));

        return response()->json($snapshot);
    }

    public function preferences(Request $r)
    {
        $values = $r->validate(['locale' => ['required', Rule::in(['tr-TR', 'en-GB', 'en-US'])], 'timezone' => ['required', Rule::in(['Europe/Istanbul', 'Europe/London', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles'])]]);
        DB::table('koza_preferences')->updateOrInsert(['user_id' => $r->user()->id], $values + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json($values);
    }

    public function company(CompanyRequest $r, ?Company $company = null)
    {
        $company = app(CompanyService::class)->save($r->user(), $r->validated(), $company);

        return response()->json($company->only(['id', 'name', 'country_code', 'city', 'email', 'phone', 'website', 'tax_number', 'roles', 'version']));
    }

    public function settings(Request $r)
    {
        abort_unless($r->user()->can_view_all_finance, 403);
        $grants = DB::table('koza_access')->get()->map(fn ($row) => (array) $row + [])->map(function ($row) {
            $row['domains'] = json_decode($row['domains'], true);

            return $row;
        });

        return response()->json(['policy' => $this->workflow->policy(), 'users' => User::where('active', true)->get(['id', 'name', 'username', 'can_view_all_finance']), 'grants' => $grants]);
    }

    public function policy(Request $r)
    {
        abort_unless($r->user()->can_view_all_finance, 403);
        $values = $r->validate(['version' => 'required|integer|min:0', 'minimum_contribution' => 'required|numeric|between:0,100',
            'sample_budget' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'], 'sample_currency' => ['required', Rule::in(['GBP', 'USD', 'TRY', 'EUR'])],
            'max_payment_days' => 'required|integer|between:0,365', 'cost_hours' => 'required|integer|between:1,8760', 'stock_hours' => 'required|integer|between:1,8760',
            'senders' => 'required|array|min:1|max:50', 'senders.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('active', true)], 'reason' => 'required|string|max:3000']);
        DB::transaction(function () use ($r, $values) {
            $old = DB::table('koza_controls')->where('key', 'commercial')->lockForUpdate()->first();
            abort_unless(($old?->version ?? 0) === (int) $values['version'], 409, 'version_conflict');
            $reason = $values['reason'];
            unset($values['reason']);
            $version = $values['version'] + 1;
            unset($values['version']);
            $values['senders'] = array_map('intval', $values['senders']);
            DB::table('koza_controls')->updateOrInsert(['key' => 'commercial'], ['value' => json_encode($values, JSON_THROW_ON_ERROR), 'approved_by' => $r->user()->id, 'version' => $version, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
            $this->workflow->event($r->user(), null, 'policy_changed', $reason, ['version' => $version, 'value' => $values]);
        });

        return response()->json(['saved' => true]);
    }

    public function grant(Request $r)
    {
        abort_unless($r->user()->can_view_all_finance, 403);
        $values = $r->validate(['user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('active', true)], 'domains' => 'present|array|max:4', 'domains.*' => ['string', 'distinct', Rule::in(['market', 'operations', 'commercial', 'system'])], 'read_cost' => 'required|boolean', 'read_finance' => 'required|boolean', 'reason' => 'required|string|max:3000']);
        DB::transaction(function () use ($r, $values) {
            User::where('id', $values['user_id'])->lockForUpdate()->firstOrFail();
            DB::table('koza_access')->updateOrInsert(['user_id' => $values['user_id']], ['domains' => json_encode($values['domains']), 'read_cost' => $values['read_cost'], 'read_finance' => $values['read_finance'], 'granted_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->workflow->event($r->user(), null, 'access_changed', $values['reason'], $values);
        });

        return response()->json(['saved' => true]);
    }

    private function documentRows(Request $r, $documents): array
    {
        return $documents->load(['category', 'latestVersion'])->map(function ($d) use ($r) {
            return ['id' => $d->id, 'name' => $d->name, 'description' => $d->description, 'category' => $d->category?->name, 'category_id' => $d->category_id, 'visibility' => $d->visibility, 'revision' => $d->revision, 'version' => $d->current_version, 'date' => $d->document_date?->format('Y-m-d'), 'expires_at' => $d->expires_at?->format('Y-m-d'),
                'can_edit' => $r->user()->can('update', $d), 'can_version' => $r->user()->can('manageVersions', $d), 'download' => $r->user()->can('download', $d) && $d->latestVersion ? route('documents.download', [$d->id, $d->latestVersion->id]) : null];
        })->all();
    }

    public function documents(Request $r)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $query = Document::visibleTo($r->user());
        if (! empty($filters['q'])) {
            $query->where('name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%');
        }
        $page = $query->orderBy('name')->paginate(40);

        return response()->json(['records' => $this->documentRows($r, $page->getCollection()), 'page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total(),
            'categories' => DocumentCategory::orderBy('name')->get(['id', 'name', 'parent_id']), 'can_upload' => $r->user()->can('create', Document::class) && Document::permits($r->user(), 'manage_permissions')]);
    }

    public function upload(Request $r, ?int $document = null)
    {
        $model = $document ? Document::visibleTo($r->user())->findOrFail($document) : null;
        Gate::authorize($model ? 'manageVersions' : 'create', $model ?? Document::class);
        $rules = ['file' => ['required', 'file', 'max:'.DocumentFiles::maxKb()], 'note' => 'required|string|max:2000', 'record_id' => 'nullable|integer'];
        $rules += $model ? ['revision' => 'required|integer|min:1'] : ['name' => 'required|string|max:180', 'description' => 'nullable|string|max:5000', 'category_id' => 'required|integer|exists:document_categories,id', 'document_date' => 'required|date_format:Y-m-d', 'expires_at' => 'nullable|date_format:Y-m-d|after_or_equal:document_date', 'visibility' => ['required', Rule::in(['private', 'internal'])]];
        $values = $r->validate($rules);
        if (! $model) {
            abort_unless(Document::permits($r->user(), 'manage_permissions'), 403);
            $values += ['allowed_users' => [], 'allowed_roles' => []];
        }
        $record = empty($values['record_id']) ? null : $this->access->visible($r->user())->findOrFail($values['record_id']);
        if ($record) {
            abort_unless($this->access->editable($r->user(), $record), 403);
        }
        $doc = app(DocumentService::class)->upload($r->user(), $values, $r->file('file'), $model);
        if ($record) {
            DB::table('koza_documents')->insertOrIgnore(['record_id' => $record->id, 'document_id' => $doc->id]);
        }

        return response()->json(['document' => $this->documentRows($r, collect([$doc]))[0]], 201);
    }

    public function trace(Request $r, int $record)
    {
        $root = $this->access->visible($r->user())->findOrFail($record);
        $seen = [$root->id];
        $frontier = [$root->id];
        $edges = [];
        for ($depth = 0; $depth < 8 && $frontier; $depth++) {
            $links = DB::table('koza_links')->where(fn ($q) => $q->whereIn('record_id', $frontier)->orWhereIn('target_id', $frontier))->limit(1000)->get();
            $ids = $links->pluck('record_id')->merge($links->pluck('target_id'))->unique()->all();
            $visible = $this->access->visible($r->user())->whereIn('id', $ids)->pluck('id')->all();
            $next = [];
            foreach ($links as $link) {
                if (in_array($link->record_id, $visible, true) && in_array($link->target_id, $visible, true)) {
                    $edges[$link->id] = (array) $link;
                    foreach ([$link->record_id, $link->target_id] as $id) {
                        if (! in_array($id, $seen, true)) {
                            $next[] = $id;
                            $seen[] = $id;
                        }
                    }
                }
            }
            $frontier = array_unique($next);
            if (count($seen) > 500) {
                break;
            }
        }

        return response()->json(['nodes' => $this->access->visible($r->user())->whereIn('id', $seen)->get()->map(fn ($v) => $this->access->serialize($r->user(), $v)), 'edges' => array_values($edges), 'truncated' => ! empty($frontier)]);
    }

    public function ask(Request $r)
    {
        $data = $r->validate(['question' => 'required|string|max:500']);
        $words = preg_split('/\s+/u', trim($data['question']));
        $query = $this->access->visible($r->user());
        $query->where(function ($q) use ($words) {
            foreach (array_slice($words, 0, 12) as $word) {
                if (mb_strlen($word) >= 2) {
                    $q->orWhere('title', 'like', '%'.addcslashes($word, '%_\\').'%');
                }
            }
        });

        return response()->json(['mode' => 'source_search', 'model_connected' => false, 'records' => $query->latest('updated_at')->limit(20)->get()->map(fn ($v) => $this->access->serialize($r->user(), $v)), 'generated_at' => now()->toIso8601String()]);
    }

    public function analytics(Request $r)
    {
        abort_unless($this->access->money($r->user()), 403);
        $filters = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'company_id' => 'nullable|integer']);
        $groups = [];
        $orders = $this->access->visible($r->user())->where('type', 'order')->whereBetween('created_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))->get();
        foreach ($orders as $order) {
            $calc = $order->data['approved_economics'] ?? null;
            if (! $calc || ! $calc['complete']) {
                continue;
            }
            $key = $order->company_id.':'.$calc['currency'];
            $groups[$key] ??= ['company' => $order->company?->name, 'currency' => $calc['currency'], 'sales' => D::of('0'), 'contribution' => D::of('0'), 'service' => D::of('0'), 'financing' => D::of('0'), 'acquisition' => D::of('0'), 'orders' => 0];
            $groups[$key]['sales'] = $groups[$key]['sales']->plus($calc['net_sales']);
            $groups[$key]['contribution'] = $groups[$key]['contribution']->plus($calc['contribution']);
            $groups[$key]['orders']++;
        }
        $costs = $this->access->visible($r->user())->where('type', 'cost')->where('state', 'active')->whereBetween('data->incurred_on', [$filters['from'], $filters['to']])->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))->get();
        foreach ($costs as $cost) {
            $d = $cost->data;
            if (($d['included_in_order'] ?? false) || ! isset($d['currency'],$d['amount'],$d['category'])) {
                continue;
            }$key = $cost->company_id.':'.$d['currency'];
            $groups[$key] ??= ['company' => $cost->company?->name, 'currency' => $d['currency'], 'sales' => D::of('0'), 'contribution' => D::of('0'), 'service' => D::of('0'), 'financing' => D::of('0'), 'acquisition' => D::of('0'), 'orders' => 0];
            $groups[$key][$d['category']] = $groups[$key][$d['category']]->plus($d['amount']);
        }
        foreach ($groups as &$g) {
            $g['relationship'] = $g['contribution']->minus($g['service'])->minus($g['financing']);
            foreach (['sales', 'contribution', 'service', 'financing', 'acquisition', 'relationship'] as $k) {
                $g[$k] = (string) $g[$k];
            }
        }

        return response()->json(['basis' => 'approved_estimates', 'period' => $filters, 'rows' => array_values($groups), 'actual_finance_connected' => false]);
    }

    public function export(Request $r)
    {
        $type = $r->validate(['type' => ['nullable', Rule::in(array_keys(config('koza.catalog')))]])['type'] ?? null;
        $user = $r->user();

        return response()->streamDownload(function () use ($user, $type) {
            echo '{"format":"koza-authorized-export-v1","records":[';
            $comma = false;
            foreach ($this->access->visible($user)->when($type, fn ($q) => $q->where('type', $type))->orderBy('id')->cursor() as $record) {
                if ($comma) {
                    echo ',';
                }echo json_encode($this->access->serialize($user, $record), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $comma = true;
            }
            echo ']}';
        }, 'koza-export-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']);
    }
}
