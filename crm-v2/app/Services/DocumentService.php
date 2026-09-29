<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentService
{
    public function upload(User $actor, array $data, UploadedFile $file, ?Document $document = null): Document
    {
        Gate::forUser($actor)->authorize($document ? 'manageVersions' : 'create', $document ?? Document::class);
        $stored = app(DocumentFiles::class)->store($file);
        try {
            return DB::transaction(function () use ($actor, $data, $stored, $document) {
                if ($document) {
                    $document = $this->locked($actor, $document, 'manageVersions', (int) $data['revision']);
                } else {
                    $document = new Document;
                    $document->fill(collect($data)->only(['name', 'category_id', 'description', 'document_date', 'expires_at', 'visibility', 'allowed_users', 'allowed_roles'])->all());
                    $document->owner_id = $actor->id;
                    $document->current_version = 0;
                    $document->revision = 0;
                    $document->save();
                    $this->event($actor, $document, 'created');
                }
                $document->current_version++;
                $document->revision++;
                $document->save();
                $version = $document->versions()->create($stored + ['number' => $document->current_version, 'uploaded_by' => $actor->id, 'note' => $data['note'] ?? null, 'created_at' => now()]);
                $this->event($actor, $document, $version->number === 1 ? 'file_uploaded' : 'version_uploaded', ['version_id' => $version->id]);

                return $document;
            });
        } catch (Throwable $e) {
            Storage::disk('documents')->delete($stored['path']);
            throw $e;
        }
    }

    public function update(User $actor, Document $document, array $data, bool $permissions = false): void
    {
        DB::transaction(function () use ($actor, $document, $data, $permissions) {
            $document = $this->locked($actor, $document, $permissions ? 'managePermissions' : 'update', (int) $data['revision']);
            $fields = $permissions ? ['visibility', 'allowed_users', 'allowed_roles'] : ['name', 'category_id', 'description', 'document_date', 'expires_at'];
            $before = $document->only($fields);
            $document->fill(collect($data)->only($fields)->all());
            $after = $document->only($fields);
            $document->revision++;
            $document->save();
            // ACL identifiers are auditable; document text stays in the protected document record.
            $details = $permissions ? ['before' => $before, 'after' => $after] : ['changed_fields' => array_keys(array_filter($after, fn ($v, $k) => $before[$k] != $v, ARRAY_FILTER_USE_BOTH))];
            $this->event($actor, $document, $permissions ? 'permissions_changed' : 'metadata_changed', $details);
        });
    }

    public function archive(User $actor, Document $document, int $revision, bool $restore = false): void
    {
        DB::transaction(function () use ($actor, $document, $revision, $restore) {
            $document = $this->locked($actor, $document, $restore ? 'restore' : 'delete', $revision, $restore);
            $document->revision++;
            $document->save();
            $restore ? $document->restore() : $document->delete();
            $this->event($actor, $document, $restore ? 'restored' : 'archived');
        });
    }

    private function locked(User $actor, Document $document, string $ability, int $revision, bool $trashed = false): Document
    {
        $document = Document::query()->when($trashed, fn ($q) => $q->withTrashed())->lockForUpdate()->findOrFail($document->id);
        Gate::forUser($actor)->authorize($ability, $document);
        abort_unless($document->revision === $revision, 409, 'Belge başka bir işlemle değişti. Sayfayı yenileyin.');

        return $document;
    }

    public function event(User $actor, ?Document $document, string $action, array $details = [], ?int $version = null): void
    {
        DB::table('document_events')->insert(['actor_id' => $actor->id, 'document_id' => $document?->id, 'version' => $version ?? $document?->current_version, 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
