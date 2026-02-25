<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'line_item_id',
        'status',
        'error_message',
        'batch_id',
        'type',
        'data'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'data' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(LineItem::class, 'line_item_id', 'line_item_id');
    }
} 