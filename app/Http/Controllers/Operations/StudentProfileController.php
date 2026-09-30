<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentContact;
use App\Models\User;
use App\Services\Auth\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Extended student profile: health details, address, emergency contacts and the
 * people authorised to pick the child up. Staff and linked parents can read it;
 * only the school admin can change it.
 */
class StudentProfileController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->ensureCanAccessStudent($request->user(), $student);

        $student->load(['schoolClass:id,name', 'section:id,name', 'parents:id,name,phone', 'contacts']);

        return response()->json([
            'student' => $this->payload($student),
            'can_edit' => $request->user()->hasRoleAtSchool($student->school_id, 'school_admin'),
        ]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $student->school_id);

        $data = $request->validate([
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'in:male,female,other'],
            'blood_group' => ['sometimes', 'nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'allergies' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'medical_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $student->update($data);

        return $this->show($request, $student->fresh());
    }

    public function storeContact(Request $request, Student $student): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $student->school_id);
        $data = $this->validateContact($request);

        $student->contacts()->create($data);

        return $this->show($request, $student)->setStatusCode(201);
    }

    public function updateContact(Request $request, Student $student, StudentContact $contact): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $student->school_id);
        abort_unless($contact->student_id === $student->id, 404);

        $contact->update($this->validateContact($request));

        return $this->show($request, $student);
    }

    public function destroyContact(Request $request, Student $student, StudentContact $contact): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $student->school_id);
        abort_unless($contact->student_id === $student->id, 404);

        $contact->delete();

        return $this->show($request, $student);
    }

    private function validateContact(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
            'is_emergency' => ['sometimes', 'boolean'],
            'can_pickup' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $data['phone'] = $this->phoneNormalizer->normalize($data['phone']);
        } catch (InvalidArgumentException) {
            abort(422, __('edubridge.invalid_phone'));
        }

        return $data;
    }

    private function payload(Student $student): array
    {
        return [
            'id' => $student->id,
            'school_id' => $student->school_id,
            'name' => $student->name,
            'admission_number' => $student->admission_number,
            'status' => $student->status,
            'class' => $student->schoolClass?->name,
            'section' => $student->section?->name,
            'date_of_birth' => $student->date_of_birth?->toDateString(),
            'gender' => $student->gender,
            'blood_group' => $student->blood_group,
            'allergies' => $student->allergies,
            'medical_notes' => $student->medical_notes,
            'address' => $student->address,
            'parents' => $student->parents->map(fn (User $parent) => [
                'id' => $parent->id,
                'name' => $parent->name,
                'phone' => $parent->phone,
                'relationship' => $parent->pivot->relationship,
                'is_primary' => (bool) $parent->pivot->is_primary,
            ]),
            'contacts' => $student->contacts->map(fn (StudentContact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'relationship' => $c->relationship,
                'phone' => $c->phone,
                'is_emergency' => $c->is_emergency,
                'can_pickup' => $c->can_pickup,
                'notes' => $c->notes,
            ]),
        ];
    }
}
