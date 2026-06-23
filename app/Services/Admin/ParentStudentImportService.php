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
    ) {}

    public function import(School $school, UploadedFile $file): array
    {
        $rows = $this->parseCsv($file);
        $imported = 0;
        $errors = [];

        $academicYear = $this->resolveAcademicYear($school);

        DB::transaction(function () use ($school, $academicYear, $rows, &$imported, &$errors) {
            foreach ($rows as $lineNumber => $row) {
                try {
                    $this->importRow($school, $academicYear, $row);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'line' => $lineNumber,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });

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

        $header = array_map(fn ($col) => strtolower(trim($col)), $header);

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

    private function resolveAcademicYear(School $school): AcademicYear
    {
        $year = AcademicYear::query()
            ->where('school_id', $school->id)
            ->where('is_current', true)
            ->first();

        if ($year) {
            return $year;
        }

        return AcademicYear::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => '2025-26'],
            [
                'starts_on' => '2025-06-01',
                'ends_on' => '2026-03-31',
                'is_current' => true,
            ],
        );
    }

    private function importRow(School $school, AcademicYear $academicYear, array $row): void
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if ($row[$column] === '') {
                throw new InvalidArgumentException("Missing value for {$column}");
            }
        }

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

        $admissionNumber = $row['admission_number'] !== ''
            ? $row['admission_number']
            : strtoupper($school->code).'-'.now()->format('Y').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        $student = Student::query()->updateOrCreate(
            [
                'school_id' => $school->id,
                'admission_number' => $admissionNumber,
            ],
            [
                'name' => $row['student_name'],
                'school_class_id' => $class->id,
                'section_id' => $section->id,
                'status' => 'active',
            ],
        );

        $phone = $this->phoneNormalizer->normalize($row['parent_phone']);

        $parent = User::query()->firstOrCreate(
            ['phone' => $phone],
            ['name' => $row['parent_name']],
        );

        if ($parent->name !== $row['parent_name']) {
            $parent->update(['name' => $row['parent_name']]);
        }

        $parent->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'parent', 'is_active' => true],
        ]);

        $relationship = $this->normalizeRelationship($row['relationship'] ?? 'guardian');

        $parent->children()->syncWithoutDetaching([
            $student->id => ['relationship' => $relationship, 'is_primary' => true],
        ]);

        WhatsAppOptIn::query()->firstOrCreate(
            ['user_id' => $parent->id, 'school_id' => $school->id],
            ['opted_in' => true, 'opted_in_at' => now()],
        );
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
