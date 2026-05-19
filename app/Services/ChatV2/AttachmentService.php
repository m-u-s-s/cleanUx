<?php

namespace App\Services\ChatV2;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttachmentService
{
    /**
     * Validate + store attachment. Returns array {path, mime, size_bytes} or throws.
     */
    public function store(UploadedFile $file): array
    {
        if (! (bool) config('chat_v2.attachments_enabled', true)) {
            throw ValidationException::withMessages(['attachment' => ['Les pièces jointes sont désactivées.']]);
        }

        $maxKb = (int) config('chat_v2.max_attachment_size_kb', 5120);
        $sizeKb = (int) ceil($file->getSize() / 1024);
        if ($sizeKb > $maxKb) {
            throw ValidationException::withMessages([
                'attachment' => ["Pièce jointe trop grande ({$sizeKb}KB > {$maxKb}KB)."],
            ]);
        }

        $allowed = (array) config('chat_v2.allowed_mime_types', []);
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        if (! empty($allowed) && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'attachment' => ["Type MIME non autorisé ({$mime})."],
            ]);
        }

        $disk = (string) config('chat_v2.attachments_disk', 'local');
        $prefix = trim((string) config('chat_v2.attachments_path_prefix', 'chat_attachments'), '/');
        $name = Str::lower(Str::random(20)) . '_' . preg_replace('/[^a-z0-9_.-]+/i', '_', $file->getClientOriginalName());
        $path = $prefix . '/' . date('Y/m/d') . '/' . $name;

        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        return [
            'path' => $path,
            'mime' => $mime,
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    public function delete(string $path): bool
    {
        $disk = (string) config('chat_v2.attachments_disk', 'local');
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }
        return true;
    }
}
