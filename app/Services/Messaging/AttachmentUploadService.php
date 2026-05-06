<?php

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service d'upload sécurisé des pièces jointes :
 *   - validation MIME + size côté serveur
 *   - storage sur disk configurable (par défaut 'public')
 *   - génération de thumbnail 480x360 pour les images (si extension imagick/gd)
 *   - status AV initial = 'pending' (job de scan async optionnel)
 *
 * NB: l'ouverture de l'option AV (config.messaging.av.required = true)
 * empêchera les attachments d'apparaître à d'autres users tant qu'ils
 * sont en pending.
 */
class AttachmentUploadService
{
    public const ALLOWED_MIMES = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Texte
        'text/plain', 'text/csv',
        // Archives (limité)
        'application/zip',
    ];

    public const MAX_SIZE_BYTES = 25 * 1024 * 1024; // 25 MB

    public function attach(
        Message $message,
        User $uploader,
        UploadedFile $file,
    ): MessageAttachment {
        $this->validate($file);

        $disk = (string) config('messaging.attachments.disk', 'public');

        // Random subfolder par mois pour éviter les répertoires trop chargés
        $folder = 'message-attachments/' . now()->format('Y/m');
        $extension = $file->getClientOriginalExtension();
        $filename  = Str::ulid() . ($extension ? '.' . $extension : '');
        $path = $file->storeAs($folder, $filename, $disk);

        $attrs = [
            'message_id'    => $message->id,
            'uploaded_by'   => $uploader->id,
            'disk'          => $disk,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size_bytes'    => $file->getSize(),
            'av_status'     => MessageAttachment::AV_STATUS_PENDING,
        ];

        // Si image, on extrait dimensions et on génère une thumb
        if ($attrs['mime_type'] && str_starts_with($attrs['mime_type'], 'image/')) {
            $this->processImageMetadata($file, $disk, $folder, $filename, $attrs);
        }

        return MessageAttachment::create($attrs);
    }

    private function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new \DomainException("Le fichier n'a pas pu être uploadé.");
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new \DomainException("Fichier trop volumineux (max " . (self::MAX_SIZE_BYTES / 1024 / 1024) . " MB).");
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \DomainException("Type de fichier non autorisé : {$mime}");
        }
    }

    private function processImageMetadata(
        UploadedFile $file,
        string $disk,
        string $folder,
        string $filename,
        array &$attrs
    ): void {
        try {
            // getimagesize fonctionne sans imagick — fallback safe
            $info = @getimagesize($file->getRealPath());
            if ($info && isset($info[0], $info[1])) {
                $attrs['image_width']  = (int) $info[0];
                $attrs['image_height'] = (int) $info[1];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Thumbnail si GD disponible
        if (! function_exists('imagecreatefromstring')) {
            return;
        }

        try {
            $imageData = file_get_contents($file->getRealPath());
            if (! $imageData) return;

            $src = @imagecreatefromstring($imageData);
            if (! $src) return;

            [$srcW, $srcH] = [imagesx($src), imagesy($src)];
            $maxW = 480;
            $maxH = 360;

            $ratio = min($maxW / $srcW, $maxH / $srcH, 1);
            $dstW  = max(1, (int) ($srcW * $ratio));
            $dstH  = max(1, (int) ($srcH * $ratio));

            $dst = imagecreatetruecolor($dstW, $dstH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            $thumbName = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
            $thumbPath = $folder . '/' . $thumbName;

            ob_start();
            imagejpeg($dst, null, 82);
            $jpegData = ob_get_clean();

            if ($jpegData) {
                Storage::disk($disk)->put($thumbPath, $jpegData);
                $attrs['thumbnail_path'] = $thumbPath;
            }

            imagedestroy($src);
            imagedestroy($dst);
        } catch (\Throwable $e) {
            report($e);
            // pas bloquant : on continue sans thumb
        }
    }
}
