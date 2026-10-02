<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DocumentFiles
{
    public static function maxKb(): int
    {
        $limits = [(int) config('documents.max_kb', 10240)];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $value = trim((string) ini_get($key));
            $bytes = (float) $value * match (strtolower(substr($value, -1))) {
                'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1,
            };
            if ($bytes > 0) {
                $limits[] = max(1, (int) floor(($bytes - ($key === 'post_max_size' ? 65536 : 0)) / 1024));
            }
        }

        return min($limits);
    }

    public function store(UploadedFile $file): array
    {
        $reject = fn () => throw ValidationException::withMessages(['file' => 'Dosyanın gerçek türü, yapısı veya boyutu desteklenmiyor. PDF, Word, Excel, JPG veya PNG seçin.']);
        if (! $file->isValid() || $file->getSize() < 1 || $file->getSize() > self::maxKb() * 1024) {
            $reject();
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $path = $file->getRealPath();
        $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (! isset($types[$ext])) {
            $reject();
        }
        $head = file_get_contents($path, false, null, 0, 16);
        if (in_array($ext, ['docx', 'xlsx'], true)) {
            if (! in_array($mime, [$types[$ext], 'application/zip'], true)) {
                $reject();
            }
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                $reject();
            }
            try {
                $required = $ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
                if ($zip->numFiles > 2000 || $zip->locateName('[Content_Types].xml') === false || $zip->locateName($required) === false) {
                    $reject();
                }
                $total = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->statIndex($i);
                    $total += $entry['size'];
                    if ($total > 52428800 || ($entry['encryption_method'] ?? 0) !== 0 || preg_match('~(^/|\.\.|\\\\|vbaProject|activeX|embeddings/)~i', $entry['name'])) {
                        $reject();
                    }
                }
                $contentTypes = $zip->getFromName('[Content_Types].xml');
                if (! is_string($contentTypes) || str_contains(strtolower($contentTypes), 'macroenabled') || ! str_contains($contentTypes, $ext === 'docx' ? 'wordprocessingml.document.main+xml' : 'spreadsheetml.sheet.main+xml')) {
                    $reject();
                }
            } finally {
                $zip->close();
            }
        } elseif (in_array($ext, ['doc', 'xls'], true)) {
            if (! in_array($mime, [$types[$ext], 'application/CDFV2', 'application/x-ole-storage'], true) || ! str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
                $reject();
            }
            $bytes = file_get_contents($path);
            $markers = $ext === 'doc' ? ['WordDocument'] : ['Workbook', 'Book'];
            if (! collect($markers)->contains(fn ($m) => str_contains($bytes, mb_convert_encoding($m, 'UTF-16LE', 'UTF-8')))) {
                $reject();
            }
        } else {
            if ($mime !== $types[$ext]) {
                $reject();
            }
            if ($ext === 'pdf') {
                if (! str_starts_with($head, '%PDF-') || ! str_contains(file_get_contents($path), '%%EOF')) {
                    $reject();
                }
            } else {
                $image = @getimagesize($path);
                if (! $image || ($image['mime'] ?? '') !== $types[$ext] || $image[0] * $image[1] > 30000000) {
                    $reject();
                }
            }
        }
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', $name), 0, 240);
        $stored = (string) Str::uuid().'.'.$ext;
        $stream = fopen($path, 'rb');
        try {
            if (! Storage::disk('documents')->put($stored, $stream)) {
                throw new \RuntimeException('Document storage failed.');
            }
        } catch (\Throwable $error) {
            Storage::disk('documents')->delete($stored);
            throw $error;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return ['path' => $stored, 'original_name' => $name, 'mime' => $types[$ext], 'extension' => $ext, 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $path)];
    }
}
