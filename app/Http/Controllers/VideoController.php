<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\Rules\File;

class VideoController extends Controller
{

    private $defaultCoverImage = 'https://images.unsplash.com/photo-1550399504-8953e1a6ac87?q=80&w=2829&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D';
    
    public function index()
    {
        $videos = Video::all();
    }

    public function show(Video $video)
    {
        // Ensure the user can only view their own video
        if ($video->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
    
        $video->featured_users = collect($video->featured_users)->map(function ($userId) {
            $person = optional(Person::find($userId));
        
            return [
                'id'   => $person->id,
                'name' => $person->name,
                'family' => $person->family,
                'avatar' => $person->avatar,
            ];
        })->toArray();
    
        return Inertia::render('Video/VideoShow', [
            'video' => $video,
            'people' => Person::all(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Video/VideoDetails');
    }

    public function edit(Video $video)
    {
        $video->featured_users = collect($video->featured_users)->map(function ($userId) {
            $person = optional(Person::find($userId));
        
            return [
                'id'   => $person->id,
                'name' => $person->name,
                'family' => $person->family,
                'avatar' => $person->avatar,
            ];
        })->toArray();

        return Inertia::render('Video/VideoDetails', [
            'updateMode' => true,
            'video' => $video,
            'people' => Person::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'youtube_url' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{11}$/'],
            'featured_users' => 'nullable|array',
        ]);

        $video = new Video([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'youtube_url' => $request->input('youtube_url'),
            'featured_users' => array_column($request->input('featured_users'), 'id'),
            'user_id' => Auth::id(),
        ]);

        if ($request->cover_image) {
            $video->cover_image = $this->defaultCoverImage;
        }

        $video->save();

        return response()->json([
            'video' => $video->only('id', 'title', 'description', 'youtube_url', 'featured_users'),
            'message' => ['type' => 'Success', 'text' => 'Video created successfully'],
        ]);
    }

    public function update(Request $request, Video $video)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'youtube_url' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{11}$/'],
            'featured_users' => 'nullable|array',
        ]);
    
        // Update video with extracted YouTube video ID
        $video->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'youtube_url' => $request->input('youtube_url'),
            'featured_users' => array_column($request->input('featured_users'), 'id'),
        ]);
        $video->save();
        return Inertia::render('Video/VideoShow', [
            'video' => $video->only('id', 'title', 'description', 'youtube_url', 'featured_users'),
            'message' => ['type' => 'Success', 'text' => 'Video updated successfully'],
        ])->withViewData(['url' => route('video.show', ['video' => $video->id])]);
    }

    public function destroy(Video $video)
    {
        $video->delete();

        // Optionally, you can return a response or redirect
        return response()->json(['success', 'Video deleted successfully']);
    }

    public function handleCoverImageUpload(Request $request, Video $video = null)
    {
        $request->validate([
            'cover_image' => [
                'required',
                File::types(['jpeg', 'png', 'jpg'])
                    ->max(12 * 1024),
            ]
        ]);

        $path = "cover-images/{$video->id} - {$video->title}";
        $name = $request->file('cover_image')->getClientOriginalName();

        try {
            $url = Storage::disk('s3')->url("{$path}/{$name}");
            $video->cover_image = $url;
            $video->save();

            $request->file('cover_image')->storeAs(
                $path,
                $name,
                's3'
            );

            $video->cover_image = Storage::disk('s3')->url("{$path}/{$name}");
            $video->save();

            return response()->json([
                'message' => 'Image uploaded successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error uploading cover image: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
