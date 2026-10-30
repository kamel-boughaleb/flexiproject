<?php

use App\Http\Controllers\Settings\NotificationSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PhotoController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('settings/photo', [PhotoController::class, 'update'])->name('photo.update');
    Route::delete('settings/photo', [PhotoController::class, 'destroy'])->name('photo.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/notifications', [NotificationSettingsController::class, 'edit'])->name('notifications.settings');
    Route::patch('settings/notifications', [NotificationSettingsController::class, 'update'])->name('notifications.settings.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});
