<?php

namespace App\Services\WhatsApp;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

interface WhatsAppDriver
{
    public function send(string $phone, string $message): bool;
}

class WhatsAppService
{
    public function __construct(
        private readonly WhatsAppDriver $driver,
    ) {}

    public function sendToUser(User $user, string $message, string $type, ?int $schoolId = null): bool
    {
        if (! $user->phone) {
            return false;
        }

        $log = NotificationLog::query()->create([
            'school_id' => $schoolId,
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'type' => $type,
            'payload' => $message,
            'status' => 'pending',
        ]);

        try {
            $sent = $this->driver->send($user->phone, $message);
            $log->update([
                'status' => $sent ? 'sent' : 'failed',
                'sent_at' => $sent ? now() : null,
            ]);

            return $sent;
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function buildUrgentNoticeMessage(string $schoolName, string $title, string $magicUrl): string
    {
        return "{$schoolName}: {$title}. Open: {$magicUrl}";
    }
}
