<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'autoRollback' => 'required|boolean',
            'notifyOnError' => 'required|boolean',
            'batchSize' => 'required|integer|min:1|max:50',
            'retryAttempts' => 'required|integer|min:0|max:5'
        ]);

        // Store settings in cache
        Cache::put('line_item_settings', $validated, now()->addYear());

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => $validated
        ]);
    }

    public function show(): JsonResponse
    {
        $settings = Cache::get('line_item_settings', [
            'autoRollback' => true,
            'notifyOnError' => true,
            'batchSize' => 10,
            'retryAttempts' => 3
        ]);

        return response()->json($settings);
    }
} 