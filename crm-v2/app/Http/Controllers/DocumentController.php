<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Services\DocumentFiles;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DocumentController
{
    public function index(Request $request)
    {
        abort_unless(Document::permits($request->user(), 'view'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'integer', 'exists:document_categories,id'], 'archived' => ['nullable', 'in:1']]);
        $query = Document::visibleTo($request->user())->with(['category', 'latestVersion.uploader']);
        if ($request->boolean('archived')) {
            $query->onlyTrashed();
        }
        if (! empty($filters['category'])) {
            $ids = [(int) $filters['category']];
            for ($depth = 0; $depth < 8; $depth++) {
                $children = DocumentCategory::whereIn('parent_id', $ids)->pluck('id')->all();
                $next = array_values(array_unique(array_merge($ids, $children)));
                if ($next === $ids) {
                    break;
                }
                $ids = $next;
            }
            $query->whereIn('category_id', $ids);
        }
        if ($q = trim($filters['q'] ?? '')) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($query) use ($like, $q) {
                $query->where('name', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('latestVersion', function ($v) use ($like) {
                        $v->where('original_name', 'like', $like)->orWhereHas('uploader', fn ($u) => $u->where('name', 'like', $like));
                    });
                $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $q);
                if ($date && $date->format('d.m.Y') === $q) {
                    $query->orWhereDate('document_date', $date->format('Y-m-d'));
                } else {
                    $iso = \DateTimeImmutable::createFromFormat('!Y-m-d', $q);
                    if ($iso && $iso->format('Y-m-d') === $q) {
                        $query->orWhereDate('document_date', $q);
                    }
                }
            });
        }

        return view('documents.index', ['documents' => $query->orderBy('name')->orderBy('id')->paginate(25)->withQueryString(), 'tree' => DocumentCategory::tree(), 'q' => $filters['q'] ?? '']);
    }

    private function choices(): array
    {
        return ['categories' => DocumentCategory::orderBy('name')->get(), 'tree' => DocumentCategory::tree(), 'users' => User::where('active', true)->orderBy('name')->get(['id', 'name']), 'roles' => config('documents.roles'), 'maxKb' => DocumentFiles::maxKb()];
    }

    private function metadata(Request $r): array
    {
        return $r->validate(['name' => ['required', 'string', 'max:180'], 'category_id' => ['required', 'integer', 'exists:document_categories,id'], 'description' => ['nullable', 'string', 'max:5000'], 'document_date' => ['required', 'date_format:Y-m-d'], 'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:document_date']]);
    }

    private function permissions(Request $r): array
    {
        $data = $r->validate(['visibility' => ['required', Rule::in(array_keys(Document::VISIBILITIES))], 'allowed_users' => ['nullable', 'array', 'max:100'], 'allowed_users.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('active', true)], 'allowed_roles' => ['nullable', 'array', 'max:20'], 'allowed_roles.*' => ['string', 'distinct', Rule::in(array_keys(config('documents.roles')))]]);
        $data['allowed_users'] = in_array($data['visibility'], ['private', 'users'], true) ? array_map('intval', $data['allowed_users'] ?? []) : [];
        $data['allowed_roles'] = in_array($data['visibility'], ['private', 'roles'], true) ? array_values($data['allowed_roles'] ?? []) : [];
        if (($data['visibility'] === 'users' && ! $data['allowed_users']) || ($data['visibility'] === 'roles' && ! $data['allowed_roles'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['visibility' => 'Seçili erişim için en az bir kullanıcı veya rol seçin.']);
        }

        return $data;
    }

    private function uploadData(Request $r, bool $new = false): array
    {
        return $r->validate(['file' => ['required', 'file', 'max:'.DocumentFiles::maxKb()], 'note' => [$new ? 'nullable' : 'required', 'string', 'max:2000'], 'revision' => [$new ? 'nullable' : 'required', 'integer', 'min:1']]);
    }

    private function document(Request $request, int $id, bool $trashed = false): Document
    {
        return Document::query()->when($trashed, fn ($q) => $q->withTrashed())->visibleTo($request->user())->findOrFail($id);
    }

    public function create()
    {
        Gate::authorize('create', Document::class);

        return view('documents.form', $this->choices() + ['document' => null]);
    }

    public function store(Request $r, DocumentService $service)
    {
        Gate::authorize('create', Document::class);
        abort_unless(Document::permits($r->user(), 'manage_permissions'), 403);
        $data = $this->metadata($r) + $this->permissions($r) + $this->uploadData($r, true);
        $document = $service->upload($r->user(), $data, $r->file('file'));

        return redirect()->route('documents.show', $document)->with('status', 'Doküman güvenli alana kaydedildi.');
    }

    public function show(Request $r, int $document, DocumentService $service)
    {
        $document = $this->document($r, $document, true);
        $document->load(['category', 'owner', 'latestVersion.uploader']);
        $service->event($r->user(), $document, 'viewed');

        return view('documents.show', $this->choices() + ['document' => $document, 'history' => $document->versions()->with('uploader')->where('number', '<', $document->current_version)->orderByDesc('number')->paginate(10), 'events' => DB::table('document_events')->join('users', 'users.id', '=', 'document_events.actor_id')->where('document_id', $document->id)->select('document_events.action', 'document_events.version', 'document_events.created_at', 'users.name')->latest('document_events.id')->limit(30)->get()]);
    }

    public function edit(Request $r, int $document)
    {
        $document = $this->document($r, $document);
        Gate::authorize('update', $document);

        return view('documents.form', $this->choices() + compact('document'));
    }

    public function update(Request $r, int $document, DocumentService $service)
    {
        $document = $this->document($r, $document);
        Gate::authorize('update', $document);
        $service->update($r->user(), $document, $this->metadata($r) + $r->validate(['revision' => ['required', 'integer', 'min:1']]));

        return redirect()->route('documents.show', $document)->with('status', 'Belge bilgileri güncellendi.');
    }

    public function access(Request $r, int $document, DocumentService $service)
    {
        $document = $this->document($r, $document);
        Gate::authorize('managePermissions', $document);
        $service->update($r->user(), $document, $this->permissions($r) + $r->validate(['revision' => ['required', 'integer', 'min:1']]), true);

        return redirect()->route('documents.index')->with('status', 'Erişim izinleri güncellendi; tüm sürümlere uygulanır.');
    }

    public function version(Request $r, int $document, DocumentService $service)
    {
        $document = $this->document($r, $document);
        Gate::authorize('manageVersions', $document);
        $service->upload($r->user(), $this->uploadData($r), $r->file('file'), $document);

        return redirect()->route('documents.show', $document)->with('status', 'Yeni sürüm eklendi; eski dosya korundu.');
    }

    public function file(Request $r, int $document, int $version, DocumentService $service)
    {
        $document = $this->document($r, $document);
        Gate::authorize('download', $document);
        $version = $document->versions()->findOrFail($version);
        $preview = $r->routeIs('documents.preview');
        abort_if($preview && ! in_array($version->extension, ['pdf', 'jpg', 'jpeg', 'png'], true), 415, 'Bu dosya türünü güvenli indirme ile açın.');
        $disk = Storage::disk('documents');
        abort_unless($disk->exists($version->path), 404);
        $service->event($r->user(), $document, $preview ? 'previewed' : 'downloaded', ['version_id' => $version->id], $version->number);
        $headers = ['Content-Type' => $version->mime, 'Content-Disposition' => HeaderUtils::makeDisposition($preview ? 'inline' : 'attachment', $version->original_name, 'document.'.$version->extension), 'Cache-Control' => 'no-store, private'];

        return response()->file($disk->path($version->path), $headers);
    }

    public function destroy(Request $r, int $document, DocumentService $service)
    {
        $document = $this->document($r, $document);
        $data = $r->validate(['revision' => ['required', 'integer', 'min:1']]);
        $service->archive($r->user(), $document, (int) $data['revision']);

        return redirect()->route('documents.index')->with('status', 'Belge arşivlendi. Dosyalar ve geçmiş korundu.');
    }

    public function restore(Request $r, int $document, DocumentService $service)
    {
        $document = $this->document($r, $document, true);
        $data = $r->validate(['revision' => ['required', 'integer', 'min:1']]);
        $service->archive($r->user(), $document, (int) $data['revision'], true);

        return redirect()->route('documents.show', $document)->with('status', 'Belge geri getirildi.');
    }
}
