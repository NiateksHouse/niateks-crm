<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function create(User $u): bool
    {
        return Document::permits($u, 'create');
    }

    public function view(User $u, Document $d): bool
    {
        return Document::withTrashed()->visibleTo($u)->whereKey($d->id)->exists();
    }

    public function update(User $u, Document $d): bool
    {
        return ! $d->trashed() && Document::permits($u, 'update') && $this->view($u, $d);
    }

    public function managePermissions(User $u, Document $d): bool
    {
        return ! $d->trashed() && Document::permits($u, 'manage_permissions') && $this->view($u, $d);
    }

    public function manageVersions(User $u, Document $d): bool
    {
        return ! $d->trashed() && Document::permits($u, 'manage_versions') && $this->view($u, $d);
    }

    public function download(User $u, Document $d): bool
    {
        return ! $d->trashed() && Document::permits($u, 'download') && $this->view($u, $d);
    }

    public function delete(User $u, Document $d): bool
    {
        return ! $d->trashed() && Document::permits($u, 'delete') && $this->view($u, $d);
    }

    public function restore(User $u, Document $d): bool
    {
        return $d->trashed() && Document::permits($u, 'delete') && $this->view($u, $d);
    }
}
