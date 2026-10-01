<?php

use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Platform\PlatformSchoolController;
use App\Http\Controllers\Platform\SchoolAdminInviteController;
use App\Http\Controllers\Platform\SchoolRegistrationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\SchoolClassManagementController;
use App\Http\Controllers\Admin\SchoolProfileController;
use App\Http\Controllers\Admin\SchoolSetupController;
use App\Http\Controllers\Admin\SchoolStaffController;
use App\Http\Controllers\Admin\SchoolStudentController;
use App\Http\Controllers\Alumni\AlumniController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\Calendar\CalendarController;
use App\Http\Controllers\Comms\NotificationSummaryController;
use App\Http\Controllers\Comms\WhatsAppOptInController;
use App\Http\Controllers\Exam\ExamController;
use App\Http\Controllers\Feedback\FeedbackController;
use App\Http\Controllers\Fees\FeeInvoiceController;
use App\Http\Controllers\Fees\FeeOnlinePaymentController;
use App\Http\Controllers\Fees\FeeReportController;
use App\Http\Controllers\Fees\FeeSetupController;
use App\Http\Controllers\Notice\MagicLinkNoticeController;
use App\Http\Controllers\Notice\NoticeController;
use App\Http\Controllers\Operations\AttendanceController;
use App\Http\Controllers\Operations\CentreLogController;
use App\Http\Controllers\Operations\ChecklistController;
use App\Http\Controllers\Operations\StudentProfileController;
use App\Http\Controllers\Parent\ParentDashboardController;
use App\Http\Controllers\Smc\SmcController;
use App\Http\Controllers\Student\StudentPortalController;
use App\Http\Controllers\Teacher\TeacherWorkspaceController;
use App\Http\Controllers\Transport\TransportController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::middleware('throttle:otp')->group(function () {
        Route::post('auth/otp/request', [OtpAuthController::class, 'requestOtp']);
        Route::post('auth/otp/verify', [OtpAuthController::class, 'verifyOtp']);
    });

    Route::post('schools/register', [SchoolRegistrationController::class, 'store'])->middleware('throttle:registration');
    Route::get('invites/admin/{token}', [SchoolAdminInviteController::class, 'show']);

    Route::get('admin/import/sample.csv', [SchoolSetupController::class, 'downloadSample']);

    // Online fee payment: signed by Razorpay, so no login (and no CSRF for the webhook).
    Route::post('fees/online/confirm', [FeeOnlinePaymentController::class, 'confirm'])->middleware('throttle:30,1');
    Route::post('webhooks/razorpay/{school}', [FeeOnlinePaymentController::class, 'webhook'])->middleware('throttle:120,1');

    Route::middleware('auth')->group(function () {
        Route::get('auth/me', [OtpAuthController::class, 'me']);
        Route::post('auth/logout', [OtpAuthController::class, 'logout']);
        Route::get('notices/magic/{token}', [MagicLinkNoticeController::class, 'show']);
        Route::post('notices/magic/{token}/read', [MagicLinkNoticeController::class, 'markRead']);
        Route::post('invites/admin/{token}/accept', [SchoolAdminInviteController::class, 'accept']);

        // School operations. Per-school / per-student access is checked in each controller.
        Route::get('students/{student}/profile', [StudentProfileController::class, 'show']);
        Route::put('students/{student}/profile', [StudentProfileController::class, 'update']);
        Route::post('students/{student}/contacts', [StudentProfileController::class, 'storeContact']);
        Route::put('students/{student}/contacts/{contact}', [StudentProfileController::class, 'updateContact']);
        Route::delete('students/{student}/contacts/{contact}', [StudentProfileController::class, 'destroyContact']);

        Route::get('attendance/sheet', [AttendanceController::class, 'sheet']);
        Route::get('attendance/report', [AttendanceController::class, 'report']);

        Route::get('centre-logs', [CentreLogController::class, 'index']);
        Route::post('centre-logs', [CentreLogController::class, 'store']);
        Route::delete('centre-logs/{log}', [CentreLogController::class, 'destroy']);

        Route::get('checklists', [ChecklistController::class, 'index']);
        Route::post('checklists', [ChecklistController::class, 'storeTemplate']);
        Route::put('checklists/{template}', [ChecklistController::class, 'updateTemplate']);
        Route::post('checklists/{template}/submit', [ChecklistController::class, 'submit']);
        Route::get('checklists/report', [ChecklistController::class, 'report']);

        // Fees visible to the office and the student's own family; checked in the controller.
        Route::get('fees/students/{student}', [FeeInvoiceController::class, 'ledger']);
        Route::get('fees/invoices/{invoice}', [FeeInvoiceController::class, 'show']);
        Route::get('fees/payments/{payment}/receipt', [FeeInvoiceController::class, 'receipt']);
        Route::post('fees/invoices/{invoice}/pay-link', [FeeInvoiceController::class, 'payLink'])->middleware('throttle:20,1');

        Route::middleware('role:parent,grandparent,school_admin,teacher,smc_member,student,alumni,super_admin')->group(function () {
            Route::get('notices', [NoticeController::class, 'index']);
            Route::get('notices/{notice}', [NoticeController::class, 'show']);
            Route::get('calendar', [CalendarController::class, 'index']);
        });

        Route::middleware('role:parent,grandparent,school_admin,teacher,smc_member,super_admin')->group(function () {
            Route::get('feedback', [FeedbackController::class, 'index']);
            Route::get('feedback/{thread}', [FeedbackController::class, 'show']);
            Route::post('feedback/{thread}/reply', [FeedbackController::class, 'reply']);
            Route::post('feedback/{thread}/resolve', [FeedbackController::class, 'resolve']);
            Route::get('comms/summary', [NotificationSummaryController::class, 'show']);
            Route::post('feedback', [FeedbackController::class, 'store']);
        });

        Route::middleware('role:parent,grandparent')->group(function () {
            Route::get('comms/whatsapp-opt-in', [WhatsAppOptInController::class, 'show']);
            Route::put('comms/whatsapp-opt-in', [WhatsAppOptInController::class, 'update']);
        });

        Route::middleware('role:parent,grandparent')->group(function () {
            Route::get('parent/dashboard', [ParentDashboardController::class, 'show']);
            Route::get('transport/student', [TransportController::class, 'studentStatus']);
            Route::post('transport/absence', [TransportController::class, 'reportAbsence']);
        });

        Route::middleware('role:school_admin,super_admin')->group(function () {
            Route::get('admin/dashboard', [AdminDashboardController::class, 'show']);
            Route::get('admin/school', [SchoolProfileController::class, 'show']);
            Route::put('admin/school', [SchoolProfileController::class, 'update']);
            Route::get('admin/invites', [SchoolProfileController::class, 'invites']);
            Route::post('admin/invites', [SchoolProfileController::class, 'storeInvite']);
            Route::get('admin/classes', [SchoolSetupController::class, 'classes']);
            Route::post('admin/classes', [SchoolClassManagementController::class, 'storeClass']);
            Route::put('admin/classes/{schoolClass}', [SchoolClassManagementController::class, 'updateClass']);
            Route::delete('admin/classes/{schoolClass}', [SchoolClassManagementController::class, 'destroyClass']);
            Route::post('admin/classes/{schoolClass}/sections', [SchoolClassManagementController::class, 'storeSection']);
            Route::put('admin/sections/{section}', [SchoolClassManagementController::class, 'updateSection']);
            Route::delete('admin/sections/{section}', [SchoolClassManagementController::class, 'destroySection']);
            Route::get('admin/staff', [SchoolStaffController::class, 'index']);
            Route::post('admin/staff', [SchoolStaffController::class, 'store']);
            Route::delete('admin/staff/{user}', [SchoolStaffController::class, 'destroy']);
            Route::get('admin/students', [SchoolStudentController::class, 'index']);
            Route::get('admin/students/{student}', [SchoolStudentController::class, 'show']);
            Route::put('admin/students/{student}', [SchoolStudentController::class, 'update']);
            Route::post('admin/students/{student}/parents', [SchoolStudentController::class, 'attachParent']);
            Route::put('admin/students/{student}/parents/{parent}', [SchoolStudentController::class, 'updateParent']);
            Route::delete('admin/students/{student}/parents/{parent}', [SchoolStudentController::class, 'detachParent']);
            Route::post('notices', [NoticeController::class, 'store']);
            Route::put('notices/{notice}', [NoticeController::class, 'update']);
            Route::post('notices/{notice}/publish', [NoticeController::class, 'publish']);
            Route::post('notices/{notice}/unpublish', [NoticeController::class, 'unpublish']);
            Route::get('notices/{notice}/stats', [NoticeController::class, 'stats']);
            Route::post('notices/{notice}/whatsapp', [NoticeController::class, 'sendWhatsApp']);
            Route::post('calendar', [CalendarController::class, 'store']);
            Route::post('smc/meetings', [SmcController::class, 'storeMeeting']);
            Route::put('smc/meetings/{meeting}/minutes', [SmcController::class, 'updateMeetingMinutes']);
            Route::post('admin/import/parents', [SchoolSetupController::class, 'importParents']);

            Route::get('fees/setup', [FeeSetupController::class, 'show']);
            Route::post('fees/heads', [FeeSetupController::class, 'storeHead']);
            Route::put('fees/heads/{head}', [FeeSetupController::class, 'updateHead']);
            Route::post('fees/structures', [FeeSetupController::class, 'storeStructure']);
            Route::put('fees/structures/{structure}', [FeeSetupController::class, 'updateStructure']);
            Route::delete('fees/structures/{structure}', [FeeSetupController::class, 'destroyStructure']);
            Route::put('fees/numbering', [FeeSetupController::class, 'updateNumbering']);
            Route::put('fees/reminders', [FeeSetupController::class, 'updateReminders']);
            Route::get('fees/gateway', [FeeOnlinePaymentController::class, 'gateway']);
            Route::put('fees/gateway', [FeeOnlinePaymentController::class, 'updateGateway']);
            Route::get('fees/students/{student}/concessions', [FeeSetupController::class, 'concessions']);
            Route::post('fees/students/{student}/concessions', [FeeSetupController::class, 'storeConcession']);
            Route::delete('fees/students/{student}/concessions/{concession}', [FeeSetupController::class, 'destroyConcession']);
            Route::get('fees/invoices', [FeeInvoiceController::class, 'index']);
            Route::post('fees/invoices/generate', [FeeInvoiceController::class, 'generate']);
            Route::post('fees/invoices/{invoice}/void', [FeeInvoiceController::class, 'void']);
            Route::post('fees/invoices/{invoice}/payments', [FeeInvoiceController::class, 'storePayment']);
            Route::post('fees/payments/{payment}/void', [FeeInvoiceController::class, 'voidPayment']);
            Route::get('fees/reports/collections', [FeeReportController::class, 'collections']);
            Route::get('fees/reports/outstanding', [FeeReportController::class, 'outstanding']);
        });

        Route::middleware('role:teacher,school_admin,super_admin')->group(function () {
            Route::get('teacher/roster', [TeacherWorkspaceController::class, 'classRoster']);
            Route::post('teacher/attendance', [TeacherWorkspaceController::class, 'markAttendance']);
            Route::post('teacher/homework', [TeacherWorkspaceController::class, 'assignHomework']);
            Route::post('teacher/marks', [TeacherWorkspaceController::class, 'enterMarks']);
            Route::get('exams', [ExamController::class, 'index']);
            Route::post('exams', [ExamController::class, 'store']);
            Route::post('exams/{exam}/publish', [ExamController::class, 'publish']);
            Route::post('question-banks', [ExamController::class, 'storeQuestionBank']);
            Route::post('questions', [ExamController::class, 'storeQuestion']);
        });

        Route::middleware('role:student,parent,grandparent')->group(function () {
            Route::post('exams/{exam}/attempts', [ExamController::class, 'startAttempt']);
            Route::post('exam-attempts/{attempt}/submit', [ExamController::class, 'submitAttempt']);
        });

        Route::middleware('role:smc_member,school_admin,parent,grandparent,super_admin')->group(function () {
            Route::get('smc/board', [SmcController::class, 'board']);
        });

        Route::middleware('role:student,parent,grandparent')->group(function () {
            Route::get('student/dashboard', [StudentPortalController::class, 'dashboard']);
        });

        Route::middleware('role:transport_staff,school_admin,super_admin')->group(function () {
            Route::get('transport', [TransportController::class, 'index']);
            Route::post('transport/trips', [TransportController::class, 'startTrip']);
            Route::post('transport/trips/{trip}/boarding', [TransportController::class, 'logBoarding']);
            Route::post('transport/trips/{trip}/delay', [TransportController::class, 'delayAlert']);
        });

        Route::middleware('role:alumni')->group(function () {
            Route::get('alumni/portal', [AlumniController::class, 'portal']);
            Route::put('alumni/profile', [AlumniController::class, 'updateProfile']);
            Route::post('alumni/mentorship', [AlumniController::class, 'requestMentorship']);
            Route::post('alumni/jobs', [AlumniController::class, 'postJob']);
        });

        Route::middleware('role:super_admin')->prefix('platform')->group(function () {
            Route::get('dashboard', [PlatformController::class, 'dashboard']);
            Route::get('schools', [PlatformController::class, 'schools']);
            Route::post('schools', [PlatformSchoolController::class, 'store']);
            Route::get('schools/{school}', [PlatformSchoolController::class, 'show']);
            Route::get('registrations', [PlatformSchoolController::class, 'registrations']);
            Route::post('schools/{school}/approve', [PlatformSchoolController::class, 'approve']);
            Route::post('schools/{school}/reject', [PlatformSchoolController::class, 'reject']);
            Route::post('schools/{school}/invites', [PlatformSchoolController::class, 'storeInvite']);
        });
    });
});

// PWA files are built into /build but served from the root, so the service worker's
// scope covers the whole app and its relative manifest entry resolves.
Route::get('/{file}', function (string $file) {
    $path = public_path('build/'.$file);
    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => $file === 'sw.js'
            ? 'application/javascript; charset=utf-8'
            : 'application/manifest+json',
        'Cache-Control' => 'no-cache',
    ]);
})->whereIn('file', ['sw.js', 'manifest.webmanifest']);

Route::view('/{any?}', 'app')->where('any', '.*');
