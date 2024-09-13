<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Log;

class AvatarService
{
    private $defaultAvatar = 'https://images.unsplash.com/photo-1550399504-8953e1a6ac87?q=80&w=2829&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D';

    public function handleAvatarUpload(Request $request, User|Person|null $person = null)
    {
        $request->validate([
            'avatar' => [
                'required',
                File::types(['jpeg', 'png', 'jpg'])
                    ->max(12 * 1024),
            ],
        ]);

        if ($person->avatar_upload_count >= 10) {
            abort(400, 'Error: Upload limit reached for this person.');
        }

        if ($person instanceof User) {
            $path = "avatars/users/{$person->id} - {$person->name}";
        } elseif ($person instanceof Person) {
            $path = "avatars/people/{$person->id} - {$person->name}";
        } else {
            // Default path
            $path = "avatars/default/{$person->id}";
        }

        $name = $request->file('avatar')->getClientOriginalName();

        try {
            $request->file('avatar')->storeAs(
                $path,
                $name,
                's3'
            );

            DB::transaction(function () use ($person, $path, $name) {
                $person->avatar = Storage::disk('s3')->url("{$path}/{$name}");
                $person->avatar_upload_count++;
                $person->save();
            });

            return response()->json([
                'message' => 'Image uploaded successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error uploading cover image: ' . $e->getMessage());

            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
