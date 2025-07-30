<?php

use App\Http\Controllers\AutoApplyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\JobAlertController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Public routes
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{slug}', [JobController::class, 'show'])->name('jobs.show');
Route::get('/search', [SearchController::class, 'search'])->name('search.index');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Job Seeker routes
    Route::middleware(['role:job_seeker'])->prefix('job-seeker')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'jobSeeker'])->name('job-seeker.dashboard');
        Route::get('/job-alerts/create', [JobAlertController::class, 'create'])->name('job-alerts.create');
        Route::post('/job-alerts', [JobAlertController::class, 'store'])->name('job-alerts.store');
        Route::delete('/job-alerts/{jobAlert}', [JobAlertController::class, 'destroy'])->name('job-alerts.destroy');
    });

    // Employer/Company routes
    Route::middleware(['role:employer'])->prefix('company')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'employer'])->name('company.dashboard');
        Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
        Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
        Route::get('/jobs/{job}/edit', [JobController::class, 'edit'])->name('jobs.edit');
        Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::put('/jobs/{job}/toggle-open', [JobController::class, 'toggleOpen'])->name('jobs.toggle-open');
        Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
        Route::get('/jobs/{job}/feature', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/jobs/{job}/feature', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    });

    // Admin routes
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
        Route::get('/jobs/pending', [AdminController::class, 'pendingJobs'])->name('admin.jobs.pending');
        Route::post('/jobs/{job}/approve', [AdminController::class, 'approveJob'])->name('admin.jobs.approve');
        Route::get('/jobs/{job}/reject', [AdminController::class, 'showRejectJob'])->name('admin.jobs.show-reject');
        Route::post('/jobs/{job}/reject', [AdminController::class, 'rejectJob'])->name('admin.jobs.reject');
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users.index');
        Route::put('/users/{user}/ban', [AdminController::class, 'banUser'])->name('admin.users.ban');
    });

    // Other authenticated routes
    Route::get('/profile', [UserController::class, 'profile'])->name('profile.show');
    Route::get('/profile/edit', [UserController::class, 'editProfile'])->name('profile.edit');
    Route::post('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::get('/profile/skills', [UserController::class, 'editSkills'])->name('profile.skills');
    Route::post('/skills', [UserController::class, 'addSkills'])->name('skills.add');
    Route::delete('/skills', [UserController::class, 'removeSkills'])->name('skills.remove');

    Route::get('/jobs/{job}/apply', [ApplicationController::class, 'create'])->name('applications.create');
    Route::post('/jobs/{job}/apply', [ApplicationController::class, 'store'])->name('applications.store');
    Route::put('/applications/{application}/status', [ApplicationController::class, 'updateStatus'])->name('applications.update-status');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('/notifications/{notification}', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/subscribe', [SubscriptionController::class, 'show'])->name('subscribe');
    Route::post('/subscribe', [SubscriptionController::class, 'create'])->name('subscribe.create');

    Route::middleware(['premium', 'role:job_seeker'])->group(function () {
        Route::get('/auto-apply', [AutoApplyController::class, 'index'])->name('auto.apply');
        Route::post('/auto-apply/update', [AutoApplyController::class, 'update'])->name('auto.apply.update');
        Route::get('/auto-apply/toggle', [AutoApplyController::class, 'toggle'])->name('auto.apply.toggle');
    });
});

require __DIR__ . '/auth.php';
