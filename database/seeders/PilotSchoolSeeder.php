<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AlumniEvent;
use App\Models\AlumniProfile;
use App\Models\AttendanceRecord;
use App\Models\CalendarEvent;
use App\Models\DonationCampaign;
use App\Models\Exam;
use App\Models\FeedbackMessage;
use App\Models\FeedbackThread;
use App\Models\Homework;
use App\Models\JobPosting;
use App\Models\Notice;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\SmcDevelopmentItem;
use App\Models\SmcMeeting;
use App\Models\SmcMember;
use App\Models\Student;
use App\Models\StudentRouteAssignment;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WhatsAppOptIn;
use App\Services\Calendar\CalendarService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PilotSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::query()->firstOrCreate(
            ['code' => 'STMS-LP'],
            [
                'name' => "St. Mary's LP School",
                'district' => 'Ernakulam',
                'type' => 'aided',
                'whatsapp_bridge_enabled' => true,
            ],
        );

        $year = AcademicYear::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => '2025-26'],
            [
                'starts_on' => '2025-06-01',
                'ends_on' => '2026-03-31',
                'is_current' => true,
            ],
        );

        $class3 = SchoolClass::query()->firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '3'],
            ['school_id' => $school->id, 'sort_order' => 3],
        );

        $sectionB = Section::query()->firstOrCreate(
            ['school_class_id' => $class3->id, 'name' => 'B'],
        );

        $admin = User::query()->firstOrCreate(
            ['phone' => '9876543210'],
            ['name' => 'School Admin', 'preferred_locale' => 'ml'],
        );
        $admin->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'school_admin', 'is_active' => true],
        ]);

        $superAdmin = User::query()->firstOrCreate(
            ['phone' => '9900000001'],
            ['name' => 'Platform Admin', 'preferred_locale' => 'en'],
        );
        DB::table('school_user')->updateOrInsert(
            [
                'school_id' => null,
                'user_id' => $superAdmin->id,
                'role' => 'super_admin',
            ],
            [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $teacher = User::query()->firstOrCreate(
            ['phone' => '9876501234'],
            ['name' => 'Suma Teacher', 'preferred_locale' => 'ml'],
        );
        $teacher->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'teacher', 'is_active' => true],
        ]);

        $parent = User::query()->firstOrCreate(
            ['phone' => '9123456789'],
            ['name' => "Anu's Parent", 'preferred_locale' => 'ml', 'large_text_mode' => true],
        );
        $parent->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'parent', 'is_active' => true],
        ]);

        WhatsAppOptIn::query()->firstOrCreate(
            ['user_id' => $parent->id, 'school_id' => $school->id],
            ['opted_in' => true, 'opted_in_at' => now()],
        );

        $student = Student::query()->firstOrCreate(
            ['school_id' => $school->id, 'admission_number' => 'STMS-2025-042'],
            [
                'name' => 'Anu',
                'school_class_id' => $class3->id,
                'section_id' => $sectionB->id,
                'status' => 'active',
            ],
        );

        $parent->children()->syncWithoutDetaching([
            $student->id => ['relationship' => 'mother', 'is_primary' => true],
        ]);

        Notice::query()->updateOrCreate(
            ['school_id' => $school->id, 'magic_link_token' => 'demo123abc'],
            [
                'author_id' => $admin->id,
                'title' => 'നാളെ സ്കൂൾ അവധി (മഴ)',
                'body' => 'അത്യാവശ്യ അറിയിപ്പ്: നാളെ മഴ കാരണം സ്കൂൾ അവധിയാണ്.',
                'title_en' => 'School closed tomorrow (rain)',
                'body_en' => 'Urgent: School closed tomorrow due to rain.',
                'priority' => 'urgent',
                'audience_type' => 'whole_school',
                'status' => 'published',
                'published_at' => now(),
                'pinned_until' => now()->addDays(2),
            ],
        );

        Notice::query()->firstOrCreate(
            [
                'school_id' => $school->id,
                'title' => 'പരീക്ഷാ ടൈംടേബിൾ',
            ],
            [
                'author_id' => $admin->id,
                'body' => 'മാർച്ച് മാസ പരീക്ഷാ ടൈംടേബിൾ പ്രസിദ്ധീകരിച്ചു.',
                'title_en' => 'Exam timetable',
                'body_en' => 'March exam timetable published.',
                'priority' => 'normal',
                'audience_type' => 'class',
                'status' => 'published',
                'published_at' => now()->subDay(),
                'magic_link_token' => 'exam2025mar',
            ],
        );

        app(CalendarService::class)->seedKeralaHolidays($school, (int) date('Y'));

        CalendarEvent::query()->firstOrCreate(
            [
                'school_id' => $school->id,
                'title' => 'രക്ഷിതാവ് സമ്മേളനം',
                'starts_at' => now()->addDay()->setTime(10, 0),
            ],
            [
                'title_en' => 'Parent meeting',
                'event_type' => 'ptm',
                'school_class_id' => $class3->id,
                'section_id' => $sectionB->id,
                'audience_type' => 'section',
            ],
        );

        $thread = FeedbackThread::query()->firstOrCreate(
            [
                'school_id' => $school->id,
                'created_by' => $parent->id,
                'subject' => 'ഹോംവർക്ക് സംശയം',
            ],
            [
                'student_id' => $student->id,
                'assigned_to' => $teacher->id,
                'category' => 'homework',
                'direction' => 'parent_to_teacher',
                'status' => 'acknowledged',
            ],
        );

        FeedbackMessage::query()->firstOrCreate(
            ['feedback_thread_id' => $thread->id, 'author_id' => $parent->id],
            ['body' => 'ഗണിത ഹോംവർക്കിലെ 5ാം ചോദ്യം മനസ്സിലാവുന്നില്ല.'],
        );

        $smcUser = User::query()->firstOrCreate(
            ['phone' => '9847012345'],
            ['name' => 'Rajesh Kumar', 'preferred_locale' => 'ml'],
        );
        $smcUser->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'smc_member', 'is_active' => true],
        ]);

        SmcMember::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Rajesh Kumar', 'role' => 'parent_rep'],
            ['phone' => '9847012345', 'user_id' => $smcUser->id, 'is_active' => true],
        );

        SmcMeeting::query()->firstOrCreate(
            ['school_id' => $school->id, 'title' => 'SMC Monthly Meeting - June'],
            [
                'scheduled_at' => now()->addDays(7)->setTime(15, 0),
                'agenda' => 'Compound wall repair, library books',
                'author_id' => $admin->id,
            ],
        );

        SmcDevelopmentItem::query()->firstOrCreate(
            ['school_id' => $school->id, 'title' => 'Compound wall repair'],
            ['status' => 'in_progress', 'description' => 'Eastern wall repair in progress'],
        );

        $malayalam = Subject::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Malayalam'],
            ['code' => 'ML'],
        );

        Homework::query()->firstOrCreate(
            ['school_id' => $school->id, 'title' => 'അക്ഷരമാല പാഠം 5'],
            [
                'school_class_id' => $class3->id,
                'section_id' => $sectionB->id,
                'subject_id' => $malayalam->id,
                'teacher_id' => $teacher->id,
                'description' => 'പാഠം 5 ചോദ്യോത്തരങ്ങൾ എഴുതുക',
                'due_date' => now()->addDays(3)->toDateString(),
            ],
        );

        AttendanceRecord::query()->firstOrCreate(
            ['student_id' => $student->id, 'date' => now()->toDateString()],
            ['school_id' => $school->id, 'marked_by' => $teacher->id, 'status' => 'present'],
        );

        TimetableSlot::query()->firstOrCreate(
            [
                'school_class_id' => $class3->id,
                'day_of_week' => Carbon::now()->dayOfWeekIso,
                'starts_at' => '09:00:00',
            ],
            [
                'school_id' => $school->id,
                'section_id' => $sectionB->id,
                'subject_id' => $malayalam->id,
                'teacher_id' => $teacher->id,
                'ends_at' => '09:40:00',
            ],
        );

        $bank = QuestionBank::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Class 3 Malayalam Quiz'],
            ['subject_id' => $malayalam->id, 'created_by' => $teacher->id],
        );

        $q1 = Question::query()->firstOrCreate(
            ['question_bank_id' => $bank->id, 'body' => 'കേരളത്തിന്റെ തലസ്ഥാനം?'],
            ['type' => 'mcq', 'options' => ['കൊച്ചി', 'തിരുവനന്തപുരം', 'കോഴിക്കോട്'], 'correct_answer' => 'തിരുവനന്തപുരം', 'marks' => 1],
        );

        $exam = Exam::query()->firstOrCreate(
            ['school_id' => $school->id, 'title' => 'Malayalam Weekly Quiz'],
            [
                'school_class_id' => $class3->id,
                'section_id' => $sectionB->id,
                'subject_id' => $malayalam->id,
                'created_by' => $teacher->id,
                'duration_minutes' => 30,
                'opens_at' => now(),
                'closes_at' => now()->addWeek(),
                'status' => 'published',
            ],
        );
        $exam->questions()->syncWithoutDetaching([$q1->id => ['sort_order' => 0]]);

        $vehicle = Vehicle::query()->firstOrCreate(
            ['school_id' => $school->id, 'registration_number' => 'KL-07-AB-1234'],
            ['name' => 'Bus 1', 'capacity' => 40, 'is_active' => true],
        );

        $driver = User::query()->firstOrCreate(
            ['phone' => '9111222333'],
            ['name' => 'Bus Driver Rajan', 'preferred_locale' => 'ml'],
        );
        $driver->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'transport_staff', 'is_active' => true],
        ]);
        $vehicle->update(['driver_user_id' => $driver->id]);

        $route = Route::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Route A - Perumbavoor'],
            ['vehicle_id' => $vehicle->id, 'direction' => 'both', 'default_start_time' => '07:30:00'],
        );

        $stop = RouteStop::query()->firstOrCreate(
            ['route_id' => $route->id, 'name' => 'Market Junction'],
            ['sort_order' => 1, 'scheduled_time' => '07:45:00'],
        );

        StudentRouteAssignment::query()->firstOrCreate(
            ['student_id' => $student->id, 'route_id' => $route->id],
            ['route_stop_id' => $stop->id],
        );

        $alumniUser = User::query()->firstOrCreate(
            ['phone' => '9988776655'],
            ['name' => 'Priya Alumni', 'preferred_locale' => 'ml'],
        );
        $alumniUser->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'alumni', 'is_active' => true],
        ]);

        AlumniProfile::query()->firstOrCreate(
            ['school_id' => $school->id, 'user_id' => $alumniUser->id],
            ['batch_year' => 2010, 'current_city' => 'Kochi', 'current_job' => 'Software Engineer', 'is_public' => true],
        );

        AlumniEvent::query()->firstOrCreate(
            ['school_id' => $school->id, 'title' => 'Class of 2010 Reunion'],
            ['starts_at' => now()->addMonths(2), 'location' => 'School Auditorium', 'batch_year' => 2010],
        );

        JobPosting::query()->firstOrCreate(
            ['school_id' => $school->id, 'title' => 'Junior Developer Intern'],
            [
                'posted_by' => $alumniUser->id,
                'description' => 'IT company in Kochi looking for interns.',
                'company' => 'Tech Kerala Pvt Ltd',
                'location' => 'Kochi',
            ],
        );

        DonationCampaign::query()->firstOrCreate(
            ['school_id' => $school->id, 'title' => 'Library Books Fund'],
            [
                'description' => 'Help us buy new library books',
                'goal_amount' => 50000,
                'raised_amount' => 12500,
                'is_active' => true,
            ],
        );
    }
}
