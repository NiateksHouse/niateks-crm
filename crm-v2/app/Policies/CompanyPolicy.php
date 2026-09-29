<?php
namespace App\Policies;
use App\Models\{User, Company};
class CompanyPolicy
{
    public function viewAny(User $user): bool { return $user->active; }
    public function view(User $user, Company $company): bool { return $user->active; }
    public function create(User $user): bool { return $user->active; }
    public function update(User $user, Company $company): bool { return $user->active; }
    public function delete(User $user, Company $company): bool { return $user->isAdmin(); }
}
