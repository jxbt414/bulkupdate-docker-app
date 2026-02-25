<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'screenshot_path',
        'type',
        'page_url',
        'user_agent'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 