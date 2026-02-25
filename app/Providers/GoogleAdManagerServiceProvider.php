<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GoogleAdManagerService;
use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\Common\Configuration;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Log;

class GoogleAdManagerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleAdManagerService::class, function ($app) {
            try {
                Log::info('Creating AdManagerSession for GoogleAdManagerService');
                
                // Load service account credentials
                $jsonKeyPath = '/Users/mcnuser/Desktop/scripts/gam-api-test-339711-d8244e01556a.json';
                Log::info("Loading service account from: {$jsonKeyPath}");
                
                if (!file_exists($jsonKeyPath)) {
                    throw new \Exception("Service account JSON key file not found at: {$jsonKeyPath}");
                }

                $jsonKey = json_decode(file_get_contents($jsonKeyPath), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Failed to parse JSON key file: " . json_last_error_msg());
                }
                
                // Load and parse adsapi_php.ini
                $iniPath = '/Users/mcnuser/Desktop/scripts/adsapi_php.ini';
                Log::info("Loading adsapi_php.ini from: {$iniPath}");
                
                if (!file_exists($iniPath)) {
                    throw new \Exception("adsapi_php.ini file not found at: {$iniPath}");
                }
                
                $iniConfig = parse_ini_file($iniPath, true);
                if ($iniConfig === false) {
                    throw new \Exception("Failed to parse adsapi_php.ini file");
                }
                
                // Override network code and application name
                $networkCode = '21780812979';
                $applicationName = 'Bulk Update Tool';
                
                $iniConfig['AD_MANAGER']['networkCode'] = $networkCode;
                $iniConfig['AD_MANAGER']['applicationName'] = $applicationName;
                
                // Add SOAP logging configuration
                $iniConfig['LOGGING']['soapLogFilePath'] = storage_path('logs/soap.log');
                $iniConfig['LOGGING']['soapLogEnabled'] = 'true';
                
                // Create configuration
                $config = new Configuration($iniConfig);
                Log::info("Created configuration with network code: " . $networkCode);

                // Create service account credentials
                $oauth2Credential = new ServiceAccountCredentials(
                    'https://www.googleapis.com/auth/dfp',
                    $jsonKey
                );
                
                Log::info("Created OAuth2 credentials");

                // Create session
                $session = (new AdManagerSessionBuilder())
                    ->from($config)
                    ->withOAuth2Credential($oauth2Credential)
                    ->build();
                
                Log::info("Built AdManager session");
                
                // Create and return the service
                return new GoogleAdManagerService($session);
            } catch (\Exception $e) {
                Log::error('Failed to create GoogleAdManagerService: ' . $e->getMessage(), [
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