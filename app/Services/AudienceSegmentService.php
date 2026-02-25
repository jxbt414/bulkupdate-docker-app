<?php

namespace App\Services;

use App\Helpers\Color;
use Google\AdsApi\AdManager\v202411\AudienceSegmentService as GoogleAudienceSegmentService;
use Google\AdsApi\AdManager\v202411\Statement;
use Google\AdsApi\AdManager\v202411\ServiceFactory;
use Illuminate\Support\Facades\Log;

class AudienceSegmentService
{
    private $session;
    private $service;

    public function __construct($session)
    {
        $this->session = $session;
        $serviceFactory = new ServiceFactory();
        $this->service = $serviceFactory->createAudienceSegmentService($this->session);
        Log::info(Color::green('AudienceSegmentService initialized'));
    }

    /**
     * Search for audience segments
     *
     * @param string|null $query Search query
     * @return array
     */
    public function searchSegments($query = null)
    {
        try {
            Log::info(Color::blue('Searching for ACTIVE audience segments'), ['query' => $query]);
            
            $statement = new Statement();
            if ($query) {
                $statement->setQuery("WHERE status = 'ACTIVE' AND type IN ('FIRST_PARTY', 'SHARED') AND name LIKE '%" . $query . "%' LIMIT 50");
            } else {
                $statement->setQuery("WHERE status = 'ACTIVE' AND type IN ('FIRST_PARTY', 'SHARED') LIMIT 50");
            }
            
            Log::info(Color::blue('Executing query'), [
                'query' => $statement->getQuery()
            ]);
            
            $response = $this->service->getAudienceSegmentsByStatement($statement);
            $segments = $response->getResults() ?? [];
            
            // Log raw segment data for debugging
            Log::info(Color::blue('Raw segments received from GAM'), [
                'total_count' => count($segments),
                'query_used' => $statement->getQuery(),
                'segments' => array_map(function($segment) {
                    return [
                        'id' => $segment->getId(),
                        'name' => $segment->getName(),
                        'status' => $segment->getStatus(),
                        'type' => $segment->getType(),
                        'segmentType' => get_class($segment)
                    ];
                }, $segments)
            ]);
            
            $formattedSegments = [];
            foreach ($segments as $segment) {
                // Log detailed info for each segment
                Log::info(Color::blue('Processing segment'), [
                    'id' => $segment->getId(),
                    'name' => $segment->getName(),
                    'status' => $segment->getStatus(),
                    'type' => $segment->getType(),
                    'segmentType' => get_class($segment),
                    'hasDataProvider' => method_exists($segment, 'getDataProvider') ? 'yes' : 'no',
                    'dataProvider' => method_exists($segment, 'getDataProvider') ? $segment->getDataProvider() : null
                ]);

                if ($segment->getStatus() !== 'ACTIVE') {
                    Log::warning(Color::yellow('Found non-active segment, skipping'), [
                        'id' => $segment->getId(),
                        'name' => $segment->getName(),
                        'status' => $segment->getStatus(),
                        'type' => $segment->getType()
                    ]);
                    continue;
                }
                
                $formattedSegments[] = [
                    'id' => $segment->getId(),
                    'name' => $segment->getName(),
                    'description' => $segment->getDescription(),
                    'type' => $segment->getType(),
                    'size' => $segment->getSize(),
                    'status' => $segment->getStatus(),
                    'dataProvider' => method_exists($segment, 'getDataProvider') ? $segment->getDataProvider() : null
                ];
            }
            
            Log::info(Color::green('Found ACTIVE audience segments'), [
                'count' => count($formattedSegments),
                'sample' => !empty($formattedSegments) ? $formattedSegments[0] : null
            ]);
            
            return $formattedSegments;
        } catch (\Exception $e) {
            Log::error(Color::red('Error searching audience segments'), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
} 