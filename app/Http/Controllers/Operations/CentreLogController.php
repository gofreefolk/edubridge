<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\CentreLog;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Daily notes and incident reports. Staff see everything at their school; parents see
 * only entries marked visible to parents that concern their child or the child's class.
 */
class CentreLogController extends Controller
{
    use AuthorizesSchoolAdmin;

    private const STAFF_ROLES = ['teacher', 'school_admin'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'student_id' => ['nullable', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'category' => ['nullable', Rule::in(CentreLog::CATEGORIES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $schoolId = (int) $data['school_id'];

        $query = CentreLog::query()
            ->where('school_id', $schoolId)
            ->with(['author:id,name', 'student:id,name', 'schoolClass:id,name'])
            ->when($data['student_id'] ?? null, fn ($q, $id) => $q->where('student_id', $id))
            ->when($data['school_class_id'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '<=', $d))
            ->orderByDesc('occurred_at');

        $isStaff = $user->hasRoleAtSchool($schoolId, ...self::STAFF_ROLES);
        $isAdmin = $user->hasRoleAtSchool($schoolId, 'school_admin');

        if (! $isStaff) {
            abort_unless($user->hasRoleAtSchool($schoolId, 'parent', 'grandparent'), 403, __('edubridge.unauthorized_role'));
            $this->scopeToParent($query, $user->children()->where('students.school_id', $schoolId)->get());
        }

        $logs = $query->paginate(30);

        return response()->json([
            'logs' => $logs->getCollection()->map(fn (CentreLog $log) => $this->payload($log, $user->id, $isStaff, $isAdmin)),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
            'can_write' => $isStaff,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'school_class_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'category' => ['required', Rule::in(CentreLog::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'visible_to_parents' => ['sometimes', 'boolean'],
        ]);

        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], ...self::STAFF_ROLES);
        $this->ensureAcademicRefsBelongToSchool($school->id, $data['school_class_id'] ?? null);

        $classId = $data['school_class_id'] ?? null;

        if (! empty($data['student_id'])) {
            $student = Student::query()->whereKey($data['student_id'])->where('school_id', $school->id)->first();
            abort_unless($student, 422, __('edubridge.invalid_reference'));
            $classId ??= $student->school_class_id;
        }

        $log = CentreLog::query()->create([
            'school_id' => $school->id,
            'school_class_id' => $classId,
            'student_id' => $data['student_id'] ?? null,
            'author_id' => $request->user()->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'body' => $data['body'],
            'occurred_at' => $data['occurred_at'] ?? now(),
            'visible_to_parents' => $data['visible_to_parents'] ?? false,
        ]);

        $log->load(['author:id,name', 'student:id,name', 'schoolClass:id,name']);

        return response()->json(['log' => $this->payload($log, $request->user()->id, true, $request->user()->hasRoleAtSchool($school->id, 'school_admin'))], 201);
    }

    public function destroy(Request $request, CentreLog $log): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRoleAtSchool($log->school_id, 'school_admin')
                || ($log->author_id === $user->id && $user->hasRoleAtSchool($log->school_id, ...self::STAFF_ROLES)),
            403,
            __('edubridge.forbidden'),
        );

        $log->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Parents see shared entries about their own child, their child's class, or the
     * whole school (no class and no student).
     */
    private function scopeToParent(Builder $query, $children): void
    {
        $query->where('visible_to_parents', true);

        if ($children->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($children) {
            $q->whereIn('student_id', $children->pluck('id'))
                ->orWhere(fn (Builder $q2) => $q2
                    ->whereNull('student_id')
                    ->where(fn (Builder $q3) => $q3
                        ->whereNull('school_class_id')
                        ->orWhereIn('school_class_id', $children->pluck('school_class_id')->filter())));
        });
    }

    private function payload(CentreLog $log, int $userId, bool $isStaff, bool $isAdmin = false): array
    {
        return [
            'id' => $log->id,
            'category' => $log->category,
            'title' => $log->title,
            'body' => $log->body,
            'occurred_at' => $log->occurred_at->toIso8601String(),
            'visible_to_parents' => $log->visible_to_parents,
            'author' => $log->author?->name,
            'student' => $log->student ? ['id' => $log->student->id, 'name' => $log->student->name] : null,
            'class' => $log->schoolClass?->name,
            'can_delete' => $isAdmin || ($isStaff && $log->author_id === $userId),
        ];
    }
}
