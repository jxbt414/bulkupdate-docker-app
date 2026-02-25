<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rollback extends Model
{
    protected $fillable = [
        'line_item_id',
        'previous_data',
        'rollback_timestamp'
    ];

    protected $casts = [
        'previous_data' => 'array',
        'rollback_timestamp' => 'datetime'
    ];

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(LineItem::class, 'line_item_id', 'line_item_id');
    }
} 