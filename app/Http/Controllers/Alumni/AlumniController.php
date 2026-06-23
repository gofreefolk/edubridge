<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\AlumniEvent;
use App\Models\AlumniProfile;
use App\Models\DonationCampaign;
use App\Models\JobPosting;
use App\Models\MentorshipRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumniController extends Controller
{
    public function portal(Request $request): JsonResponse
    {
        $schoolId = (int) $request->query('school_id');
        $request->validate(['school_id' => ['required', 'exists:schools,id']]);

        return response()->json([
            'profile' => AlumniProfile::query()
                ->where('school_id', $schoolId)
                ->where('user_id', $request->user()->id)
                ->first(),
            'directory' => AlumniProfile::query()
                ->where('school_id', $schoolId)
                ->where('is_public', true)
                ->orderByDesc('batch_year')
                ->limit(50)
                ->get(),
            'events' => AlumniEvent::query()
                ->where('school_id', $schoolId)
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->get(),
            'jobs' => JobPosting::query()->where('school_id', $schoolId)->where('is_active', true)->latest()->get(),
            'campaigns' => DonationCampaign::query()->where('school_id', $schoolId)->where('is_active', true)->get(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'batch_year' => ['nullable', 'integer'],
            'current_city' => ['nullable', 'string'],
            'current_job' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'is_public' => ['boolean'],
        ]);

        $profile = AlumniProfile::query()->updateOrCreate(
            ['school_id' => $data['school_id'], 'user_id' => $request->user()->id],
            $data,
        );

        return response()->json(['profile' => $profile]);
    }

    public function requestMentorship(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'student_id' => ['required', 'exists:students,id'],
            'topic' => ['required', 'string'],
            'message' => ['nullable', 'string'],
        ]);

        $mentorship = MentorshipRequest::query()->create([
            ...$data,
            'alumni_user_id' => $request->user()->id,
        ]);

        return response()->json(['mentorship' => $mentorship], 201);
    }

    public function postJob(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'company' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'contact_email' => ['nullable', 'email'],
        ]);

        $job = JobPosting::query()->create([
            ...$data,
            'posted_by' => $request->user()->id,
        ]);

        return response()->json(['job' => $job], 201);
    }
}
