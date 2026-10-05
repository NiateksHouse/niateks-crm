<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentCategoryController
{
    public function store(Request $r, DocumentService $service)
    {
        abort_unless(Document::permits($r->user(), 'manage_categories'), 403);
        $data = $r->validate(['name' => ['required', 'string', 'max:120'], 'parent_id' => ['nullable', 'integer', 'exists:document_categories,id']]);
        DB::transaction(function () use ($r, $data, $service) {
            DocumentCategory::orderBy('id')->lockForUpdate()->firstOrFail();
            if (DocumentCategory::where('parent_id', $data['parent_id'] ?? null)->where('name', $data['name'])->exists()) {
                throw ValidationException::withMessages(['name' => 'Bu başlık altında aynı kategori zaten var.']);
            }
            $parent = $data['parent_id'] ?? null;
            for ($depth = 0; $parent; $depth++) {
                if ($depth >= 7) {
                    throw ValidationException::withMessages(['parent_id' => 'Kategori ağacı en fazla 8 seviye olabilir.']);
                }
                $parent = DocumentCategory::findOrFail($parent)->parent_id;
            }
            $category = DocumentCategory::create($data);
            $service->event($r->user(), null, 'category_created', ['category_id' => $category->id, 'parent_id' => $category->parent_id]);
        });

        return redirect()->route('documents.index')->with('status', 'Kategori eklendi.');
    }
}
