<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Notifications', [
            'preferences' => $request->user()->notificationPreferences(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = array_map(fn () => ['required', 'boolean'], User::NOTIFICATION_DEFAULTS);
        $request->user()->update(['notification_preferences' => array_map('boolval', $request->validate($rules))]);

        return back();
    }
}
