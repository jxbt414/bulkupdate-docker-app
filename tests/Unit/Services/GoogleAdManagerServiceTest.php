<?php

namespace Tests\Unit\Services;

use App\Models\Log;
use App\Models\Rollback;
use App\Services\GoogleAdManagerService;
use Google\AdsApi\AdManager\v202411\LineItem;
use Google\AdsApi\AdManager\v202411\LineItemPage;
use Google\AdsApi\AdManager\v202411\LineItemService;
use Google\AdsApi\AdManager\v202411\Money;
use Google\AdsApi\AdManager\v202411\Goal;
use Google\AdsApi\AdManager\v202411\Statement;
use Tests\TestCase;
use Exception;
use Mockery;

class GoogleAdManagerServiceTest extends TestCase
{
    private GoogleAdManagerService $service;
    private $mockLineItemService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the LineItemService
        $this->mockLineItemService = Mockery::mock(LineItemService::class);
        
        // Create service instance with mocked dependencies
        $this->service = new GoogleAdManagerService();
        $this->service->setLineItemService($this->mockLineItemService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_updates_line_item_successfully(): void
    {
        // Create a mock line item
        $lineItem = new LineItem();
        $lineItem->setId('123');
        $lineItem->setName('Test Line Item');
        
        // Create a mock response
        $page = new LineItemPage();
        $page->setResults([$lineItem]);
        
        // Set up expectations
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andReturn($page);
            
        $this->mockLineItemService
            ->shouldReceive('updateLineItems')
            ->once()
            ->andReturn([$lineItem]);

        // Test the update
        $data = [
            'line_item_id' => '123',
            'line_item_name' => 'Updated Name',
            'budget' => 1000.00,
            'priority' => 5
        ];

        $this->service->updateLineItem($data, 1);

        // Assert log was created
        $this->assertDatabaseHas('logs', [
            'user_id' => 1,
            'action' => 'update',
            'line_item_id' => '123',
            'status' => 'success'
        ]);
    }

    public function test_handles_line_item_not_found(): void
    {
        // Create an empty response
        $page = new LineItemPage();
        $page->setResults([]);
        
        // Set up expectations
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andReturn($page);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Line item not found: 123');

        $data = ['line_item_id' => '123'];
        $this->service->updateLineItem($data, 1);
    }

    public function test_stores_rollback_data(): void
    {
        // Create a mock line item
        $lineItem = new LineItem();
        $lineItem->setId('123');
        $lineItem->setName('Original Name');
        
        // Create a mock response
        $page = new LineItemPage();
        $page->setResults([$lineItem]);
        
        // Set up expectations
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andReturn($page);

        // Test storing rollback data
        $this->service->storeRollbackData('123');

        // Assert rollback was created
        $this->assertDatabaseHas('rollbacks', [
            'line_item_id' => '123'
        ]);
    }

    public function test_performs_rollback_successfully(): void
    {
        // Create original and updated line items
        $originalLineItem = new LineItem();
        $originalLineItem->setId('123');
        $originalLineItem->setName('Original Name');
        
        // Create rollback record
        Rollback::create([
            'line_item_id' => '123',
            'previous_data' => json_decode(json_encode($originalLineItem), true),
            'rollback_timestamp' => now()
        ]);
        
        // Create a mock response for current line item
        $currentLineItem = new LineItem();
        $currentLineItem->setId('123');
        $currentLineItem->setName('Updated Name');
        
        $page = new LineItemPage();
        $page->setResults([$currentLineItem]);
        
        // Set up expectations
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andReturn($page);
            
        $this->mockLineItemService
            ->shouldReceive('updateLineItems')
            ->once()
            ->andReturn([$originalLineItem]);

        // Test the rollback
        $this->service->rollback('123', 1);

        // Assert log was created
        $this->assertDatabaseHas('logs', [
            'user_id' => 1,
            'action' => 'rollback',
            'line_item_id' => '123',
            'status' => 'success'
        ]);
    }

    public function test_handles_api_errors(): void
    {
        // Set up expectations for API error
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andThrow(new Exception('API Error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API Error');

        $data = ['line_item_id' => '123'];
        $this->service->updateLineItem($data, 1);

        // Assert error log was created
        $this->assertDatabaseHas('logs', [
            'user_id' => 1,
            'action' => 'update',
            'line_item_id' => '123',
            'status' => 'error'
        ]);
    }
} 