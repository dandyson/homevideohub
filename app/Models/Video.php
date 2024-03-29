<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'user_id',
        'youtube_url',
        'cover_image',
    ];

    /**
     * Get the people associated with the video.
     */
    public function people()
    {
        return $this->belongsToMany(Person::class);
    }

    /**
     * Get the locations associated with the video.
     */
    public function locations()
    {
        return $this->belongsToMany(Location::class);
    }
}
