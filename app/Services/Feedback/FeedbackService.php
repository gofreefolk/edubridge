<?php

namespace App\Services\Feedback;

use App\Models\FeedbackThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FeedbackService
{
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

        if ($user->hasAnyRole('super_admin') || $user->roleAtSchool($schoolId) === 'school_admin') {
            return $query;
        }

        if ($user->hasAnyRole('teacher') && $user->roleAtSchool($schoolId) === 'teacher') {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('direction', 'parent_to_teacher')
                    ->orWhere('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('assigned_to', $user->id);
        });
    }

    public function canAccess(User $user, FeedbackThread $thread): bool
    {
        if ($user->hasAnyRole('super_admin')) {
            return true;
        }

        if ($user->roleAtSchool($thread->school_id) === 'school_admin') {
            return true;
        }

        if ($user->roleAtSchool($thread->school_id) === 'teacher'
            && $thread->direction === 'parent_to_teacher') {
            return true;
        }

        return $thread->created_by === $user->id || $thread->assigned_to === $user->id;
    }

    public function canResolve(User $user, FeedbackThread $thread): bool
    {
        if ($user->hasAnyRole('super_admin')) {
            return true;
        }

        $role = $user->roleAtSchool($thread->school_id);

        return in_array($role, ['school_admin', 'teacher'], true)
            || $thread->created_by === $user->id;
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
