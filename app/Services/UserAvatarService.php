<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserAvatarService
{
    public static function url(User $user): ?string
    {
        if (! $user->profile_photo_path) {
            return null;
        }

        if (! Storage::disk('public')->exists($user->profile_photo_path)) {
            return null;
        }

        $v = $user->profile_updated_at?->timestamp ?? time();

        return asset('storage/' . $user->profile_photo_path) . '?v=' . $v;
    }

    public function store(User $user, UploadedFile $file): string
    {
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        return $file->store('user-avatars', 'public');
    }

    public function remove(User $user): void
    {
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->profile_photo_path = null;
    }
}