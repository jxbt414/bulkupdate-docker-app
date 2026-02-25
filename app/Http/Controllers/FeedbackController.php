<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FeedbackController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $feedback = Feedback::with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    $screenshotUrl = null;
                    if ($item->screenshot_path) {
                        try {
                            // Check if file exists
                            if (Storage::disk('public')->exists($item->screenshot_path)) {
                                $screenshotUrl = asset('storage/' . $item->screenshot_path);
                                
                                Log::info('Processing feedback screenshot:', [
                                    'id' => $item->id,
                                    'path' => $item->screenshot_path,
                                    'url' => $screenshotUrl,
                                    'exists' => true,
                                    'full_path' => Storage::disk('public')->path($item->screenshot_path)
                                ]);
                            } else {
                                Log::warning('Screenshot file not found:', [
                                    'id' => $item->id,
                                    'path' => $item->screenshot_path
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Error processing screenshot:', [
                                'id' => $item->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    return [
                        'id' => $item->id,
                        'message' => $item->message,
                        'type' => $item->type,
                        'screenshot_url' => $screenshotUrl,
                        'page_url' => $item->page_url,
                        'user_name' => $item->user->name,
                        'created_at' => $item->created_at->setTimezone('Australia/Sydney')->format('Y-m-d H:i:s'),
                        'user_agent' => $item->user_agent,
                    ];
                });

            return response()->json($feedback);
        } catch (\Exception $e) {
            Log::error('Failed to fetch feedback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string',
                'screenshot' => 'nullable|string', // Base64 encoded image
                'type' => 'required|string|in:user,bot'
            ]);

            Log::info('Received feedback request:', [
                'has_screenshot' => !empty($validated['screenshot']),
                'message' => $validated['message'],
                'type' => $validated['type']
            ]);

            // If there's a screenshot, store it
            $screenshotPath = null;
            if (!empty($validated['screenshot'])) {
                try {
                    // Remove the data:image/png;base64, part
                    $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $validated['screenshot']);
                    $imageData = base64_decode($base64Image);
                    
                    if ($imageData === false) {
                        throw new \Exception('Failed to decode screenshot data');
                    }

                    // Generate unique filename
                    $filename = 'screenshot_' . time() . '_' . uniqid() . '.png';
                    $relativePath = 'feedback/screenshots/' . $filename;

                    // Log the paths we're using
                    Log::info('Screenshot paths:', [
                        'filename' => $filename,
                        'relativePath' => $relativePath,
                        'storage_path' => storage_path('app/public/' . $relativePath),
                        'public_path' => public_path('storage/' . $relativePath)
                    ]);

                    // Ensure directory exists
                    $directory = storage_path('app/public/feedback/screenshots');
                    if (!file_exists($directory)) {
                        if (!mkdir($directory, 0775, true)) {
                            throw new \Exception("Failed to create directory: {$directory}");
                        }
                        Log::info("Created directory: {$directory}");
                    }

                    // Store the image using the public disk
                    $stored = Storage::disk('public')->put($relativePath, $imageData);
                    
                    if (!$stored) {
                        throw new \Exception('Failed to store screenshot');
                    }

                    // Set the path to be stored in the database
                    $screenshotPath = $relativePath;

                    // Verify file exists
                    if (!Storage::disk('public')->exists($relativePath)) {
                        throw new \Exception('Screenshot file not found after storage');
                    }

                    // Get the full URL
                    $url = Storage::disk('public')->url($relativePath);

                    Log::info('Successfully stored screenshot:', [
                        'filename' => $filename,
                        'relativePath' => $relativePath,
                        'exists' => Storage::disk('public')->exists($relativePath),
                        'full_path' => Storage::disk('public')->path($relativePath),
                        'url' => $url,
                        'permissions' => substr(sprintf('%o', fileperms($directory)), -4)
                    ]);

                } catch (\Exception $e) {
                    Log::error('Screenshot storage failed:', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw new \Exception('Failed to store screenshot: ' . $e->getMessage());
                }
            }

            // Create feedback record
            $feedback = Feedback::create([
                'user_id' => Auth::id(),
                'message' => $validated['message'],
                'screenshot_path' => $screenshotPath,
                'type' => $validated['type'],
                'page_url' => $request->header('Referer'),
                'user_agent' => $request->header('User-Agent')
            ]);

            Log::info('Feedback submitted', [
                'feedback_id' => $feedback->id,
                'user_id' => Auth::id(),
                'screenshot_path' => $feedback->screenshot_path,
                'storage_url' => $feedback->screenshot_path ? Storage::disk('public')->url($feedback->screenshot_path) : null
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback submitted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store feedback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit feedback: ' . $e->getMessage()
            ], 500);
        }
    }
} 