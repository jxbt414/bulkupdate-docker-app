<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CmsMetadataService;
use App\Services\GoogleAdManagerService;
use Illuminate\Support\Facades\Log;

class CmsMetadataServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CmsMetadataService::class, function ($app) {
            try {
                Log::info('Creating CmsMetadataService');
                
                // Get the GoogleAdManagerService instance
                $googleAdManagerService = $app->make(GoogleAdManagerService::class);
                
                // Get the session from the GoogleAdManagerService
                $session = $googleAdManagerService->getSession();
                
                // Create and return the service
                return new CmsMetadataService($session);
            } catch (\Exception $e) {
                Log::error('Failed to create CmsMetadataService: ' . $e->getMessage(), [
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