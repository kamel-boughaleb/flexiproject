<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Photo de profil de l'utilisateur connecté. */
class PhotoController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'max:5120']]);
        $request->user()->setPhoto($request->file('photo'));

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->removePhoto();

        return back();
    }
}
