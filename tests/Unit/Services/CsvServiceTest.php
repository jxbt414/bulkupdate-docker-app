<?php

namespace Tests\Unit\Services;

use App\Services\CsvService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Exception;

class CsvServiceTest extends TestCase
{
    private CsvService $csvService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csvService = new CsvService();
    }

    public function test_validates_required_headers(): void
    {
        $csvContent = "invalid_header\n123";
        $file = $this->createTemporaryCsvFile($csvContent);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing required headers: line_item_id, line_item_name");

        $this->csvService->validateAndParseCsv($file);
    }

    public function test_validates_invalid_headers(): void
    {
        $csvContent = "line_item_id,line_item_name,invalid_column\n123,Test Name,value";
        $file = $this->createTemporaryCsvFile($csvContent);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid headers found: invalid_column");

        $this->csvService->validateAndParseCsv($file);
    }

    public function test_parses_valid_csv_successfully(): void
    {
        $csvContent = "line_item_id,line_item_name,budget,priority\n123,Test Name,100.50,5";
        $file = $this->createTemporaryCsvFile($csvContent);

        $result = $this->csvService->validateAndParseCsv($file);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals([
            'line_item_id' => '123',
            'line_item_name' => 'Test Name',
            'budget' => 100.50,
            'priority' => 5
        ], $result[0]);
    }

    public function test_validates_numeric_fields(): void
    {
        $csvContent = "line_item_id,line_item_name,budget,priority\n123,Test Name,invalid,5";
        $file = $this->createTemporaryCsvFile($csvContent);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Budget must be a number");

        $this->csvService->validateAndParseCsv($file);
    }

    public function test_handles_empty_csv(): void
    {
        $csvContent = "line_item_id,line_item_name\n";
        $file = $this->createTemporaryCsvFile($csvContent);

        $result = $this->csvService->validateAndParseCsv($file);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_handles_missing_optional_fields(): void
    {
        $csvContent = "line_item_id,line_item_name\n123,Test Name";
        $file = $this->createTemporaryCsvFile($csvContent);

        $result = $this->csvService->validateAndParseCsv($file);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals([
            'line_item_id' => '123',
            'line_item_name' => 'Test Name'
        ], $result[0]);
    }

    private function createTemporaryCsvFile(string $content): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv_');
        file_put_contents($tempFile, $content);

        return new UploadedFile(
            $tempFile,
            'test.csv',
            'text/csv',
            null,
            true
        );
    }
} 