<?php

namespace App\Http\Controllers\Comms;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppOptIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppOptInController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $record = WhatsAppOptIn::query()->firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'school_id' => $validated['school_id'],
            ],
            ['opted_in' => false],
        );

        return response()->json([
            'opted_in' => $record->opted_in,
            'opted_in_at' => $record->opted_in_at?->toIso8601String(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'opted_in' => ['required', 'boolean'],
        ]);

        $record = WhatsAppOptIn::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'school_id' => $validated['school_id'],
            ],
            [
                'opted_in' => $validated['opted_in'],
                'opted_in_at' => $validated['opted_in'] ? now() : null,
            ],
        );

        return response()->json([
            'opted_in' => $record->opted_in,
            'opted_in_at' => $record->opted_in_at?->toIso8601String(),
        ]);
    }
}
