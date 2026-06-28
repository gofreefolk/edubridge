<?php

namespace App\Services\Storage;

use App\Models\Notice;
use App\Models\NoticeAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachmentStorageService
{
    public function diskName(): string
    {
        return (string) config('edubridge.attachments.disk', 'public');
    }

    public function store(Notice $notice, UploadedFile $file): NoticeAttachment
    {
        $diskName = $this->diskName();
        $directory = "notices/{$notice->school_id}/{$notice->id}";

        $options = $this->uploadOptions($diskName);

        $path = Storage::disk($diskName)->putFile($directory, $file, $options);

        return NoticeAttachment::query()->create([
            'notice_id' => $notice->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => $diskName,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function url(NoticeAttachment $attachment): string
    {
        $diskName = $attachment->disk ?: 'public';
        $disk = Storage::disk($diskName);

        if ($diskName === 's3' && $this->usesPrivateUrls()) {
            return $disk->temporaryUrl(
                $attachment->path,
                now()->addMinutes($this->temporaryUrlMinutes()),
            );
        }

        return $disk->url($attachment->path);
    }

    /** @return array<string, mixed> */
    private function uploadOptions(string $diskName): array
    {
        if ($diskName !== 's3') {
            return [];
        }

        return [
            'visibility' => (string) config('edubridge.attachments.visibility', 'private'),
        ];
    }

    private function usesPrivateUrls(): bool
    {
        return config('edubridge.attachments.visibility', 'private') === 'private';
    }

    private function temporaryUrlMinutes(): int
    {
        return max(1, (int) config('edubridge.attachments.temporary_url_minutes', 60));
    }
}
