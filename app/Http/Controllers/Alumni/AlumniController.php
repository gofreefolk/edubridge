<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\AlumniEvent;
use App\Models\AlumniProfile;
use App\Models\DonationCampaign;
use App\Models\JobPosting;
use App\Models\MentorshipRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumniController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function portal(Request $request): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'exists:schools,id']]);
        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], 'alumni');

        return response()->json([
            'profile' => AlumniProfile::query()
                ->where('school_id', $school->id)
                ->where('user_id', $request->user()->id)
                ->first(),
            'directory' => AlumniProfile::query()
                ->where('school_id', $school->id)
                ->where('is_public', true)
                ->orderByDesc('batch_year')
                ->limit(50)
                ->get(),
            'events' => AlumniEvent::query()
                ->where('school_id', $school->id)
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->get(),
            'jobs' => JobPosting::query()->where('school_id', $school->id)->where('is_active', true)->latest()->get(),
            'campaigns' => DonationCampaign::query()->where('school_id', $school->id)->where('is_active', true)->get(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'batch_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'current_city' => ['nullable', 'string', 'max:255'],
            'current_job' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
        ]);

        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], 'alumni');

        $profile = AlumniProfile::query()->updateOrCreate(
            ['school_id' => $school->id, 'user_id' => $request->user()->id],
            $data,
        );

        return response()->json(['profile' => $profile]);
    }

    public function requestMentorship(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'student_id' => ['required', 'integer'],
            'topic' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], 'alumni');

        if (! Student::query()->whereKey($data['student_id'])->where('school_id', $school->id)->exists()) {
            abort(422, __('edubridge.invalid_reference'));
        }

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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'company' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email'],
        ]);

        $this->schoolForRoles($request->user(), (int) $data['school_id'], 'alumni');

        $job = JobPosting::query()->create([
            ...$data,
            'posted_by' => $request->user()->id,
        ]);

        return response()->json(['job' => $job], 201);
    }
}
