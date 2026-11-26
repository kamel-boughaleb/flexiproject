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
            'digestTime' => $request->user()->digestTime(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = array_map(fn () => ['required', 'boolean'], User::NOTIFICATION_DEFAULTS);
        $data = $request->validate([...$rules, 'digest_time' => ['sometimes', 'required', 'date_format:H:i']]);

        $user = $request->user();
        $user->notification_preferences = array_map('boolval', array_intersect_key($data, User::NOTIFICATION_DEFAULTS));
        if (isset($data['digest_time'])) {
            $user->digest_time = $data['digest_time'] === User::DIGEST_DEFAULT_TIME ? null : $data['digest_time'];
        }
        $user->save();

        return back();
    }
}
