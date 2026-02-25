<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AudienceSegmentService;
use App\Services\GoogleAdManagerService;
use Illuminate\Support\Facades\Log;

class AudienceSegmentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AudienceSegmentService::class, function ($app) {
            try {
                Log::info('Creating AudienceSegmentService');
                
                // Get the GoogleAdManagerService instance
                $googleAdManagerService = $app->make(GoogleAdManagerService::class);
                
                // Get the session from the GoogleAdManagerService
                $session = $googleAdManagerService->getSession();
                
                // Create and return the service
                return new AudienceSegmentService($session);
            } catch (\Exception $e) {
                Log::error('Failed to create AudienceSegmentService: ' . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
} 