<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class DocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    private function pdf(string $name = 'secure.pdf', string $text = 'Synthetic document'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% $text\n%%EOF\n");
    }

    private function data(array $extra = []): array
    {
        return array_replace(['name' => 'Private tax certificate', 'category_id' => DocumentCategory::where('name', 'Vergi Levhası')->value('id'), 'description' => 'confidential description', 'document_date' => '2026-09-30', 'visibility' => 'private', 'file' => $this->pdf()], $extra);
    }

    private function createDocument(User $owner, array $extra = []): Document
    {
        $this->actingAs($owner)->post('/documents', $this->data($extra))->assertRedirect();

        return Document::latest('id')->firstOrFail();
    }

    public function test_private_documents_have_no_admin_bypass_and_search_never_leaks(): void
    {
        $owner = $this->user('owner', 'admin');
        $other = $this->user('other', 'admin');
        $doc = $this->createDocument($owner);
        $version = $doc->latestVersion;
        $this->actingAs($other);
        foreach (['', '?q=Private', '?q=confidential', '?q=secure.pdf', '?q=owner', '?q=Vergi', '?q=30.09.2026', '?q=2026-09-30', '?q=2026-99-99'] as $query) {
            $this->get('/documents'.$query)->assertOk()->assertViewHas('documents', fn ($docs) => $docs->total() === 0)->assertDontSee($doc->name);
        }
        foreach (["/documents/$doc->id", "/documents/$doc->id/edit", "/documents/$doc->id/versions/$version->id/download", "/documents/$doc->id/versions/$version->id/preview"] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->put("/documents/$doc->id/access", ['revision' => 1, 'visibility' => 'internal'])->assertNotFound();
        $this->delete("/documents/$doc->id", ['revision' => 1])->assertNotFound();
        $this->actingAs($owner)->get("/documents/$doc->id")->assertOk()->assertSee($doc->name);
        $this->get("/documents/$doc->id/versions/$version->id/preview")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        Storage::disk('documents')->assertExists($version->path);
        $this->assertStringNotContainsString('secure', $version->path);
    }

    public function test_explicit_user_role_and_internal_grants_still_require_capabilities(): void
    {
        $owner = $this->user('owner', 'admin');
        $reader = $this->user('reader');
        $outsider = $this->user('outsider');
        $doc = $this->createDocument($owner, ['allowed_users' => [$reader->id]]);
        $this->actingAs($reader)->get("/documents/$doc->id")->assertOk();
        $this->get('/documents/create')->assertForbidden();
        $this->get("/documents/$doc->id/edit")->assertForbidden();
        $this->post("/documents/$doc->id/versions", ['revision' => 1, 'note' => 'No right', 'file' => $this->pdf()])->assertForbidden();
        $this->actingAs($outsider)->get("/documents/$doc->id")->assertNotFound();
        $this->actingAs($owner)->put("/documents/$doc->id/access", ['revision' => 1, 'visibility' => 'roles', 'allowed_roles' => ['representative']])->assertRedirect();
        $this->actingAs($outsider)->get("/documents/$doc->id")->assertOk();
        config(['documents.permissions.representative' => []]);
        $this->get('/documents')->assertForbidden();
        $this->get("/documents/$doc->id")->assertNotFound();
        config(['documents.permissions.representative' => ['view', 'download']]);
        $this->actingAs($owner)->put("/documents/$doc->id/access", ['revision' => 2, 'visibility' => 'private', 'allowed_users' => [$reader->id]])->assertRedirect();
        $this->actingAs($reader)->get("/documents/$doc->id")->assertNotFound();
        $this->actingAs($owner)->put("/documents/$doc->id/access", ['revision' => 3, 'visibility' => 'internal'])->assertRedirect();
        $this->actingAs($reader)->get("/documents/$doc->id")->assertOk();
        config(['documents.permissions.representative' => ['view']]);
        $this->get("/documents/$doc->id/versions/{$doc->latestVersion->id}/download")->assertForbidden();
    }

    public function test_versions_are_immutable_acl_revocation_covers_history_and_stale_upload_cleans_file(): void
    {
        $owner = $this->user('owner', 'admin');
        $reader = $this->user('reader');
        $doc = $this->createDocument($owner, ['visibility' => 'users', 'allowed_users' => [$reader->id]]);
        $old = $doc->latestVersion;
        $oldBytes = Storage::disk('documents')->get($old->path);
        $this->post("/documents/$doc->id/versions", ['revision' => 1, 'file' => $this->pdf()])->assertSessionHasErrors('note');
        $this->post("/documents/$doc->id/versions", ['revision' => 1, 'note' => 'Renewed certificate', 'file' => $this->pdf('renewed.pdf', 'second version')])->assertRedirect();
        $doc->refresh();
        $this->assertSame(2, $doc->current_version);
        $this->assertSame($oldBytes, Storage::disk('documents')->get($old->path));
        $this->assertCount(2, $doc->versions);
        $this->assertNotSame($old->sha256, $doc->latestVersion->sha256);
        $this->post("/documents/$doc->id/versions", ['revision' => 1, 'note' => 'Stale upload', 'file' => $this->pdf()])->assertStatus(409);
        $this->assertCount(2, Storage::disk('documents')->allFiles());
        $this->actingAs($reader)->get("/documents/$doc->id/versions/$old->id/download")->assertOk();
        $this->actingAs($owner)->put("/documents/$doc->id/access", ['revision' => 2, 'visibility' => 'private'])->assertRedirect();
        $this->actingAs($reader);
        foreach ($doc->versions as $version) {
            $this->get("/documents/$doc->id/versions/$version->id/download")->assertNotFound();
            $this->get("/documents/$doc->id/versions/$version->id/preview")->assertNotFound();
        }
        $this->assertDatabaseHas('document_events', ['document_id' => $doc->id, 'action' => 'version_uploaded', 'version' => 2]);
        $event = DB::table('document_events')->where('action', 'permissions_changed')->first();
        $details = json_decode($event->details, true);
        $this->assertSame([$reader->id], $details['before']['allowed_users']);
        $this->assertSame([], $details['after']['allowed_users']);
    }

    public function test_file_ids_cannot_cross_documents_and_archiving_retains_files_with_guarded_restore(): void
    {
        $owner = $this->user('owner', 'admin');
        $other = $this->user('other', 'admin');
        $a = $this->createDocument($owner);
        $b = $this->createDocument($owner, ['name' => 'Second document']);
        $this->get("/documents/$a->id/versions/{$b->latestVersion->id}/download")->assertNotFound();
        $this->delete("/documents/$a->id", ['revision' => 1])->assertRedirect();
        $this->assertSoftDeleted('documents', ['id' => $a->id]);
        Storage::disk('documents')->assertExists($a->latestVersion->path);
        $this->get("/documents/$a->id/versions/{$a->latestVersion->id}/download")->assertNotFound();
        $this->get('/documents?archived=1')->assertOk()->assertSee($a->name)->assertDontSee($b->name);
        $this->actingAs($other)->post("/documents/$a->id/restore", ['revision' => 2])->assertNotFound();
        $this->actingAs($owner)->post("/documents/$a->id/restore", ['revision' => 1])->assertStatus(409);
        $this->post("/documents/$a->id/restore", ['revision' => 2])->assertRedirect();
        $this->assertNotSoftDeleted('documents', ['id' => $a->id]);
        $this->get("/documents/$a->id/versions/{$a->latestVersion->id}/download")->assertOk();
        foreach (['created', 'file_uploaded', 'archived', 'restored', 'downloaded'] as $action) {
            $this->assertDatabaseHas('document_events', ['document_id' => $a->id, 'action' => $action]);
        }
    }

    public function test_real_mime_and_size_are_checked_and_office_is_download_only(): void
    {
        $owner = $this->user('owner', 'admin');
        $this->actingAs($owner);
        foreach (['forged.pdf' => '<?php echo 1;', 'forged.png' => '<svg></svg>', 'script.php' => '%PDF-1.4 %%EOF', 'bad.docx' => 'not a zip', 'bad.xls' => 'not an office document'] as $name => $body) {
            $this->post('/documents', $this->data(['file' => UploadedFile::fake()->createWithContent($name, $body)]))->assertSessionHasErrors('file');
        }
        config(['documents.max_kb' => 1]);
        $this->post('/documents', $this->data(['file' => $this->pdf('large.pdf', str_repeat('x', 2048))]))->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
        $this->assertCount(0, Storage::disk('documents')->allFiles());
        config(['documents.max_kb' => 10240]);
        $path = tempnam(sys_get_temp_dir(), 'koza-docx-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<Types><Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
            $zip->addFromString('word/document.xml', '<document/>');
            $zip->close();
            $doc = $this->createDocument($owner, ['file' => new UploadedFile($path, 'guide.docx', null, null, true)]);
            $this->get("/documents/$doc->id/versions/{$doc->latestVersion->id}/download")->assertOk();
            $this->get("/documents/$doc->id/versions/{$doc->latestVersion->id}/preview")->assertStatus(415);
            $zip->open($path);
            $zip->addFromString('word/vbaProject.bin', 'macro');
            $zip->close();
            $this->post('/documents', $this->data(['file' => new UploadedFile($path, 'macro.docx', null, null, true)]))->assertSessionHasErrors('file');
        } finally {
            @unlink($path);
        }
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_turkish_ordering_category_tree_filters_and_metadata_whitelist(): void
    {
        $owner = $this->user('owner', 'admin');
        $expected = ['C belgesi', 'Ç belgesi', 'G belgesi', 'Ğ belgesi', 'I belgesi', 'İ belgesi', 'O belgesi', 'Ö belgesi', 'S belgesi', 'Ş belgesi', 'U belgesi', 'Ü belgesi'];
        foreach (array_reverse($expected) as $name) {
            $this->createDocument($owner, ['name' => $name]);
        }
        $this->get('/documents')->assertOk()->assertViewHas('documents', fn ($docs) => $docs->pluck('name')->all() === $expected);
        $parent = DocumentCategory::where('name', 'Mali Belgeler')->firstOrFail();
        $this->get('/documents?category='.$parent->id)->assertViewHas('documents', fn ($docs) => $docs->total() === 12);
        foreach (array_reverse($expected) as $name) {
            $this->post('/document-categories', ['name' => $name, 'parent_id' => $parent->id])->assertRedirect();
        }
        $nodes = collect(DocumentCategory::tree())->first(fn ($n) => $n['category']->id === $parent->id);
        $names = collect($nodes['children'])->pluck('category.name')->filter(fn ($n) => in_array($n, $expected))->values()->all();
        $this->assertSame($expected, $names);
        $doc = Document::firstOrFail();
        $data = $this->data(['name' => 'Revised name', 'revision' => 1, 'owner_id' => 99999, 'current_version' => 999, 'visibility' => 'internal']);
        unset($data['file']);
        $this->put("/documents/$doc->id", $data)->assertRedirect();
        $doc->refresh();
        $this->assertSame($owner->id, $doc->owner_id);
        $this->assertSame('private', $doc->visibility);
        $this->assertSame(1, $doc->current_version);
        $this->assertSame('Revised name', $doc->name);
        $this->put("/documents/$doc->id", $data)->assertStatus(409);
        $this->assertDatabaseHas('document_events', ['document_id' => $doc->id, 'action' => 'metadata_changed']);
        $details = json_decode(DB::table('document_events')->where('document_id', $doc->id)->where('action', 'metadata_changed')->value('details'), true);
        $this->assertNotSame('Revised name', $details['before']['name']);
        $this->assertSame('Revised name', $details['after']['name']);
        $this->get("/documents/$doc->id")->assertOk()->assertSee('Önceki bilgiler');
    }

    public function test_valid_images_preview_and_category_management_is_admin_only(): void
    {
        $owner = $this->user('owner', 'admin');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+cXioAAAAASUVORK5CYII=');
        $doc = $this->createDocument($owner, ['file' => UploadedFile::fake()->createWithContent('sample.png', $png)]);
        $this->get("/documents/$doc->id/versions/{$doc->latestVersion->id}/preview")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->user('reader'))->post('/document-categories', ['name' => 'Forbidden category'])->assertForbidden();
        $this->assertDatabaseMissing('document_categories', ['name' => 'Forbidden category']);
        $this->actingAs($owner)->post('/document-categories', ['name' => 'İnsan Kaynakları'])->assertSessionHasErrors('name');
    }

    public function test_guest_and_inactive_users_cannot_access_documents(): void
    {
        $this->get('/documents')->assertRedirect('/login');
        $this->get('/documents/1/versions/1/download')->assertRedirect('/login');
        $user = $this->user('inactive', 'admin', false);
        $this->actingAs($user)->get('/documents')->assertRedirect('/login');
    }
}
