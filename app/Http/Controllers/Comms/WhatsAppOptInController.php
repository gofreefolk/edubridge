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
        $schoolId = $this->authorizedSchoolId($request);

        $record = WhatsAppOptIn::query()
            ->where('user_id', $request->user()->id)
            ->where('school_id', $schoolId)
            ->first();

        return response()->json([
            'opted_in' => $record?->opted_in ?? false,
            'opted_in_at' => $record?->opted_in_at?->toIso8601String(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $schoolId = $this->authorizedSchoolId($request);
        $validated = $request->validate([
            'opted_in' => ['required', 'boolean'],
        ]);

        $record = WhatsAppOptIn::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'school_id' => $schoolId,
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

    private function authorizedSchoolId(Request $request): int
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $schoolId = (int) $validated['school_id'];

        abort_unless(
            $request->user()->hasRoleAtSchool($schoolId, 'parent', 'grandparent'),
            403,
            __('edubridge.unauthorized_role'),
        );

        return $schoolId;
    }
}
