<?php

namespace App\Http\Controllers\Smc;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\FeedbackThread;
use App\Models\SmcDevelopmentItem;
use App\Models\SmcMeeting;
use App\Models\SmcMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmcController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function board(Request $request): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'exists:schools,id']]);
        $user = $request->user();
        $school = $this->schoolForRoles($user, (int) $data['school_id'], 'smc_member', 'school_admin', 'parent', 'grandparent');

        // Grievances come from individual parents; only the committee and office see them.
        $canSeeGrievances = $user->hasRoleAtSchool($school->id, 'smc_member', 'school_admin');

        return response()->json([
            'members' => SmcMember::query()->where('school_id', $school->id)->where('is_active', true)->get(),
            'meetings' => SmcMeeting::query()->where('school_id', $school->id)->orderByDesc('scheduled_at')->limit(10)->get(),
            'development_items' => SmcDevelopmentItem::query()->where('school_id', $school->id)->get(),
            'grievances' => $canSeeGrievances
                ? FeedbackThread::query()
                    ->where('school_id', $school->id)
                    ->where('direction', 'parent_to_smc')
                    ->latest()
                    ->limit(20)
                    ->get()
                : [],
        ]);
    }

    public function storeMeeting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'title' => ['required', 'string', 'max:255'],
            'agenda' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
        ]);

        $this->schoolForAdmin($request->user(), (int) $data['school_id']);

        $meeting = SmcMeeting::query()->create([
            ...$data,
            'author_id' => $request->user()->id,
        ]);

        return response()->json(['meeting' => $meeting], 201);
    }

    public function updateMeetingMinutes(Request $request, SmcMeeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'string'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
        ]);

        $this->schoolForAdmin($request->user(), $meeting->school_id);

        $meeting->update($data);

        return response()->json(['meeting' => $meeting]);
    }
}
