<?php

namespace App\Services\WhatsApp;

interface WhatsAppDriver
{
    public function send(string $phone, string $message): bool;
}
