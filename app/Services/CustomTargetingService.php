<?php

namespace App\Services;

use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\v202411\CustomTargetingKeyType;
use Google\AdsApi\AdManager\v202411\CustomTargetingKey;
use Google\AdsApi\AdManager\v202411\CustomTargetingValue;
use Google\AdsApi\AdManager\v202411\Statement;
use Google\AdsApi\AdManager\v202411\ServiceFactory;
use Illuminate\Support\Facades\Log;

class CustomTargetingService
{
    /**
     * @var AdManagerSession
     */
    protected $session;

    /**
     * @var \Google\AdsApi\AdManager\v202411\CustomTargetingService
     */
    protected $service;

    /**
     * @var int
     */
    protected $maxRetries = 2;

    /**
     * CustomTargetingService constructor.
     *
     * @param AdManagerSession $session
     */
    public function __construct(AdManagerSession $session)
    {
        Log::info('Initializing CustomTargetingService');
        $this->session = $session;
        try {
            Log::info('Creating Google Ad Manager CustomTargetingService');
            $this->service = (new ServiceFactory())->createCustomTargetingService($this->session);
            Log::info('Successfully created Google Ad Manager CustomTargetingService');
        } catch (\Exception $e) {
            Log::error('Error creating CustomTargetingService: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get custom targeting keys based on search query with retry logic
     *
     * @param string $search
     * @param int $retryCount
     * @return array
     */
    public function getCustomTargetingKeys(string $search, int $retryCount = 0): array
    {
        try {
            Log::info('Creating statement for custom targeting keys search', [
                'search' => $search,
                'retry_count' => $retryCount
            ]);
            
            $statement = new Statement();
            
            if (!empty($search)) {
                $query = "WHERE status = 'ACTIVE' AND name LIKE '%" . $search . "%' LIMIT 10";
            } else {
                $query = "WHERE status = 'ACTIVE' LIMIT 10";
            }
            
            $statement->setQuery($query);
            Log::info('Fetching ACTIVE custom targeting keys with query: ' . $query);
            
            // Ensure service is initialized
            if (!$this->service) {
                Log::error('CustomTargetingService not initialized');
                
                // Try to reinitialize the service
                if ($retryCount < $this->maxRetries) {
                    Log::info('Attempting to reinitialize CustomTargetingService', ['retry' => $retryCount + 1]);
                    $this->service = (new ServiceFactory())->createCustomTargetingService($this->session);
                    return $this->getCustomTargetingKeys($search, $retryCount + 1);
                }
                
                throw new \Exception('CustomTargetingService not initialized after retry attempts');
            }
            
            // Make the API call
            Log::info('Making API call to getCustomTargetingKeysByStatement');
            $page = $this->service->getCustomTargetingKeysByStatement($statement);
            Log::info('API call to getCustomTargetingKeysByStatement completed');
            
            if ($page->getResults() !== null) {
                $count = count($page->getResults());
                Log::info("Found {$count} ACTIVE custom targeting keys");
                
                // Log each key for debugging
                foreach ($page->getResults() as $index => $key) {
                    Log::info("Key {$index}: " . $key->getName() . " (ID: " . $key->getId() . ")");
                }
                
                return $page->getResults();
            }
            
            Log::info('No ACTIVE custom targeting keys found');
            return [];
        } catch (\Exception $e) {
            Log::error('Error fetching custom targeting keys: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'search' => $search,
                'retry_count' => $retryCount
            ]);
            
            // Retry logic for certain exceptions
            if ($retryCount < $this->maxRetries) {
                $shouldRetry = false;
                
                // Check if it's a network error or authentication issue
                if (strpos($e->getMessage(), 'cURL error') !== false || 
                    strpos($e->getMessage(), 'authentication') !== false ||
                    strpos($e->getMessage(), 'session') !== false) {
                    $shouldRetry = true;
                }
                
                if ($shouldRetry) {
                    Log::info('Retrying getCustomTargetingKeys after error', ['retry' => $retryCount + 1]);
                    // Wait a bit before retrying (exponential backoff)
                    usleep(pow(2, $retryCount) * 100000); // 100ms, 200ms, 400ms, etc.
                    return $this->getCustomTargetingKeys($search, $retryCount + 1);
                }
            }
            
            throw $e; // Re-throw the exception to be handled by the controller
        }
    }

    /**
     * Get custom targeting values for a key based on search query with retry logic
     *
     * @param string $keyName
     * @param string $search
     * @param int $retryCount
     * @return array
     */
    public function getCustomTargetingValues(string $keyName, string $search, int $retryCount = 0): array
    {
        try {
            Log::info('Fetching ACTIVE custom targeting values', [
                'keyName' => $keyName,
                'search' => $search,
                'retry_count' => $retryCount
            ]);
            
            // First, find the key ID by name
            $keyStatement = new Statement();
            $keyQuery = "WHERE name = '" . $keyName . "' AND status = 'ACTIVE' LIMIT 1";
            $keyStatement->setQuery($keyQuery);
            
            Log::info('Fetching ACTIVE custom targeting key with query: ' . $keyQuery);
            
            // Ensure service is initialized
            if (!$this->service) {
                Log::error('CustomTargetingService not initialized');
                
                // Try to reinitialize the service
                if ($retryCount < $this->maxRetries) {
                    Log::info('Attempting to reinitialize CustomTargetingService', ['retry' => $retryCount + 1]);
                    $this->service = (new ServiceFactory())->createCustomTargetingService($this->session);
                    return $this->getCustomTargetingValues($keyName, $search, $retryCount + 1);
                }
                
                throw new \Exception('CustomTargetingService not initialized after retry attempts');
            }
            
            // Make the API call to get the key
            $keyPage = $this->service->getCustomTargetingKeysByStatement($keyStatement);
            
            if ($keyPage->getResults() === null || count($keyPage->getResults()) === 0) {
                Log::info('No ACTIVE custom targeting key found with name: ' . $keyName);
                return [];
            }
            
            $keyId = $keyPage->getResults()[0]->getId();
            Log::info('Found ACTIVE custom targeting key with ID: ' . $keyId);
            
            // Now get values for this key
            $valueStatement = new Statement();
            
            if (!empty($search)) {
                $valueQuery = "WHERE customTargetingKeyId = " . $keyId . " AND status = 'ACTIVE' AND name LIKE '%" . $search . "%' LIMIT 10";
            } else {
                $valueQuery = "WHERE customTargetingKeyId = " . $keyId . " AND status = 'ACTIVE' LIMIT 10";
            }
            
            $valueStatement->setQuery($valueQuery);
            Log::info('Fetching ACTIVE custom targeting values with query: ' . $valueQuery);
            
            // Make the API call to get the values
            $valuePage = $this->service->getCustomTargetingValuesByStatement($valueStatement);
            
            if ($valuePage->getResults() !== null) {
                $count = count($valuePage->getResults());
                Log::info("Found {$count} ACTIVE custom targeting values");
                
                // Log each value for debugging
                foreach ($valuePage->getResults() as $index => $value) {
                    Log::info("Value {$index}: " . $value->getName() . " (ID: " . $value->getId() . ")");
                }
                
                return $valuePage->getResults();
            }
            
            Log::info('No ACTIVE custom targeting values found');
            return [];
        } catch (\Exception $e) {
            Log::error('Error fetching custom targeting values: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'keyName' => $keyName,
                'search' => $search,
                'retry_count' => $retryCount
            ]);
            
            // Retry logic for certain exceptions
            if ($retryCount < $this->maxRetries) {
                $shouldRetry = false;
                
                // Check if it's a network error or authentication issue
                if (strpos($e->getMessage(), 'cURL error') !== false || 
                    strpos($e->getMessage(), 'authentication') !== false ||
                    strpos($e->getMessage(), 'session') !== false) {
                    $shouldRetry = true;
                }
                
                if ($shouldRetry) {
                    Log::info('Retrying getCustomTargetingValues after error', ['retry' => $retryCount + 1]);
                    // Wait a bit before retrying (exponential backoff)
                    usleep(pow(2, $retryCount) * 100000); // 100ms, 200ms, 400ms, etc.
                    return $this->getCustomTargetingValues($keyName, $search, $retryCount + 1);
                }
            }
            
            throw $e; // Re-throw the exception to be handled by the controller
        }
    }

    /**
     * Debug method to check if the service is properly initialized
     *
     * @return array
     */
    public function debug(): array
    {
        $result = [
            'service_initialized' => $this->service !== null,
            'session_initialized' => $this->session !== null,
        ];
        
        if ($this->session !== null) {
            $result['session_type'] = get_class($this->session);
            
            // Try to get session details
            try {
                $reflection = new \ReflectionClass($this->session);
                $networkCodeProperty = $reflection->getProperty('networkCode');
                $networkCodeProperty->setAccessible(true);
                $result['network_code'] = $networkCodeProperty->getValue($this->session);
                
                $applicationNameProperty = $reflection->getProperty('applicationName');
                $applicationNameProperty->setAccessible(true);
                $result['application_name'] = $applicationNameProperty->getValue($this->session);
            } catch (\Exception $e) {
                $result['reflection_error'] = $e->getMessage();
            }
            
            // Try a simple API call to check connectivity
            try {
                $statement = new Statement();
                $statement->setQuery("LIMIT 1");
                $result['api_call_attempted'] = true;
                
                if ($this->service) {
                    $page = $this->service->getCustomTargetingKeysByStatement($statement);
                    $result['api_call_successful'] = true;
                    $result['results_count'] = $page->getResults() ? count($page->getResults()) : 0;
                    
                    if ($page->getResults() && count($page->getResults()) > 0) {
                        $result['first_key'] = [
                            'id' => $page->getResults()[0]->getId(),
                            'name' => $page->getResults()[0]->getName(),
                            'displayName' => $page->getResults()[0]->getDisplayName(),
                            'type' => $page->getResults()[0]->getType()
                        ];
                    }
                } else {
                    $result['api_call_successful'] = false;
                    $result['error'] = 'Service not initialized';
                }
            } catch (\Exception $e) {
                $result['api_call_successful'] = false;
                $result['error'] = $e->getMessage();
                $result['trace'] = $e->getTraceAsString();
            }
        } else {
            $result['api_call_attempted'] = false;
            $result['api_call_successful'] = false;
            $result['error'] = 'Session not initialized';
        }
        
        return $result;
    }
} 