<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait LineItemLocking
{
    private function getLineItemLockKey(string $lineItemId): string
    {
        return "line_item_lock_{$lineItemId}";
    }

    private function acquireLock(string $lineItemId, int $userId, int $timeout = 300): bool
    {
        $lockKey = $this->getLineItemLockKey($lineItemId);
        
        // Try to acquire the lock
        if (Cache::add($lockKey, $userId, $timeout)) {
            Log::info("Lock acquired for line item {$lineItemId} by user {$userId}");
            return true;
        }

        // If lock exists, check if it's stale (older than 5 minutes)
        $lockTime = Cache::get("{$lockKey}_time");
        if ($lockTime && now()->diffInSeconds($lockTime) > $timeout) {
            Cache::forget($lockKey);
            Cache::forget("{$lockKey}_time");
            
            // Try to acquire the lock again
            if (Cache::add($lockKey, $userId, $timeout)) {
                Log::info("Stale lock cleared and new lock acquired for line item {$lineItemId} by user {$userId}");
                return true;
            }
        }

        // Get the user who holds the lock
        $lockHolder = Cache::get($lockKey);
        Log::warning("Lock acquisition failed for line item {$lineItemId}. Currently held by user {$lockHolder}");
        
        return false;
    }

    private function releaseLock(string $lineItemId, int $userId): void
    {
        $lockKey = $this->getLineItemLockKey($lineItemId);
        
        // Only release if we own the lock
        if (Cache::get($lockKey) === $userId) {
            Cache::forget($lockKey);
            Cache::forget("{$lockKey}_time");
            Log::info("Lock released for line item {$lineItemId} by user {$userId}");
        }
    }

    private function isLocked(string $lineItemId): bool
    {
        return Cache::has($this->getLineItemLockKey($lineItemId));
    }

    private function getLockHolder(string $lineItemId): ?int
    {
        return Cache::get($this->getLineItemLockKey($lineItemId));
    }
} 