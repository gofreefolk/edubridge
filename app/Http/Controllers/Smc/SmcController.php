<?php

namespace App\Http\Controllers\Smc;

use App\Http\Controllers\Controller;
use App\Models\SmcDevelopmentItem;
use App\Models\SmcMeeting;
use App\Models\SmcMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmcController extends Controller
{
    public function board(Request $request): JsonResponse
    {
        $schoolId = (int) $request->query('school_id');
        $request->validate(['school_id' => ['required', 'exists:schools,id']]);

        return response()->json([
            'members' => SmcMember::query()->where('school_id', $schoolId)->where('is_active', true)->get(),
            'meetings' => SmcMeeting::query()->where('school_id', $schoolId)->orderByDesc('scheduled_at')->limit(10)->get(),
            'development_items' => SmcDevelopmentItem::query()->where('school_id', $schoolId)->get(),
            'grievances' => \App\Models\FeedbackThread::query()
                ->where('school_id', $schoolId)
                ->where('direction', 'parent_to_smc')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function storeMeeting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'title' => ['required', 'string'],
            'agenda' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
        ]);

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

        $meeting->update($data);

        return response()->json(['meeting' => $meeting]);
    }
}
