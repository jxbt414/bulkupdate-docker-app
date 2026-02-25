<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Log;
use App\Models\User;
use App\Services\GoogleAdManagerService;
use Google\ApiCore\ApiException;
use Google\AdsApi\AdManager\v202411\LineItem;
use Google\AdsApi\AdManager\v202411\LineItemPage;
use Google\AdsApi\AdManager\v202411\LineItemService;
use Google\AdsApi\AdManager\v202411\Targeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Mockery;

class LineItemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private $mockLineItemService;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Mock LineItemService
        $this->mockLineItemService = Mockery::mock(LineItemService::class);
        
        // Mock GoogleAdManagerService
        $mockAdManagerService = Mockery::mock(GoogleAdManagerService::class);
        $mockAdManagerService->shouldReceive('getLineItemService')
            ->andReturn($this->mockLineItemService);
        
        $this->app->instance(GoogleAdManagerService::class, $mockAdManagerService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_complete_line_item_update_flow(): void
    {
        // 1. Upload CSV
        $csvContent = "line_item_id,line_item_name,budget,priority\n123,Test Line Item,1000.50,5";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/upload', [
                'csv' => $file
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'CSV validated successfully'
            ]);

        // 2. Map Fields
        $mappings = [
            'line_item_id' => 'line_item_id',
            'line_item_name' => 'line_item_name',
            'budget' => 'budget',
            'priority' => 'priority'
        ];

        $data = [[
            'line_item_id' => '123',
            'line_item_name' => 'Test Line Item',
            'budget' => 1000.50,
            'priority' => 5
        ]];

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/map-fields', [
                'mappings' => $mappings,
                'data' => $data
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Fields mapped successfully'
            ]);

        $sessionId = $response->json('id');

        // 3. Mock current line item data
        $lineItem = new LineItem();
        $lineItem->setId('123');
        $lineItem->setName('Test Line Item');
        
        $page = new LineItemPage();
        $page->setResults([$lineItem]);
        
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andReturn($page);

        // 4. Get Preview Data
        $response = $this->actingAs($this->user)
            ->get("/api/line-items/preview/{$sessionId}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success'
            ]);

        // 5. Update Line Item
        $this->mockLineItemService
            ->shouldReceive('updateLineItems')
            ->once()
            ->andReturn([$lineItem]);

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/update', [
                'line_item_id' => '123',
                'budget' => 1000.50,
                'priority' => 5
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Line item updated successfully'
            ]);

        // 6. Verify Logs
        $this->assertDatabaseHas('logs', [
            'user_id' => $this->user->id,
            'action' => 'update',
            'line_item_id' => '123',
            'status' => 'success'
        ]);
    }

    public function test_handles_invalid_csv_upload(): void
    {
        $csvContent = "invalid_header\n123";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/upload', [
                'csv' => $file
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Missing required headers: line_item_id, line_item_name'
            ]);
    }

    public function test_handles_invalid_field_mapping(): void
    {
        $mappings = [
            'invalid_field' => 'line_item_id'
        ];

        $data = [[
            'invalid_field' => '123'
        ]];

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/map-fields', [
                'mappings' => $mappings,
                'data' => $data
            ]);

        $response->assertStatus(400);
    }

    public function test_handles_expired_preview_session(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/api/line-items/preview/invalid_session_id');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Preview data not found or has expired'
            ]);
    }

    public function test_rollback_functionality(): void
    {
        // 1. Mock original line item
        $originalLineItem = new LineItem();
        $originalLineItem->setId('123');
        $originalLineItem->setName('Original Name');
        
        $page = new LineItemPage();
        $page->setResults([$originalLineItem]);
        
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andReturn($page);
            
        $this->mockLineItemService
            ->shouldReceive('updateLineItems')
            ->once()
            ->andReturn([$originalLineItem]);

        // 2. Perform rollback
        $response = $this->actingAs($this->user)
            ->post('/api/line-items/rollback/123');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Line item rolled back successfully'
            ]);

        // 3. Verify rollback log
        $this->assertDatabaseHas('logs', [
            'user_id' => $this->user->id,
            'action' => 'rollback',
            'line_item_id' => '123',
            'status' => 'success'
        ]);
    }

    public function test_handles_rate_limiting(): void
    {
        // Simulate hitting rate limit
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andReturn(true);

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/update', [
                'line_item_id' => '123',
                'budget' => 1000.50
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'status' => 'error',
                'message' => 'Too many requests. Please try again later.'
            ]);
    }

    public function test_handles_concurrent_updates(): void
    {
        // Simulate concurrent update by setting a lock
        Cache::put('line_item_lock_123', true, now()->addMinutes(5));

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/update', [
                'line_item_id' => '123',
                'budget' => 1000.50
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'status' => 'error',
                'message' => 'Line item is currently being updated by another user.'
            ]);
    }

    public function test_handles_large_csv_file(): void
    {
        // Create a large CSV file (over 1000 lines)
        $csvContent = "line_item_id,line_item_name,budget,priority\n";
        for ($i = 1; $i <= 1000; $i++) {
            $csvContent .= "{$i},Test Line Item {$i},1000.50,5\n";
        }

        $file = UploadedFile::fake()->createWithContent('large_test.csv', $csvContent);

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/upload', [
                'csv' => $file
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'CSV validated successfully'
            ]);
    }

    public function test_validates_file_type(): void
    {
        // Try to upload a non-CSV file
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/upload', [
                'csv' => $file
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'The csv must be a file of type: csv.'
            ]);
    }

    public function test_handles_complex_targeting_data(): void
    {
        $targeting = [
            'inventory' => ['placement_id' => '12345'],
            'geography' => ['country_code' => 'US'],
            'custom_criteria' => [
                ['key' => 'age', 'value' => '18-34'],
                ['key' => 'gender', 'value' => 'M']
            ]
        ];

        // Mock line item with targeting
        $lineItem = new LineItem();
        $lineItem->setId('123');
        $lineItem->setTargeting(new Targeting());
        
        $page = new LineItemPage();
        $page->setResults([$lineItem]);
        
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andReturn($page);
            
        $this->mockLineItemService
            ->shouldReceive('updateLineItems')
            ->once()
            ->andReturn([$lineItem]);

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/update', [
                'line_item_id' => '123',
                'targeting' => $targeting
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Line item updated successfully'
            ]);
    }

    public function test_handles_authentication_failure(): void
    {
        $response = $this->post('/api/line-items/update', [
            'line_item_id' => '123',
            'budget' => 1000.50
        ]);

        $response->assertStatus(401);
    }

    public function test_handles_invalid_session_token(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/api/line-items/update', [
                'line_item_id' => '123',
                'budget' => 1000.50
            ], [
                'X-CSRF-TOKEN' => 'invalid_token'
            ]);

        $response->assertStatus(419);
    }

    public function test_handles_api_timeout(): void
    {
        $this->mockLineItemService
            ->shouldReceive('getLineItemsByStatement')
            ->once()
            ->andThrow(new \Exception('Request timeout'));

        $response = $this->actingAs($this->user)
            ->post('/api/line-items/update', [
                'line_item_id' => '123',
                'budget' => 1000.50
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Request timeout'
            ]);

        // Verify error is logged
        $this->assertDatabaseHas('logs', [
            'user_id' => $this->user->id,
            'action' => 'update',
            'line_item_id' => '123',
            'status' => 'error'
        ]);
    }
} 