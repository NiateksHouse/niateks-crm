<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    public function save(User $actor, array $data, ?Company $company = null): Company
    {
        try {
            return DB::transaction(function () use ($actor, $data, $company) {
                if ($company) {
                    $company = Company::query()->lockForUpdate()->findOrFail($company->id);
                    Gate::forUser($actor)->authorize('update', $company);
                    abort_unless($company->version === (int) $data['version'], 409, 'Bu kart başka bir kullanıcı tarafından güncellendi. Sayfayı yenileyin.');
                } else {
                    Gate::forUser($actor)->authorize('create', Company::class);
                    $company = new Company;
                    $company->created_by = $actor->id;
                    $company->version = 0;
                }
                $before = $company->exists ? $this->snapshot($company) : [];
                $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($data['name'])));
                $identity = hash('sha256', $data['country_code'].'|'.$normalized);
                $duplicate = Company::withTrashed()->where(function ($q) use ($identity, $data) {
                    $q->where('identity_key', $identity);
                    if (! empty($data['email'])) {
                        $q->orWhere('email', $data['email']);
                    }
                    if (! empty($data['phone'])) {
                        $q->orWhere('phone', $data['phone']);
                    }
                })->when($company->exists, fn ($q) => $q->where('id', '!=', $company->id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['name' => 'Benzer bir firma kaydı var. Yeni kart açmadan mevcut firmayı kontrol edin.']);
                }
                $company->fill(collect($data)->only(['name', 'country_code', 'city', 'email', 'phone'])->all());
                $company->roles = array_values($data['roles']);
                $company->identity_key = $identity;
                $company->version++;
                $company->save();
                $company->supplyCategories()->sync($data['supply_category_ids'] ?? []);
                $snapshot = $company->only(['name', 'country_code', 'city', 'email', 'phone', 'roles']);
                $snapshot['supply_categories'] = $company->supplyCategories()->orderBy('name')->get(['supply_categories.id', 'name'])->toArray();
                $company->revisions()->create(['version' => $company->version, 'actor_id' => $actor->id, 'snapshot' => $snapshot, 'created_at' => now()]);
                $this->audit($actor, $company, $before ? 'revision_added' : 'created', $before);

                return $company;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['name' => 'Bu firma zaten kayıtlı. Mevcut kartı kontrol edin.']);
        }
    }

    public function archive(User $actor, Company $company, int $version): void
    {
        DB::transaction(function () use ($actor, $company, $version) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            Gate::forUser($actor)->authorize('delete', $company);
            abort_unless($company->version === $version, 409);
            $company->delete();
            $this->audit($actor, $company, 'archived', []);
        });
    }

    private function snapshot(Company $company): array
    {
        $snapshot = $company->only(['name', 'country_code', 'city', 'email', 'phone', 'roles']);
        $snapshot['supply_categories'] = $company->supplyCategories()->orderBy('name')->get(['supply_categories.id', 'name'])->toArray();

        return $snapshot;
    }

    private function audit(User $actor, Company $company, string $action, array $before): void
    {
        // Contact values stay in business records; audit records contain only changed field names.
        $changed = [];
        foreach ($this->snapshot($company) as $field => $value) {
            if (! array_key_exists($field, $before) || $before[$field] !== $value) {
                $changed[] = $field;
            }
        }
        DB::table('audit_events')->insert(['actor_id' => $actor->id, 'entity_type' => 'company', 'entity_id' => $company->id,
            'action' => $action, 'changed_fields' => json_encode($changed), 'created_at' => now()]);
    }
}
