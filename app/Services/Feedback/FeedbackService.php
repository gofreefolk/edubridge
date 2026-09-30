<?php

namespace App\Services\Feedback;

use App\Models\FeedbackThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FeedbackService
{
    /**
     * Which role at a school handles each thread direction.
     */
    private const HANDLER_ROLE = [
        'parent_to_teacher' => 'teacher',
        'parent_to_smc' => 'smc_member',
    ];

    public function inboxQuery(User $user, int $schoolId): Builder
    {
        $query = FeedbackThread::query()
            ->where('school_id', $schoolId)
            ->with([
                'creator:id,name,phone',
                'student:id,name',
                'messages' => fn ($q) => $q->with('author:id,name')->oldest(),
            ])
            ->latest();

        if ($user->hasRoleAtSchool($schoolId, 'school_admin')) {
            return $query;
        }

        $handledDirections = $this->handledDirections($user, $schoolId);

        $childIds = $user->children()->where('students.school_id', $schoolId)->pluck('students.id');

        return $query->where(function (Builder $q) use ($user, $handledDirections, $childIds) {
            $q->where('created_by', $user->id)
                ->orWhere('assigned_to', $user->id);

            if ($childIds->isNotEmpty()) {
                $q->orWhere(fn (Builder $staffThread) => $staffThread
                    ->where('direction', 'teacher_to_parent')
                    ->whereIn('student_id', $childIds));
            }

            if ($handledDirections !== []) {
                $q->orWhereIn('direction', $handledDirections);
            }
        });
    }

    public function canAccess(User $user, FeedbackThread $thread): bool
    {
        if ($user->hasRoleAtSchool($thread->school_id, 'school_admin')) {
            return true;
        }

        if (in_array($thread->direction, $this->handledDirections($user, $thread->school_id), true)) {
            return true;
        }

        if ($thread->direction === 'teacher_to_parent' && $thread->student_id && $user->isParentOf($thread->student_id)) {
            return true;
        }

        return $thread->created_by === $user->id || $thread->assigned_to === $user->id;
    }

    public function canResolve(User $user, FeedbackThread $thread): bool
    {
        if ($user->hasRoleAtSchool($thread->school_id, 'school_admin')) {
            return true;
        }

        return $thread->created_by === $user->id
            || in_array($thread->direction, $this->handledDirections($user, $thread->school_id), true);
    }

    /** @return list<string> */
    private function handledDirections(User $user, int $schoolId): array
    {
        $roles = $user->rolesAtSchool($schoolId);

        return array_keys(array_filter(
            self::HANDLER_ROLE,
            fn (string $role) => in_array($role, $roles, true),
        ));
    }

    public function threadPayload(FeedbackThread $thread): array
    {
        return [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'category' => $thread->category,
            'direction' => $thread->direction,
            'status' => $thread->status,
            'created_at' => $thread->created_at?->toIso8601String(),
            'creator' => $thread->creator ? [
                'id' => $thread->creator->id,
                'name' => $thread->creator->name,
                'phone' => $thread->creator->phone,
            ] : null,
            'student' => $thread->student ? [
                'id' => $thread->student->id,
                'name' => $thread->student->name,
            ] : null,
            'messages' => $thread->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'author' => [
                    'id' => $m->author?->id,
                    'name' => $m->author?->name,
                ],
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
            'last_message' => $thread->messages->last() ? [
                'body' => $thread->messages->last()->body,
                'created_at' => $thread->messages->last()->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
