<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorProject extends Model
{
    protected $fillable = [
        'user_id', 'title', 'role', 'year', 'description', 'url', 'image_path', 'position',
    ];

    protected $casts = [
        'id'       => 'integer',
        'user_id'  => 'integer',
        'year'     => 'integer',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
