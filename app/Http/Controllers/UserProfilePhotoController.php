<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

class UserProfilePhotoController extends Controller
{
    public function show(User $user): Response
    {
        abort_unless($user->hasProfilePhoto(), 404);

        $content = $user->profilePhotoContent();

        abort_if($content === null, 404);

        return response($content, 200, [
            'Content-Type' => $user->profile_photo_mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) ($user->profile_photo_size ?? strlen($content)),
            'Content-Disposition' => 'inline; filename="'.$user->profilePhotoDownloadName().'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
