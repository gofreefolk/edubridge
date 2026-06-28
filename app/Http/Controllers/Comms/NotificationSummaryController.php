<?php

namespace App\Http\Controllers\Comms;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppOptIn;
use App\Services\Comms\NotificationSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSummaryController extends Controller
{
    public function __construct(
        private readonly NotificationSummaryService $summaryService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        return response()->json(
            $this->summaryService->summary($request->user(), (int) $validated['school_id']),
        );
    }
}
