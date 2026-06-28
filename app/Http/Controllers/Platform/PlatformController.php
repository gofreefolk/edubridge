<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $schools = School::query()
            ->withCount(['students', 'users', 'notices'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'name', 'code', 'district', 'type', 'is_active', 'created_at']);

        return response()->json([
            'stats' => [
                'schools' => School::query()->count(),
                'active_schools' => School::query()->where('is_active', true)->count(),
                'pending_registrations' => School::query()->where('approval_status', School::APPROVAL_PENDING)->count(),
                'users' => User::query()->count(),
                'students' => Student::query()->count(),
                'notices' => Notice::query()->count(),
                'published_notices' => Notice::query()->whereNotNull('published_at')->count(),
            ],
            'recent_schools' => $schools->map(fn (School $school) => $this->schoolPayload($school)),
        ]);
    }

    public function schools(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $schools = School::query()
            ->withCount(['students', 'users', 'notices'])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'district', 'type', 'is_active', 'whatsapp_bridge_enabled', 'created_at']);

        return response()->json([
            'schools' => $schools->map(fn (School $school) => $this->schoolPayload($school)),
        ]);
    }

    private function schoolPayload(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'code' => $school->code,
            'district' => $school->district,
            'type' => $school->type,
            'is_active' => $school->is_active,
            'approval_status' => $school->approval_status,
            'whatsapp_bridge_enabled' => $school->whatsapp_bridge_enabled,
            'students_count' => $school->students_count ?? 0,
            'users_count' => $school->users_count ?? 0,
            'notices_count' => $school->notices_count ?? 0,
            'created_at' => $school->created_at?->toIso8601String(),
        ];
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Platform admin access required.');
    }
}
