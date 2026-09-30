<?php

namespace App\Services\Admin;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use App\Services\Auth\PhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ParentStudentImportService
{
    private const REQUIRED_COLUMNS = [
        'student_name',
        'class',
        'section',
        'parent_name',
        'parent_phone',
    ];

    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SchoolManagementService $management,
    ) {}

    public function import(School $school, UploadedFile $file, bool $whatsappConsent = false): array
    {
        $rows = $this->parseCsv($file);
        $imported = 0;
        $errors = [];

        $academicYear = $this->management->currentAcademicYear($school);

        foreach ($rows as $lineNumber => $row) {
            try {
                // One transaction per row: a bad row rolls back cleanly without
                // leaving half-created classes, students or parents behind.
                DB::transaction(fn () => $this->importRow($school, $academicYear, $row, $whatsappConsent));
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'line' => $lineNumber,
                    'message' => $e->getMessage() === 'invalid_phone'
                        ? __('edubridge.invalid_phone')
                        : $e->getMessage(),
                ];
            }
        }

        return [
            'imported' => $imported,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('Could not read CSV file.');
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            throw new InvalidArgumentException('CSV file is empty.');
        }

        // Strip a UTF-8 BOM that Excel adds to the first header cell.
        $header = array_map(fn ($col) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $col))), $header);

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);

                throw new InvalidArgumentException("Missing required column: {$column}");
            }
        }

        $rows = [];
        $lineNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = trim($data[$index] ?? '');
            }

            $rows[$lineNumber] = $row;
        }

        fclose($handle);

        if ($rows === []) {
            throw new InvalidArgumentException('CSV has no data rows.');
        }

        return $rows;
    }

    private function importRow(School $school, AcademicYear $academicYear, array $row, bool $whatsappConsent): void
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if ($row[$column] === '') {
                throw new InvalidArgumentException("Missing value for {$column}");
            }
        }

        // Validate the phone before creating anything.
        $phone = $this->phoneNormalizer->normalize($row['parent_phone']);

        $class = SchoolClass::query()->firstOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'name' => $row['class'],
            ],
            [
                'school_id' => $school->id,
                'sort_order' => (int) preg_replace('/\D/', '', $row['class']) ?: 0,
            ],
        );

        $section = Section::query()->firstOrCreate(
            [
                'school_class_id' => $class->id,
                'name' => $row['section'],
            ],
        );

        $parent = User::query()->firstOrCreate(
            ['phone' => $phone],
            ['name' => $row['parent_name']],
        );

        $student = $this->resolveStudent($school, $class, $section, $parent, $row);

        if ($parent->name !== $row['parent_name']) {
            $parent->update(['name' => $row['parent_name']]);
        }

        $parent->assignSchoolRole($school->id, 'parent');

        $relationship = $this->normalizeRelationship($row['relationship'] ?? 'guardian');

        $parent->children()->syncWithoutDetaching([
            $student->id => ['relationship' => $relationship, 'is_primary' => true],
        ]);

        if ($whatsappConsent) {
            WhatsAppOptIn::query()->firstOrCreate(
                ['user_id' => $parent->id, 'school_id' => $school->id],
                ['opted_in' => true, 'opted_in_at' => now()],
            );
        }
    }

    /**
     * With an admission number, upsert by it. Without one, reuse the same child already
     * linked to this parent (so re-importing a sheet does not duplicate students), and
     * otherwise create a new student with a guaranteed-unique generated number.
     */
    private function resolveStudent(School $school, SchoolClass $class, Section $section, User $parent, array $row): Student
    {
        $attributes = [
            'name' => $row['student_name'],
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'status' => 'active',
        ];

        $admissionNumber = $row['admission_number'] ?? '';

        if ($admissionNumber !== '') {
            return Student::query()->updateOrCreate(
                ['school_id' => $school->id, 'admission_number' => $admissionNumber],
                $attributes,
            );
        }

        $existing = $parent->children()
            ->where('students.school_id', $school->id)
            ->where('students.name', $row['student_name'])
            ->first();

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return Student::query()->create([
            ...$attributes,
            'school_id' => $school->id,
            'admission_number' => $this->generateAdmissionNumber($school),
        ]);
    }

    private function generateAdmissionNumber(School $school): string
    {
        $prefix = strtoupper($school->code).'-'.now()->format('Y').'-';

        do {
            $candidate = $prefix.strtoupper(bin2hex(random_bytes(3)));
        } while (Student::query()->where('school_id', $school->id)->where('admission_number', $candidate)->exists());

        return $candidate;
    }

    private function normalizeRelationship(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'mother', 'father', 'guardian', 'grandparent', 'other' => $value,
            default => 'guardian',
        };
    }
}
