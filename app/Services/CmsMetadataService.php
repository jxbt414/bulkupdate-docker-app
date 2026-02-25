<?php

namespace App\Services;

use App\Helpers\Color;
use Google\AdsApi\AdManager\v202411\CmsMetadataService as GoogleCmsMetadataService;
use Google\AdsApi\AdManager\v202411\Statement;
use Google\AdsApi\AdManager\v202411\ServiceFactory;
use Illuminate\Support\Facades\Log;

class CmsMetadataService
{
    private $session;
    private $service;

    public function __construct($session)
    {
        $this->session = $session;
        $serviceFactory = new ServiceFactory();
        $this->service = $serviceFactory->createCmsMetadataService($this->session);
        Log::info(Color::green('CmsMetadataService initialized'));
    }

    /**
     * Search for CMS metadata keys
     *
     * @param string|null $query Search query
     * @return array
     */
    public function searchMetadata($query = null)
    {
        try {
            Log::info(Color::blue('Searching for ACTIVE CMS metadata keys'), ['query' => $query]);
            
            $statement = new Statement();
            if ($query) {
                $statement->setQuery("WHERE status = 'ACTIVE' AND name LIKE '%" . $query . "%' LIMIT 50");
            } else {
                $statement->setQuery("WHERE status = 'ACTIVE' LIMIT 50");
            }
            
            $response = $this->service->getCmsMetadataKeysByStatement($statement);
            $keys = $response->getResults() ?? [];
            
            $formattedKeys = [];
            foreach ($keys as $key) {
                $formattedKeys[] = [
                    'id' => $key->getId(),
                    'name' => $key->getName(),
                    'status' => $key->getStatus(),
                ];
            }
            
            Log::info(Color::green('Found ACTIVE CMS metadata keys'), [
                'count' => count($formattedKeys),
                'sample' => !empty($formattedKeys) ? $formattedKeys[0] : null
            ]);
            
            return $formattedKeys;
        } catch (\Exception $e) {
            Log::error(Color::red('Error searching CMS metadata keys'), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Search for CMS metadata values for a specific key
     *
     * @param int $keyId The CMS metadata key ID
     * @param string|null $query Search query
     * @return array
     */
    public function searchMetadataValues($keyId, $query = null)
    {
        try {
            Log::info(Color::blue('Searching for ACTIVE CMS metadata values'), [
                'keyId' => $keyId,
                'query' => $query
            ]);
            
            $statement = new Statement();
            $queryString = "WHERE cmsKeyId = " . $keyId . " AND status = 'ACTIVE'";
            
            if ($query && trim($query) !== '') {
                $queryString .= " AND cmsValue LIKE '%" . $query . "%'";
            }
            
            $queryString .= " LIMIT 50";
            $statement->setQuery($queryString);
            
            Log::info(Color::blue('CMS metadata values query'), [
                'query' => $queryString
            ]);
            
            $response = $this->service->getCmsMetadataValuesByStatement($statement);
            $values = $response->getResults() ?? [];
            
            $formattedValues = [];
            foreach ($values as $value) {
                $formattedValues[] = [
                    'id' => $value->getCmsMetadataValueId(),
                    'name' => $value->getValueName(),
                    'keyId' => $value->getKey()->getId(),
                    'status' => $value->getStatus(),
                ];
            }
            
            Log::info(Color::green('Found ACTIVE CMS metadata values'), [
                'count' => count($formattedValues),
                'sample' => !empty($formattedValues) ? $formattedValues[0] : null
            ]);
            
            return $formattedValues;
        } catch (\Exception $e) {
            Log::error(Color::red('Error searching CMS metadata values'), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
} 