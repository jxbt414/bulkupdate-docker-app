<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LineItem extends Model
{
    protected $fillable = [
        'line_item_id',
        'line_item_name',
        'budget',
        'priority',
        'impression_goals',
        'targeting',
        'labels'
    ];

    protected $casts = [
        'impression_goals' => 'array',
        'targeting' => 'array',
        'labels' => 'array',
        'budget' => 'float',
        'priority' => 'integer'
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(Log::class, 'line_item_id', 'line_item_id');
    }

    public function rollbacks(): HasMany
    {
        return $this->hasMany(Rollback::class, 'line_item_id', 'line_item_id');
    }
} 