<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ImageSanitizer;
use Illuminate\Http\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request, ImageSanitizer $sanitizer): RedirectResponse
    {
        $user = $request->user();
        $data = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email') ?: null,
            'notify_email' => $request->boolean('notify_email'),
            'notify_whatsapp' => $request->boolean('notify_whatsapp'),
        ];

        if ($request->hasFile('avatar')) {
            // Re-encoded like listing images: real image, no EXIF/GPS, generated file name.
            $clean = $sanitizer->sanitize($request->file('avatar'));
            $path = Storage::disk('public')->putFileAs('avatars', new File($clean['path']), Str::random(32).'.'.$clean['extension']);
            @unlink($clean['path']);

            $this->deleteAvatar($user->avatar);
            $data['avatar'] = $path;
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteAvatar($user->avatar);
            $data['avatar'] = null;
        }

        $user->update($data);

        return redirect()->route('profile.edit')->with('success', __('app.profile.updated'));
    }

    private function deleteAvatar(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
