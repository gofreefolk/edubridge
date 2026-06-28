<?php

namespace App\Services\Platform;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolAdminInvite;
use App\Models\User;
use App\Services\Auth\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SchoolOnboardingService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * @param  array{name: string, code: string, district?: string|null, type: string, whatsapp_bridge_enabled?: bool, admin_name: string, admin_phone: string}  $data
     */
    public function createApprovedSchool(array $data, User $createdBy): array
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $school = $this->createSchoolRecord($data, School::APPROVAL_APPROVED, $createdBy);
            $this->bootstrapAcademicYear($school);
            $invite = $this->createInvite(
                $school,
                $data['admin_name'],
                $data['admin_phone'],
                $createdBy,
            );

            return [
                'school' => $school->fresh(),
                'invite' => $invite,
            ];
        });
    }

    /**
     * @param  array{name: string, code: string, district?: string|null, type: string, admin_name: string, admin_phone: string}  $data
     */
    public function submitRegistration(array $data): School
    {
        $phone = $this->phoneNormalizer->normalize($data['admin_phone']);

        return DB::transaction(function () use ($data, $phone) {
            $school = School::query()->create([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'district' => $data['district'] ?? null,
                'type' => $data['type'],
                'whatsapp_bridge_enabled' => true,
                'is_active' => false,
                'approval_status' => School::APPROVAL_PENDING,
                'admin_contact_name' => $data['admin_name'],
                'admin_contact_phone' => $phone,
            ]);

            $this->bootstrapAcademicYear($school);

            return $school;
        });
    }

    public function approveSchool(School $school, User $reviewer): array
    {
        if ($school->approval_status !== School::APPROVAL_PENDING) {
            throw new InvalidArgumentException('school_not_pending');
        }

        return DB::transaction(function () use ($school, $reviewer) {
            $school->update([
                'approval_status' => School::APPROVAL_APPROVED,
                'is_active' => true,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer->id,
            ]);

            $invite = null;
            if ($school->admin_contact_phone && $school->admin_contact_name) {
                $invite = $this->createInvite(
                    $school,
                    $school->admin_contact_name,
                    $school->admin_contact_phone,
                    $reviewer,
                );
            }

            return [
                'school' => $school->fresh(),
                'invite' => $invite,
            ];
        });
    }

    public function rejectSchool(School $school, User $reviewer, string $reason): School
    {
        if ($school->approval_status !== School::APPROVAL_PENDING) {
            throw new InvalidArgumentException('school_not_pending');
        }

        $school->update([
            'approval_status' => School::APPROVAL_REJECTED,
            'is_active' => false,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
        ]);

        return $school->fresh();
    }

    public function createInvite(School $school, string $name, string $rawPhone, ?User $invitedBy): SchoolAdminInvite
    {
        if (! $school->isApproved()) {
            throw new InvalidArgumentException('school_not_approved');
        }

        $phone = $this->phoneNormalizer->normalize($rawPhone);

        SchoolAdminInvite::query()
            ->where('school_id', $school->id)
            ->where('phone', $phone)
            ->where('status', SchoolAdminInvite::STATUS_PENDING)
            ->update(['status' => SchoolAdminInvite::STATUS_REVOKED]);

        return SchoolAdminInvite::query()->create([
            'school_id' => $school->id,
            'phone' => $phone,
            'name' => $name,
            'token' => Str::random(48),
            'status' => SchoolAdminInvite::STATUS_PENDING,
            'invited_by_user_id' => $invitedBy?->id,
            'expires_at' => now()->addDays(14),
        ]);
    }

    public function acceptInvite(string $token, User $user): SchoolAdminInvite
    {
        $invite = SchoolAdminInvite::query()
            ->with('school')
            ->where('token', $token)
            ->first();

        if (! $invite || $invite->status !== SchoolAdminInvite::STATUS_PENDING) {
            throw new InvalidArgumentException('invite_invalid');
        }

        if ($invite->expires_at->isPast()) {
            $invite->update(['status' => SchoolAdminInvite::STATUS_EXPIRED]);
            throw new InvalidArgumentException('invite_expired');
        }

        if ($user->phone !== $invite->phone) {
            throw new InvalidArgumentException('invite_phone_mismatch');
        }

        return DB::transaction(function () use ($invite, $user) {
            $user->update(['name' => $invite->name]);

            $user->schools()->syncWithoutDetaching([
                $invite->school_id => ['role' => 'school_admin', 'is_active' => true],
            ]);

            $invite->update([
                'status' => SchoolAdminInvite::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
            ]);

            return $invite->fresh(['school']);
        });
    }

    public function inviteUrl(SchoolAdminInvite $invite): string
    {
        return url('/invite/admin/'.$invite->token);
    }

    public function acceptPendingInviteForUser(User $user): ?SchoolAdminInvite
    {
        if (count($user->getRoles()) > 0) {
            return null;
        }

        $invite = SchoolAdminInvite::query()
            ->where('phone', $user->phone)
            ->where('status', SchoolAdminInvite::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $invite) {
            return null;
        }

        try {
            return $this->acceptInvite($invite->token, $user);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function createSchoolRecord(array $data, string $approvalStatus, ?User $reviewer): School
    {
        $phone = $this->phoneNormalizer->normalize($data['admin_phone']);

        return School::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'district' => $data['district'] ?? null,
            'type' => $data['type'],
            'whatsapp_bridge_enabled' => $data['whatsapp_bridge_enabled'] ?? true,
            'is_active' => $approvalStatus === School::APPROVAL_APPROVED,
            'approval_status' => $approvalStatus,
            'admin_contact_name' => $data['admin_name'],
            'admin_contact_phone' => $phone,
            'reviewed_at' => $approvalStatus === School::APPROVAL_APPROVED ? now() : null,
            'reviewed_by_user_id' => $reviewer?->id,
        ]);
    }

    private function bootstrapAcademicYear(School $school): AcademicYear
    {
        $year = now()->year;
        $name = now()->month >= 6
            ? $year.'-'.substr((string) ($year + 1), -2)
            : ($year - 1).'-'.substr((string) $year, -2);

        return AcademicYear::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => $name],
            [
                'starts_on' => now()->month >= 6
                    ? now()->setMonth(6)->setDay(1)->toDateString()
                    : now()->subYear()->setMonth(6)->setDay(1)->toDateString(),
                'ends_on' => now()->month >= 6
                    ? now()->addYear()->setMonth(3)->setDay(31)->toDateString()
                    : now()->setMonth(3)->setDay(31)->toDateString(),
                'is_current' => true,
            ],
        );
    }
}
