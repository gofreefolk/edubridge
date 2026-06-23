<?php

use App\Http\Controllers\Admin\SchoolSetupController;
use App\Http\Controllers\Alumni\AlumniController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\Calendar\CalendarController;
use App\Http\Controllers\Exam\ExamController;
use App\Http\Controllers\Feedback\FeedbackController;
use App\Http\Controllers\Notice\MagicLinkNoticeController;
use App\Http\Controllers\Notice\NoticeController;
use App\Http\Controllers\Parent\ParentDashboardController;
use App\Http\Controllers\Smc\SmcController;
use App\Http\Controllers\Student\StudentPortalController;
use App\Http\Controllers\Teacher\TeacherWorkspaceController;
use App\Http\Controllers\Transport\TransportController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::post('auth/otp/request', [OtpAuthController::class, 'requestOtp']);
    Route::post('auth/otp/verify', [OtpAuthController::class, 'verifyOtp']);

    Route::get('admin/import/sample.csv', [SchoolSetupController::class, 'downloadSample']);

    Route::middleware('auth')->group(function () {
        Route::get('auth/me', [OtpAuthController::class, 'me']);
        Route::post('auth/logout', [OtpAuthController::class, 'logout']);
        Route::get('notices/magic/{token}', [MagicLinkNoticeController::class, 'show']);
        Route::post('notices/magic/{token}/read', [MagicLinkNoticeController::class, 'markRead']);

        Route::middleware('role:parent,grandparent,school_admin,teacher,smc_member,student,alumni,super_admin')->group(function () {
            Route::get('notices', [NoticeController::class, 'index']);
            Route::get('notices/{notice}', [NoticeController::class, 'show']);
            Route::get('calendar', [CalendarController::class, 'index']);
        });

        Route::middleware('role:parent,grandparent,school_admin,teacher,smc_member,super_admin')->group(function () {
            Route::get('feedback', [FeedbackController::class, 'index']);
            Route::post('feedback/{thread}/reply', [FeedbackController::class, 'reply']);
            Route::post('feedback/{thread}/resolve', [FeedbackController::class, 'resolve']);
        });

        Route::middleware('role:parent,grandparent')->group(function () {
            Route::get('parent/dashboard', [ParentDashboardController::class, 'show']);
            Route::post('feedback', [FeedbackController::class, 'store']);
            Route::get('student/dashboard', [StudentPortalController::class, 'dashboard']);
            Route::get('transport/student', [TransportController::class, 'studentStatus']);
            Route::post('transport/absence', [TransportController::class, 'reportAbsence']);
        });

        Route::middleware('role:school_admin,super_admin')->group(function () {
            Route::get('admin/classes', [SchoolSetupController::class, 'classes']);
            Route::post('notices', [NoticeController::class, 'store']);
            Route::put('notices/{notice}', [NoticeController::class, 'update']);
            Route::post('notices/{notice}/publish', [NoticeController::class, 'publish']);
            Route::post('calendar', [CalendarController::class, 'store']);
            Route::post('smc/meetings', [SmcController::class, 'storeMeeting']);
            Route::put('smc/meetings/{meeting}/minutes', [SmcController::class, 'updateMeetingMinutes']);
            Route::post('admin/import/parents', [SchoolSetupController::class, 'importParents']);
        });

        Route::middleware('role:teacher,school_admin,super_admin')->group(function () {
            Route::get('teacher/roster', [TeacherWorkspaceController::class, 'classRoster']);
            Route::post('teacher/attendance', [TeacherWorkspaceController::class, 'markAttendance']);
            Route::post('teacher/homework', [TeacherWorkspaceController::class, 'assignHomework']);
            Route::post('teacher/marks', [TeacherWorkspaceController::class, 'enterMarks']);
            Route::post('feedback', [FeedbackController::class, 'store']);
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

        Route::middleware('role:student')->group(function () {
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
    });
});

Route::view('/{any?}', 'app')->where('any', '.*');
