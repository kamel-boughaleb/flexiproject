<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MyTasksController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\TaskController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('my-tasks', MyTasksController::class)->name('my-tasks');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');

    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', fn (Project $project) => to_route('projects.view', [$project, 'board']))->name('projects.show');
    Route::get('projects/{project}/rules', [ProjectController::class, 'rules'])->name('projects.rules');
    Route::get('projects/{project}/members', [ProjectController::class, 'members'])->name('projects.members');
    Route::get('projects/{project}/settings', [ProjectController::class, 'settings'])->name('projects.settings');
    Route::get('projects/{project}/{view}', [ProjectController::class, 'show'])->whereIn('view', ProjectController::VIEWS)->name('projects.view');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::post('projects/{project}/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::patch('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::post('tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');
    Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->name('comments.store');

    Route::post('projects/{project}/members', [MemberController::class, 'store'])->name('members.store');
    Route::patch('projects/{project}/members/{user}', [MemberController::class, 'update'])->name('members.update');
    Route::delete('projects/{project}/members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');

    Route::post('projects/{project}/rules', [RuleController::class, 'store'])->name('rules.store');
    Route::patch('rules/{rule}', [RuleController::class, 'update'])->name('rules.update');
    Route::delete('rules/{rule}', [RuleController::class, 'destroy'])->name('rules.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
