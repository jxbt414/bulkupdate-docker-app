<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LineItemController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\FeedbackController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Add this temporary route for debugging
    Route::get('/debug-ziggy', function () {
        return response()->json(app('ziggy')->toArray());
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Line Items Routes
    Route::get('/line-items/upload', function () {
        return Inertia::render('LineItems/Upload');
    })->name('line-items.upload');

    Route::get('/line-items/static-sample-csv', [LineItemController::class, 'downloadStaticSampleCsv'])
        ->name('line-items.static-sample-csv');

    Route::get('/line-items/dynamic-sample-csv', [LineItemController::class, 'downloadDynamicSampleCsv'])
        ->name('line-items.dynamic-sample-csv');

    Route::get('/line-items', [LineItemController::class, 'index'])->name('line-items.index');
    Route::get('/line-items/create', [LineItemController::class, 'create'])->name('line-items.create');
    Route::post('/line-items', [LineItemController::class, 'store'])->name('line-items.store');
    Route::get('/line-items/static-update', [LineItemController::class, 'staticUpdate'])->name('line-items.static-update');
    Route::post('/line-items/preview', [LineItemController::class, 'preview'])->name('line-items.preview');
    Route::get('/line-items/preview', [LineItemController::class, 'preview'])->name('line-items.preview.get');
    Route::get('/line-items/logs', function () {
        return Inertia::render('LineItems/Logs');
    })->name('line-items.logs');

    Route::get('/settings', function () {
        return Inertia::render('Settings');
    })->name('settings');

    Route::post('/line-items/upload', [LineItemController::class, 'upload'])->name('line-items.upload.post');
    Route::post('/line-items/parse-ids', [LineItemController::class, 'parseIds'])->name('line-items.parse-ids');
    Route::post('/line-items/map-fields', [LineItemController::class, 'mapFields'])->name('line-items.map-fields');
    Route::get('/line-items/preview/data/{sessionId}', [LineItemController::class, 'getPreviewData'])->name('line-items.preview.data');
    Route::post('/line-items/update', [LineItemController::class, 'update'])->name('line-items.update');
    Route::post('/line-items/bulk-update', [LineItemController::class, 'bulkUpdate'])->name('line-items.bulk-update');
    Route::post('/line-items/{lineItemId}/rollback', [LineItemController::class, 'rollback'])->name('line-items.rollback');
    Route::post('/line-items/rollback-batch/{batchId}', [LineItemController::class, 'rollbackBatch'])->name('line-items.rollback-batch');
    Route::get('/line-items/logs/data', [LineItemController::class, 'getLogs'])->name('line-items.logs.data');
    Route::get('/line-items/available-labels', [LineItemController::class, 'getAvailableLabels'])->name('line-items.available-labels');
    Route::get('/line-items/available-ad-units', [LineItemController::class, 'getAvailableAdUnits'])
        ->name('line-items.available-ad-units');
    Route::get('/line-items/available-placements', [LineItemController::class, 'getAvailablePlacements'])
        ->name('line-items.available-placements');
    Route::get('/line-items/available-locations', [LineItemController::class, 'getAvailableLocations'])
        ->name('line-items.available-locations');
    Route::get('/line-items/available-custom-targeting-keys', [LineItemController::class, 'getAvailableCustomTargetingKeys'])
        ->name('line-items.available-custom-targeting-keys');
    Route::get('/line-items/custom-targeting-keys', [LineItemController::class, 'getCustomTargetingKeys'])->name('line-items.custom-targeting-keys');
    Route::get('/line-items/custom-targeting-values', [LineItemController::class, 'getCustomTargetingValues'])->name('line-items.custom-targeting-values');
    
    // Audience Segments and CMS Metadata routes
    Route::get('/line-items/audience-segments', [LineItemController::class, 'getAudienceSegments'])
        ->name('line-items.audience-segments');
    Route::get('/line-items/cms-metadata-keys', [LineItemController::class, 'getCmsMetadataKeys'])
        ->name('line-items.cms-metadata-keys');
    Route::get('/line-items/cms-metadata-values', [LineItemController::class, 'getCmsMetadataValues'])
        ->name('line-items.cms-metadata-values');

    // Bulk update status and retry routes
    Route::get('/line-items/bulk-update-status/{batchId}', [LineItemController::class, 'getBulkUpdateStatus'])
        ->name('line-items.bulk-update-status');
    Route::post('/line-items/retry-bulk-update/{batchId}', [LineItemController::class, 'retryBulkUpdate'])
        ->name('line-items.retry-bulk-update');

    // Test route for debugging CMS metadata
    Route::get('/test/cms-metadata', [LineItemController::class, 'testCmsMetadata'])
        ->name('test.cms-metadata');

    // Temporary test route for verifying line item updates
    Route::get('/test/verify-update/{lineItemId}', [LineItemController::class, 'testVerifyUpdate'])
        ->name('test.verify-update');

    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/data', [SettingsController::class, 'show'])->name('settings.show');

    // Secret Feedback Page
    Route::get('/secret-feedback', function () {
        return Inertia::render('SecretFeedback');
    })->name('secret-feedback');
    Route::get('/feedback/data', [FeedbackController::class, 'index'])->name('feedback.data');

    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');

    // Test endpoint for custom targeting keys
    Route::get('/line-items/test-custom-targeting-keys', [LineItemController::class, 'testCustomTargetingKeys'])->name('line-items.test-custom-targeting-keys');
});

require __DIR__.'/auth.php';
