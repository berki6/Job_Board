<?php

use App\Http\Controllers\AutoApplyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/subscribe', [SubscriptionController::class, 'show'])->name('subscribe');
    Route::post('/subscribe', [SubscriptionController::class, 'create'])->name('subscribe.create');

    Route::middleware(['premium'])->group(function () {
        Route::get('/auto-apply', [AutoApplyController::class, 'index'])->name('auto.apply');
        Route::post('/auto-apply/update', [AutoApplyController::class, 'update'])->name('auto.apply.update');
        Route::get('/auto-apply/toggle', [AutoApplyController::class, 'toggle'])->name('auto.apply.toggle');
    });
});

require __DIR__.'/auth.php';
