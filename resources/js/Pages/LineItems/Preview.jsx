import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LineItemTable from '@/Components/LineItemTable';
import ErrorLog from '@/Components/ErrorLog';
import LoadingSpinner from '@/Components/LoadingSpinner';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { toast } from 'react-hot-toast';
import BulkUpdateProgress from '@/Components/BulkUpdateProgress';

export default function Preview({ auth, sessionId }) {
    const [mappedData, setMappedData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [selectedItems, setSelectedItems] = useState([]);
    const [updateStatus, setUpdateStatus] = useState({});
    const [lineItemTypes, setLineItemTypes] = useState({});
    const [batchId, setBatchId] = useState(null);

    // Add all update fields matching StaticUpdate
    const [updates, setUpdates] = useState({
        budget: '',
        priority: '',
        impression_goals: '',
        status: '',
        start_date_time: '',
        end_date_time: '',
        unlimited_end_date: false,
        delivery_rate_type: '',
        cost_type: '',
        cost_per_unit: '',
        labels: '',
        // Frequency Cap
        frequency_cap_max: '',
        frequency_cap_time_units: '',
        frequency_cap_time_unit_type: '',
        // Targeting
        geo_targeting_included: '',
        geo_targeting_excluded: '',
        inventory_targeting_included: '',
        inventory_targeting_excluded: '',
        custom_targeting: '',
        day_part_targeting: '',
        device_category_targeting: ''
    });

    // Line item type restrictions
    const LINE_ITEM_PRIORITIES = {
        SPONSORSHIP: { min: 1, max: 4, label: 'Sponsorship (1-4)' },
        STANDARD: { min: 6, max: 10, label: 'Standard (6-10)' },
        NETWORK: { value: 12, label: 'Network (12)' },
        BULK: { value: 12, label: 'Bulk (12)' },
        PRICE_PRIORITY: { value: 12, label: 'Price Priority (12)' },
        AD_EXCHANGE: { value: 12, label: 'Ad Exchange (12)' },
        HOUSE: { value: 16, label: 'House (16)' },
        BUMPER: { value: 16, label: 'Bumper (16)' }
    };

    // Validation functions
    const validatePriority = (priority, lineItemType) => {
        if (!lineItemType) return true; // Skip validation if type is unknown
        
        const restriction = LINE_ITEM_PRIORITIES[lineItemType];
        if (!restriction) return true; // Skip validation if no restriction found

        priority = parseInt(priority);
        if (restriction.value) {
            return priority === restriction.value;
        } else {
            return priority >= restriction.min && priority <= restriction.max;
        }
    };

    const validateImpressionGoals = (goals, lineItemType) => {
        if (!lineItemType || !goals) return true;
        
        if (lineItemType === 'SPONSORSHIP') {
            const value = parseInt(goals);
            return value >= 0 && value <= 100;
        }
        return true;
    };

    const getPriorityOptions = (lineItemType) => {
        if (!lineItemType) return [];
        
        const restriction = LINE_ITEM_PRIORITIES[lineItemType];
        if (!restriction) return [];
        
        if (restriction.value) {
            return [{ value: restriction.value, label: restriction.label }];
        }
        
        const options = [];
        for (let i = restriction.min; i <= restriction.max; i++) {
            options.push({
                value: i,
                label: `${i} - ${lineItemType.toLowerCase()}`
            });
        }
        return options;
    };

    // Log props when component mounts
    useEffect(() => {
        console.log('Preview component props:', { auth, sessionId });
    }, []);

    useEffect(() => {
        console.log('Preview component mounted with sessionId:', sessionId);
        if (!sessionId) {
            console.error('No sessionId provided to Preview component');
            setError('No session ID provided. Please start from the upload page.');
            return;
        }

        const url = `/line-items/preview/data/${sessionId}`;
        console.log('Will request data from:', url);
        loadPreviewData(sessionId);
    }, [sessionId]);

    const loadPreviewData = async (sessionId) => {
        console.log('Starting to load preview data for sessionId:', sessionId);
        setLoading(true);
        try {
            const url = `/line-items/preview/data/${sessionId}`;
            console.log('Making request to:', url);
            const response = await axios.get(url);
            console.log('Preview data response:', response.data);
            
            if (response.data.status === 'success' && response.data.data) {
                // Process the data to only include fields that will be updated
                const processedData = response.data.data.map(item => {
                    // Keep values as strings, just like the test command
                    if (item.priority !== undefined) {
                        item.priority = String(item.priority);
                    }
                    if (item.impression_goals !== undefined) {
                        item.impression_goals = String(item.impression_goals);
                    }
                    if (item.budget !== undefined) {
                        item.budget = String(item.budget);
                    }
                    
                    // Get all fields that have new values from CSV
                    const fieldsToUpdate = Object.keys(item).filter(key => 
                        // Only include fields that:
                        // 1. Don't start with 'original_'
                        // 2. Aren't null/undefined
                        // 3. Have a value different from the original
                        // 4. Are actually present in the CSV data
                        !key.startsWith('original_') && 
                        item[key] !== null && 
                        item[key] !== undefined &&
                        key !== 'line_item_id' &&
                        // Check if the value is different from original
                        (item[`original_${key}`] === undefined || item[key] !== item[`original_${key}`])
                    );

                    console.log('Fields to update for item', item.line_item_id, ':', fieldsToUpdate);

                    // Create a new object with only the fields being updated
                    const processedItem = {
                        line_item_id: item.line_item_id,
                        ...fieldsToUpdate.reduce((acc, key) => {
                            // Add the new value
                            acc[key] = item[key];
                            // Add the corresponding original value if it exists
                            const originalKey = `original_${key}`;
                            if (item[originalKey] !== undefined) {
                                acc[originalKey] = item[originalKey];
                            }
                            return acc;
                        }, {})
                    };

                    console.log('Processed item:', {
                        line_item_id: item.line_item_id,
                        fieldsToUpdate,
                        processedItem
                    });

                    return processedItem;
                });

                console.log('Setting processed mapped data:', processedData);
                setMappedData(processedData);
                
                // Auto-select all items
                setSelectedItems(processedData);
            } else {
                console.error('Invalid response format:', response.data);
                throw new Error(response.data.message || 'No preview data available');
            }
        } catch (err) {
            console.error('Error loading preview data:', {
                error: err,
                message: err.message,
                response: err.response?.data
            });
            setError(err.response?.data?.message || err.message || 'Failed to load preview data');
        } finally {
            setLoading(false);
        }
    };

    const handleUpdate = async () => {
        if (selectedItems.length === 0) {
            setError('Please select at least one line item to update');
            return;
        }

        setLoading(true);
        setError(null);
        
        // Create a temporary batch ID immediately
        const tempBatchId = 'temp_' + Date.now();
        setBatchId(tempBatchId);
        
        try {
            console.log('Starting update process with selected items:', selectedItems);
            
            // Create payload from selected items, similar to test command approach
            const payload = {
                line_items: selectedItems.map(item => {
                    console.log(`Creating update payload for line item ${item.line_item_id}`);
                    
                    // Start with just the line item ID
                    const updatePayload = {
                        line_item_id: item.line_item_id
                    };
                    
                    // Add line item name if available
                    if (item.line_item_name) {
                        updatePayload.line_item_name = item.line_item_name;
                        console.log(`Setting line_item_name to: ${item.line_item_name}`);
                    }
                    
                    // Add priority as string if available
                    if (item.priority !== undefined && item.priority !== null) {
                        updatePayload.priority = String(item.priority);
                        console.log(`Setting priority to: ${updatePayload.priority}`);
                    }
                    
                    // Add impression_goals as string if available
                    if (item.impression_goals !== undefined && item.impression_goals !== null) {
                        updatePayload.impression_goals = String(item.impression_goals);
                        console.log(`Setting impression_goals to: ${updatePayload.impression_goals}`);
                    }
                    
                    // Add budget as string if available
                    if (item.budget !== undefined && item.budget !== null) {
                        updatePayload.budget = String(item.budget);
                        console.log(`Setting budget to: ${updatePayload.budget}`);
                    }
                    
                    console.log(`Final update payload for line item ${item.line_item_id}:`, updatePayload);
                    return updatePayload;
                })
            };

            console.log('Sending final update payload to server:', payload);
            const response = await axios.post('/line-items/bulk-update', payload);
            console.log('Update response:', response.data);
            
            if (response.data.status === 'success') {
                const results = response.data.results;
                
                // Update with the real batch ID from the server
                setBatchId(results.batch_id);
                
                // Set loading to false once we have the batch ID
                setLoading(false);
            } else {
                throw new Error(response.data.message || 'Failed to update line items');
            }
        } catch (err) {
            console.error('Update error:', err);
            setBatchId(null); // Clear the batch ID if there's an error
            setLoading(false);
            
            // Extract error details
            let errorMessage = err.message;
            const apiError = err.response?.data?.message;
            
            if (apiError) {
                // Parse Google Ad Manager API errors
                if (apiError.includes('PERCENTAGE_UNITS_BOUGHT_TOO_HIGH')) {
                    errorMessage = 'Impression goal percentage must be between 0 and 100 for sponsorship line items.';
                } else if (apiError.includes('targeting.requestPlatformTargeting')) {
                    errorMessage = 'Request platform targeting is required. Please try again.';
                } else if (apiError.includes('targeting.inventoryTargeting')) {
                    errorMessage = 'Inventory targeting is required. Please try again.';
                } else {
                    errorMessage = apiError;
                }
            }
            
            setError(errorMessage);
            
            // Show error in toast notification
            toast.error(errorMessage, {
                duration: 5000,
                position: 'top-right',
            });
        }
    };

    // Handle completion of bulk update
    const handleUpdateComplete = (status) => {
        setLoading(false);
        
        if (status.failed > 0) {
            // Only set error state for failed updates
            setError(
                `Failed items:\n` +
                status.failed_items.map(item => 
                    `Line item ${item.line_item_id}: ${item.error}`
                ).join('\n')
            );
        }
    };

    const handleRollback = async (lineItemId) => {
        try {
            await axios.post(`/line-items/${lineItemId}/rollback`);
            setSelectedItems(prev => prev.filter(id => id !== lineItemId));
            setUpdateStatus(prev => {
                const newStatus = { ...prev };
                delete newStatus[lineItemId];
                return newStatus;
            });
        } catch (err) {
            setError(err.response?.data?.message || 'Rollback failed');
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Preview & Update</h2>}
        >
            <Head title="Preview & Update">
                <style>{`
                    .btn, .btn-outline {
                        text-transform: none;
                    }
                `}</style>
            </Head>

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            {error && <ErrorLog error={error} />}
                            
                            {loading && !batchId ? (
                                <LoadingSpinner text="Preparing update..." />
                            ) : batchId ? (
                                <div className="mb-6">
                                    <h3 className="text-lg font-semibold mb-4">Update Progress</h3>
                                    <BulkUpdateProgress 
                                        batchId={batchId} 
                                        onComplete={handleUpdateComplete} 
                                    />
                                </div>
                            ) : null}

                            {mappedData && !batchId && !loading ? (
                                <>
                                    <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                                        <div className="flex">
                                            <div className="flex-shrink-0">
                                                <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                            <div className="ml-3">
                                                <p className="text-sm text-yellow-700">
                                                    Please review the mapped data below before proceeding with the update.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mb-6">
                                        <h3 className="text-lg font-semibold mb-4">Preview Data</h3>
                                        <LineItemTable 
                                            data={mappedData} 
                                            selectable={true}
                                            onSelectionChange={setSelectedItems}
                                        />
                                    </div>

                                    <div className="flex justify-between mt-6">
                                        <button
                                            onClick={() => router.visit(route('line-items.upload'))}
                                            className="btn btn-outline"
                                        >
                                            Back to Upload
                                        </button>
                                        <button
                                            onClick={handleUpdate}
                                            disabled={loading || selectedItems.length === 0}
                                            className="btn btn-primary"
                                        >
                                            {loading ? 'Processing...' : `Update ${selectedItems.length} Line Items`}
                                        </button>
                                    </div>
                                </>
                            ) : !loading && !batchId && (
                                <div className="text-center py-12">
                                    <p className="text-gray-500">
                                        No data available. Please upload and map a CSV file first.
                                    </p>
                                    <button
                                        onClick={() => router.visit(route('line-items.upload'))}
                                        className="btn btn-primary mt-4"
                                    >
                                        Go to Upload
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 