import React from 'react';
import { useState, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FileUpload from '@/Components/FileUpload';
import ErrorLog from '@/Components/ErrorLog';
import LoadingSpinner from '@/Components/LoadingSpinner';
import LineItemTable from '@/Components/LineItemTable';
import axios from 'axios';
import SearchableLabels from '@/Components/SearchableLabels';
import SearchableAdUnits from '@/Components/SearchableAdUnits';
import SearchablePlacements from '@/Components/SearchablePlacements';
import SearchableCustomTargeting from '@/Components/SearchableCustomTargeting';
import DeviceCategorySelector from '@/Components/DeviceCategorySelector';
import DayPartSelector from '@/Components/DayPartSelector';
import SearchableGeoTargeting from '@/Components/SearchableGeoTargeting';
import TargetingCriteria from '@/Components/TargetingCriteria';
import BulkUpdateProgress from '@/Components/BulkUpdateProgress';

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

export default function StaticUpdate({ auth }) {
    const [selectedIds, setSelectedIds] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState(false);
    const [step, setStep] = useState('input'); // input, preview, update
    const [csvData, setCsvData] = useState(null);
    const [selectedItems, setSelectedItems] = useState([]);
    const [lineItemTypes, setLineItemTypes] = useState({}); // Store line item types
    const [selectedLabels, setSelectedLabels] = useState([]);
    const [selectedIncludedAdUnits, setSelectedIncludedAdUnits] = useState([]);
    const [selectedExcludedAdUnits, setSelectedExcludedAdUnits] = useState([]);
    const [selectedIncludedPlacements, setSelectedIncludedPlacements] = useState([]);
    const [selectedExcludedPlacements, setSelectedExcludedPlacements] = useState([]);
    const [selectedPlacements, setSelectedPlacements] = useState([]);
    const [selectedCustomTargeting, setSelectedCustomTargeting] = useState([]);
    const [selectedAudienceSegments, setSelectedAudienceSegments] = useState([]);
    const [selectedCmsMetadata, setSelectedCmsMetadata] = useState([]);
    const [selectedDeviceCategories, setSelectedDeviceCategories] = useState([]);
    const [selectedDayParts, setSelectedDayParts] = useState([]);
    const [selectedIncludeLocations, setSelectedIncludeLocations] = useState([]);
    const [selectedExcludeLocations, setSelectedExcludeLocations] = useState([]);
    const [batchId, setBatchId] = useState(null);
    const [progress, setProgress] = useState(0);
    const [changedFields, setChangedFields] = useState(new Set());

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
        device_category_targeting: '',
        line_item_type: ''
    });

    // Initialize selectedCustomTargeting from updates.custom_targeting
    useEffect(() => {
        if (updates.custom_targeting) {
            const pairs = updates.custom_targeting.split(',').filter(pair => pair.includes('='));
            const targeting = pairs.map(pair => {
                const [key, value] = pair.split('=');
                return { key: key.trim(), value: value.trim() };
            });
            setSelectedCustomTargeting(targeting);
        }
        
        // Initialize selectedDeviceCategories from updates.device_category_targeting
        if (updates.device_category_targeting) {
            const deviceIds = updates.device_category_targeting.split(',').map(d => d.trim());
            const devices = deviceIds.map(id => {
                const name = {
                    'DESKTOP': 'Desktop',
                    'MOBILE': 'Mobile',
                    'TABLET': 'Tablet',
                    'CONNECTED_TV': 'Connected TV',
                    'SET_TOP_BOX': 'Set-top Box'
                }[id] || id;
                
                return { id, name };
            });
            setSelectedDeviceCategories(devices);
        }
        
        // Initialize selectedDayParts from updates.day_part_targeting
        if (updates.day_part_targeting) {
            try {
                const dayPartEntries = updates.day_part_targeting.split(';');
                const parsedDayParts = dayPartEntries.map(entry => {
                    const parts = entry.split(',');
                    
                    // Check if we have at least 3 parts (days, startTime, endTime)
                    if (parts.length < 3) return null;
                    
                    // The first parts until the last 4 are days
                    const days = parts.slice(0, parts.length - 4);
                    const startTime = parts[parts.length - 4];
                    const endTime = parts[parts.length - 3];
                    const timeZone = parts[parts.length - 2];
                    const inclusion = parts[parts.length - 1];
                    
                    return {
                        days,
                        startTime,
                        endTime,
                        usePublisherTimeZone: timeZone === 'PUBLISHER',
                        dontRunOnTheseDays: inclusion === 'EXCLUDE'
                    };
                }).filter(Boolean); // Remove any null entries
                
                if (parsedDayParts.length > 0) {
                    setSelectedDayParts(parsedDayParts);
                }
            } catch (error) {
                console.error('Failed to parse day part targeting:', error);
            }
        }
    }, []);

    // Validate priority based on line item type
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

    // Validate impression goals based on line item type
    const validateImpressionGoals = (goals, lineItemType) => {
        if (!lineItemType || !goals) return true;
        
        if (lineItemType === 'SPONSORSHIP') {
            const value = parseInt(goals);
            return value >= 0 && value <= 100;
        }
        return true;
    };

    // Generate priority options based on line item type
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

    const handleFileUpload = async (file) => {
        setLoading(true);
        setError(null);

        const formData = new FormData();
        formData.append('csv', file);

        try {
            const response = await axios.post('/line-items/upload', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            if (response.data.status === 'success' && response.data.data) {
                // Validate that we have line_item_id in the data
                const validData = response.data.data.filter(row => row && row.line_item_id);
                
                if (validData.length === 0) {
                    throw new Error('No valid line item IDs found in CSV. Please ensure your CSV has a "line_item_id" column.');
                }

                if (validData.length !== response.data.data.length) {
                    setError(`Warning: Only ${validData.length} out of ${response.data.data.length} rows had valid line item IDs.`);
                }

                // Format the data for the table, preserving priority and impression_goals if they exist
                const formattedData = validData.map(row => {
                    const item = {
                    line_item_id: row.line_item_id,
                    line_item_name: row.line_item_name || row.line_item_id,
                        line_item_type: row.line_item_type // Store line item type
                    };
                    
                    // Store line item type in state
                    setLineItemTypes(prev => ({
                        ...prev,
                        [row.line_item_id]: row.line_item_type
                    }));
                    
                    // Preserve priority and impression_goals from CSV if they exist
                    if (row.priority) {
                        // Validate priority based on line item type
                        if (!validatePriority(row.priority, row.line_item_type)) {
                            throw new Error(`Invalid priority ${row.priority} for line item type ${row.line_item_type} (ID: ${row.line_item_id})`);
                        }
                        item.priority = row.priority;
                        setUpdates(prev => ({...prev, priority: row.priority, line_item_type: row.line_item_type}));
                    }
                    
                    if (row.impression_goals) {
                        // Validate impression goals for sponsorship line items
                        if (!validateImpressionGoals(row.impression_goals, row.line_item_type)) {
                            throw new Error(`Invalid impression goals ${row.impression_goals} for SPONSORSHIP line item (ID: ${row.line_item_id}). Must be between 0 and 100.`);
                        }
                        item.impression_goals = row.impression_goals;
                        setUpdates(prev => ({...prev, impression_goals: row.impression_goals}));
                    }
                    
                    return item;
                });

                console.log('Formatted data from CSV:', formattedData);

                // Extract line item IDs from CSV data
                const ids = formattedData.map(row => row.line_item_id).join(', ');
                setSelectedIds(ids);
                setCsvData(formattedData);
                setStep('preview');
            } else {
                throw new Error(response.data.message || 'Failed to process CSV file');
            }
        } catch (err) {
            setError(err.response?.data?.message || err.message || 'An error occurred while uploading the file');
            // Reset state on error
            setSelectedIds('');
            setCsvData(null);
        } finally {
            setLoading(false);
        }
    };

    const handleTargetingCriteriaChange = (data) => {
        try {
            // Validate input data
            if (!data || typeof data !== 'object') {
                console.error('Invalid targeting data received:', data);
                return;
            }

            console.log('Received targeting data:', data);

            switch (data.type) {
                case 'custom':
                    if (Array.isArray(data.targeting)) {
                        const formattedTargeting = data.targeting.map(item => ({
                            key: String(item.key || ''),
                            value: String(item.value || ''),
                            operator: String(item.operator || 'IS'),
                            displayValue: `${item.key || ''} ${item.operator || 'IS'} ${item.value || ''}`
                        }));

                        console.log('Formatted custom targeting:', formattedTargeting);
                        setSelectedCustomTargeting(formattedTargeting);
                        setChangedFields(prev => new Set(prev).add('custom_targeting'));
                    }
                    break;

                case 'audience':
                    if (Array.isArray(data.segments)) {
                        const formattedSegments = data.segments.map(item => ({
                            id: item.id,
                            name: String(item.name || ''),
                            operator: String(item.operator || 'IS_ANY_OF'),
                            displayValue: `${item.name} ${item.operator === 'IS_ANY_OF' ? 'is any of' : 'is none of'}`
                        }));

                        console.log('Formatted audience segments:', formattedSegments);
                        setSelectedAudienceSegments(formattedSegments);
                        setChangedFields(prev => new Set(prev).add('audience_segments'));
                    }
                    break;

                case 'cms':
                    if (Array.isArray(data.metadata)) {
                        const formattedMetadata = data.metadata.map(item => ({
                            key: String(item.key || ''),
                            value: String(item.value || ''),
                            operator: String(item.operator || 'IS'),
                            displayValue: `${item.key || ''} ${item.operator || 'IS'} ${item.value || ''}`
                        }));

                        console.log('Formatted CMS metadata:', formattedMetadata);
                        setSelectedCmsMetadata(formattedMetadata);
                        setChangedFields(prev => {
                            const newFields = new Set(prev);
                            newFields.add('cms_metadata');
                            console.log('Updated changedFields with cms_metadata:', Array.from(newFields));
                            return newFields;
                        });
                        
                        // Log the state update
                        console.log('Setting CMS metadata state:', formattedMetadata);
                        
                        // Add to updates object
                        setUpdates(prev => {
                            const newUpdates = {
                                ...prev,
                                cms_metadata: formattedMetadata
                            };
                            console.log('Updated updates object with CMS metadata:', newUpdates);
                            return newUpdates;
                        });
                    }
                    break;

                default:
                    console.warn('Unknown targeting type:', data.type);
            }
        } catch (error) {
            console.error('Error in handleTargetingCriteriaChange:', error);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);
        setBatchId(null);
        setProgress(0);

        try {
            console.log('Starting static update submission...');
            // Create a temporary batch ID for progress tracking
            const tempBatchId = `temp_${Date.now()}`;
            setBatchId(tempBatchId);

            // Get line item IDs from selected items if available, otherwise from selectedIds
            const lineItems = selectedItems.length > 0 
                ? selectedItems
                : selectedIds.split(',').map(id => ({
                    line_item_id: id.trim(),
                    line_item_name: id.trim()
                }));
            
            console.log('Line items to update:', lineItems);
            
            if (lineItems.length === 0) {
                throw new Error('No line items selected for update');
            }

            // Create update payload for each line item
            const payload = {
                line_items: lineItems.map(item => {
                    console.log(`Creating payload for line item ${item.line_item_id}...`);
                    // Start with the line item ID and name
                    const updateData = {
                        line_item_id: item.line_item_id,
                        line_item_name: item.line_item_name || item.line_item_id
                    };

                    // Use values from the item if they exist, otherwise use form values
                    // Budget
                    if (item.budget !== undefined) {
                        updateData.budget = String(item.budget);
                        console.log(`Setting budget from item: ${updateData.budget}`);
                    } else if (updates.budget) {
                        updateData.budget = String(updates.budget);
                        console.log(`Setting budget from form: ${updateData.budget}`);
                    }
                    
                    // Priority - validate against line item type
                    if (item.priority !== undefined) {
                        if (!validatePriority(item.priority, lineItemTypes[item.line_item_id])) {
                            throw new Error(`Invalid priority ${item.priority} for line item type ${lineItemTypes[item.line_item_id]} (ID: ${item.line_item_id})`);
                        }
                        updateData.priority = String(item.priority);
                        console.log(`Setting priority from item: ${updateData.priority}`);
                    } else if (updates.priority) {
                        // Validate for all selected items
                        for (const selectedItem of lineItems) {
                            if (!validatePriority(updates.priority, lineItemTypes[selectedItem.line_item_id])) {
                                throw new Error(`Invalid priority ${updates.priority} for line item type ${lineItemTypes[selectedItem.line_item_id]} (ID: ${selectedItem.line_item_id})`);
                            }
                        }
                        updateData.priority = String(updates.priority);
                        console.log(`Setting priority from form: ${updateData.priority}`);
                    }
                    
                    // Status
                    if (updates.status) {
                        updateData.status = updates.status;
                        console.log(`Setting status: ${updateData.status}`);
                    }

                    // Line Item Type
                    if (updates.line_item_type) {
                        updateData.line_item_type = updates.line_item_type;
                        console.log(`Setting line item type: ${updateData.line_item_type}`);
                    }

                    // Impression Goals
                    if (updates.impression_goals) {
                        updateData.impression_goals = String(updates.impression_goals);
                        console.log(`Setting impression goals: ${updateData.impression_goals}`);
                    }

                    // Custom targeting
                    if (selectedCustomTargeting.length > 0) {
                        updateData.custom_targeting = selectedCustomTargeting.map(target => ({
                            key: target.key,
                            value: target.value,
                            operator: target.operator
                        }));
                        console.log('Setting custom targeting:', updateData.custom_targeting);
                    }

                    // Device category targeting
                    if (selectedDeviceCategories.length > 0) {
                        updateData.device_category_targeting = selectedDeviceCategories;
                        console.log('Setting device category targeting:', updateData.device_category_targeting);
                    }

                    // Day part targeting
                    if (selectedDayParts.length > 0) {
                        updateData.day_part_targeting = selectedDayParts;
                        console.log('Setting day part targeting:', updateData.day_part_targeting);
                    }

                    // Audience segments
                    if (selectedAudienceSegments.length > 0) {
                        updateData.audience_segments = selectedAudienceSegments.map(segment => ({
                            id: segment.id,
                            name: segment.name,
                            operator: segment.operator
                        }));
                        console.log('Setting audience segments:', updateData.audience_segments);
                    }

                    // CMS metadata
                    if (selectedCmsMetadata.length > 0) {
                        updateData.cms_metadata = selectedCmsMetadata.map(metadata => ({
                            key: metadata.key,
                            value: metadata.value,
                            operator: metadata.operator
                        }));
                        console.log('Adding CMS metadata to payload:', updateData.cms_metadata);
                    }

                    console.log(`Final update data for line item ${item.line_item_id}:`, updateData);
                    return updateData;
                })
            };

            console.log('Sending final update payload:', payload);

            const response = await axios.post('/line-items/bulk-update', payload);
            console.log('Update response:', response.data);
            
            if (response.data.status === 'success' && response.data.results?.batch_id) {
                console.log('Update successful, batch ID:', response.data.results.batch_id);
                setSuccess(true);
                // Replace temporary batch ID with real one
                setBatchId(response.data.results.batch_id);
                // Set loading to false once we have the batch ID
                setLoading(false);
            } else {
                throw new Error(response.data.message || 'Failed to update line items');
            }
        } catch (err) {
            console.error('Update error:', err);
            console.error('Error details:', {
                message: err.message,
                response: err.response?.data,
                stack: err.stack
            });
            setError(err.response?.data?.message || err.message || 'An error occurred');
            setLoading(false);
            setBatchId(null);
        }
    };

    // Handle completion of bulk update
    const handleUpdateComplete = () => {
        // Don't redirect automatically
        setSuccess(true);
        setLoading(false);
        
        // Reset form data
        setSelectedIds('');
        setSelectedItems([]);
        setCsvData(null);
        setSelectedCustomTargeting([]);
        setSelectedAudienceSegments([]);
        setSelectedCmsMetadata([]);
        setSelectedDeviceCategories([]);
        setSelectedDayParts([]);
        setSelectedIncludeLocations([]);
        setSelectedExcludeLocations([]);
        setChangedFields(new Set());
        setUpdates({
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
            frequency_cap_max: '',
            frequency_cap_time_units: '',
            frequency_cap_time_unit_type: '',
            geo_targeting_included: '',
            geo_targeting_excluded: '',
            inventory_targeting_included: '',
            inventory_targeting_excluded: '',
            custom_targeting: '',
            day_part_targeting: '',
            device_category_targeting: '',
            line_item_type: ''
        });
    };

    const handlePreviewSubmit = () => {
        // Clear any existing errors
        setError(null);
        
        // Debug log the current state
        console.log('Current updates state:', updates);
        console.log('Selected items:', selectedItems);
        console.log('Selected IDs:', selectedIds);
        
        // Ensure we have selected items
        if (selectedItems.length === 0 && !selectedIds.trim()) {
            setError('Please enter at least one line item ID');
            return;
        }

        // Check which fields have been updated
        const updatedFields = Object.entries(updates).filter(([key, value]) => {
            // Include if value is present (including boolean false and number 0)
            return value !== null && value !== undefined && value !== '';
        });

        console.log('Updated fields:', updatedFields);

        // Validate that we have at least one update field filled
        if (updatedFields.length === 0 && 
            !selectedCustomTargeting.length && 
            !selectedDeviceCategories.length && 
            !selectedDayParts.length && 
            !selectedLabels.length && 
            !selectedIncludeLocations.length && 
            !selectedExcludeLocations.length) {
            setError('Please specify at least one field to update');
            return;
        }
        
        try {
            // Validate priority and impression goals for all selected items
            if (updates.priority) {
                for (const item of selectedItems) {
                    if (!validatePriority(updates.priority, lineItemTypes[item.line_item_id])) {
                        throw new Error(`Invalid priority ${updates.priority} for line item type ${lineItemTypes[item.line_item_id]} (ID: ${item.line_item_id})`);
                    }
                }
            }

            if (updates.impression_goals) {
                for (const item of selectedItems) {
                    if (!validateImpressionGoals(updates.impression_goals, lineItemTypes[item.line_item_id])) {
                        throw new Error(`Invalid impression goals ${updates.impression_goals} for SPONSORSHIP line item (ID: ${item.line_item_id}). Must be between 0 and 100.`);
                    }
                }
        }
        
            // Log final state before proceeding
            console.log('Proceeding to update step with updates:', updates);
        
        // Proceed to update step
        setStep('update');
        } catch (err) {
            setError(err.message);
        }
    };

    const handleLabelsChange = (newLabels) => {
        setSelectedLabels(newLabels);
        setChangedFields(prev => new Set(prev).add('labels'));
        setUpdates(prev => ({
            ...prev,
            labels: newLabels.map(label => label.id).join(',')
        }));
    };

    const handleAdUnitsChange = (newAdUnits, type = 'include') => {
        if (type === 'include') {
            setSelectedIncludedAdUnits(newAdUnits.map(unit => ({
                ...unit,
                name: `${unit.name}\n${unit.path}`
            })));
            setChangedFields(prev => new Set(prev).add('inventory_targeting_included'));
            setUpdates(prev => ({
                ...prev,
                inventory_targeting_included: JSON.stringify(newAdUnits.map(unit => ({
                    id: unit.id,
                    name: unit.displayName || unit.name,
                    path: unit.path
                })))
            }));
        } else {
            setSelectedExcludedAdUnits(newAdUnits.map(unit => ({
                ...unit,
                name: `${unit.name}\n${unit.path}`
            })));
            setChangedFields(prev => new Set(prev).add('inventory_targeting_excluded'));
            setUpdates(prev => ({
                ...prev,
                inventory_targeting_excluded: JSON.stringify(newAdUnits.map(unit => ({
                    id: unit.id,
                    name: unit.displayName || unit.name,
                    path: unit.path
                })))
            }));
        }
    };

    const handlePlacementsChange = (newPlacements, type = 'include') => {
        if (type === 'include') {
            setSelectedIncludedPlacements(newPlacements);
            setChangedFields(prev => new Set(prev).add('placements_included'));
            setUpdates(prev => ({
                ...prev,
                placements_included: JSON.stringify(newPlacements.map(placement => ({
                    id: placement.id,
                    name: placement.name
                })))
            }));
        } else {
            setSelectedExcludedPlacements(newPlacements);
            setChangedFields(prev => new Set(prev).add('placements_excluded'));
            setUpdates(prev => ({
                ...prev,
                placements_excluded: JSON.stringify(newPlacements.map(placement => ({
                    id: placement.id,
                    name: placement.name
                })))
            }));
        }
    };

    const handleUpdate = async () => {
        if (!hasUpdates()) {
            setError('Please specify at least one field to update');
            return;
        }

        setLoading(true);
        setError(null);
        setProgress(0);

        try {
            // Simulate progress updates
            const progressInterval = setInterval(() => {
                setProgress(prev => Math.min(prev + 10, 90));
            }, 500);

            const response = await axios.post(route('line-items.update'), {
                line_items: selectedLineItems,
                updates: getUpdates()
            });

            clearInterval(progressInterval);
            setProgress(100);
            setSuccess(true);
        } catch (err) {
            console.error('Update error:', err);
            setError(err.response?.data?.message || err.message || 'An error occurred');
            setProgress(0);
        } finally {
            setLoading(false);
        }
    };

    const clearLineItems = () => {
        setSelectedIds('');
        setSelectedItems([]);
        setError('');
        setSuccess(false);
        setStep('input');
    };

    const handleClearClick = () => {
        clearLineItems();
    };

    const handleFieldChange = (fieldName, value) => {
        console.log(`Updating field ${fieldName} with value:`, value);
        
        // Only update changedFields if the value is not empty
        if (value !== null && value !== undefined && value !== '') {
            setChangedFields(prev => {
                const newChangedFields = new Set(prev);
                newChangedFields.add(fieldName);
                console.log('Updated changedFields:', Array.from(newChangedFields));
                return newChangedFields;
            });
        } else {
            // Remove from changedFields if the value is empty
            setChangedFields(prev => {
                const newChangedFields = new Set(prev);
                newChangedFields.delete(fieldName);
                console.log('Removed from changedFields:', fieldName);
                return newChangedFields;
            });
        }

        setUpdates(prev => {
            const newUpdates = { ...prev, [fieldName]: value };
            console.log('New updates state:', newUpdates);
            return newUpdates;
        });
    };

    return (
        <AuthenticatedLayout
            auth={auth}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Static Line Item Update</h2>}
        >
            <Head title="Static Line Item Update" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        {error && <ErrorLog error={error} />}
                        <div className="mb-8">
                            <ul className="steps w-full">
                                <li className={`step ${step === 'input' ? 'step-primary' : ''}`}>Input Line Items</li>
                                <li className={`step ${step === 'preview' ? 'step-primary' : ''}`}>Preview</li>
                                <li className={`step ${step === 'update' ? 'step-primary' : ''}`}>Update</li>
                            </ul>
                        </div>

                        {error && (
                            <div className="alert alert-error mt-6" data-testid="error-message">
                                <span>{error}</span>
                            </div>
                        )}

                        {loading && !batchId ? (
                            <LoadingSpinner text="Preparing update..." />
                        ) : batchId ? (
                            <div className="mt-6">
                                <h3 className="text-lg font-semibold mb-4">Update Progress</h3>
                                <BulkUpdateProgress 
                                    batchId={batchId} 
                                    onComplete={handleUpdateComplete} 
                                />
                            </div>
                        ) : null}

                        {step === 'input' && !loading && (
                            <div className="space-y-6">
                                <div className="mb-6">
                                    <h3 className="text-lg font-medium text-gray-900">Enter Line Item IDs</h3>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Enter line item IDs manually or upload a CSV file containing the IDs.
                                    </p>
                                </div>
                                {error && <ErrorLog error={error} />}
                                <div className="form-control">
                                    <label className="label">
                                        <span className="label-text">Line Item IDs</span>
                                        <button 
                                            className="btn btn-ghost btn-xs"
                                            onClick={handleClearClick}
                                            data-testid="clear-button"
                                        >
                                            Clear
                                        </button>
                                    </label>
                                    <textarea
                                        className="textarea textarea-bordered w-full border-black h-24"
                                        placeholder="Enter line item IDs separated by commas"
                                        value={selectedIds}
                                        onChange={(e) => setSelectedIds(e.target.value)}
                                    />
                                    <label className="label">
                                        <span className="label-text-alt">Example: 123456, 789012, 345678</span>
                                    </label>
                                </div>

                                {/* CSV Upload */}
                                <div className="divider">OR</div>

                                <div>
                                    <div className="flex justify-between items-center mb-4">
                                        <h4 className="font-medium">Upload CSV File</h4>
                                        <a
                                            href="/line-items/static-sample-csv"
                                            className="btn btn-outline btn-sm no-animation"
                                            download
                                        >
                                            Download Sample CSV
                                        </a>
                                    </div>
                                    <div className="bg-gray-50 p-4 rounded-lg mb-4">
                                        <h5 className="font-medium mb-2">CSV Format Guidelines:</h5>
                                        <ul className="list-disc list-inside text-sm text-gray-600 space-y-1">
                                            <li>First row should contain column headers</li>
                                            <li>Required column: Line Item ID</li>
                                            <li>Optional column: Line Item Name</li>
                                            <li>Each row represents a different line item</li>
                                        </ul>
                                    </div>
                                    <FileUpload 
                                        onUpload={handleFileUpload}
                                        loading={loading}
                                    />
                                </div>

                                {/* Continue Button */}
                                <button
                                    onClick={() => selectedIds.trim() && setStep('preview')}
                                    className="btn btn-primary w-full"
                                    disabled={!selectedIds.trim()}
                                >
                                    Continue to Preview
                                </button>
                            </div>
                        )}

                        {step === 'preview' && (
                            <div className="space-y-6">
                                <div className="mb-6">
                                    <h3 className="text-lg font-medium text-gray-900">Preview Line Items</h3>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Review the line items and specify the updates to apply.
                                    </p>
                                </div>
                                {error && <ErrorLog error={error} />}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">Selected Line Item IDs</span>
                                        </label>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <p className="text-sm text-gray-600">
                                                {selectedIds.split(',').map(id => id.trim()).join(', ')}
                                            </p>
                                        </div>
                                    </div>

                                <div className="divider">Update Fields</div>

                                {/* Update Fields */}
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {/* Status */}
                                    <div className="form-control">
                                        <label className="label" htmlFor="status">
                                            <span className="label-text">Status</span>
                                        </label>
                                        <select
                                            id="status"
                                            className="select select-bordered w-full border-black"
                                            value={updates.status || ''}
                                            onChange={(e) => handleFieldChange('status', e.target.value)}
                                        >
                                            <option value="">Select Status</option>
                                            <option value="PAUSED">Pause</option>
                                            <option value="DELIVERING">Resume</option>
                                            <option value="ARCHIVED">Archive</option>
                                        </select>
                                    </div>

                                    {/* Line Item Type */}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">Line Item Type</span>
                                        </label>
                                        <select
                                            className="select select-bordered w-full border-black"
                                            value={updates.line_item_type || ''}
                                            onChange={(e) => {
                                                const newType = e.target.value;
                                                handleFieldChange('line_item_type', newType);
                                                    // Reset priority when type changes
                                                handleFieldChange('priority', '');
                                            }}
                                        >
                                            <option value="">Select Type</option>
                                            <option value="SPONSORSHIP">Sponsorship</option>
                                            <option value="STANDARD">Standard</option>
                                            <option value="NETWORK">Network</option>
                                            <option value="BULK">Bulk</option>
                                            <option value="PRICE_PRIORITY">Price Priority</option>
                                            <option value="HOUSE">House</option>
                                            <option value="BUMPER">Bumper</option>
                                        </select>
                                    </div>

                                    {/* Budget */}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">Budget</span>
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="input input-bordered w-full border-black"
                                            value={updates.budget || ''}
                                            onChange={(e) => handleFieldChange('budget', e.target.value)}
                                        />
                                    </div>

                                    {/* Priority */}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">Priority</span>
                                        </label>
                                        <select
                                            className="select select-bordered w-full border-black"
                                            value={updates.priority || ''}
                                            onChange={(e) => handleFieldChange('priority', e.target.value)}
                                            disabled={!updates.line_item_type}
                                        >
                                            <option value="">Select Priority</option>
                                            {getPriorityOptions(updates.line_item_type).map(option => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {/* Impression Goals */}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">
                                                Impression Goals
                                                {updates.line_item_type === 'SPONSORSHIP' && ' (0-100%)'}
                                            </span>
                                        </label>
                                        <input
                                            type="number"
                                            className="input input-bordered w-full border-black"
                                            value={updates.impression_goals || ''}
                                            onChange={(e) => handleFieldChange('impression_goals', e.target.value)}
                                            min={updates.line_item_type === 'SPONSORSHIP' ? 0 : undefined}
                                            max={updates.line_item_type === 'SPONSORSHIP' ? 100 : undefined}
                                        />
                                    </div>

                                    {/* Start Date/Time */}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">Start Date/Time</span>
                                        </label>
                                        <input
                                            type="datetime-local"
                                            className="input input-bordered w-full border-black"
                                            value={updates.start_date_time}
                                            onChange={(e) => handleFieldChange('start_date_time', e.target.value)}
                                        />
                                    </div>

                                    {/* End Date/Time */}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">End Date/Time</span>
                                        </label>
                                        <input
                                            type="datetime-local"
                                            className="input input-bordered w-full border-black"
                                            value={updates.end_date_time}
                                            onChange={(e) => handleFieldChange('end_date_time', e.target.value)}
                                            disabled={updates.unlimited_end_date}
                                        />
                                        <label className="label cursor-pointer">
                                            <span className="label-text">Unlimited End Date</span>
                                            <input
                                                type="checkbox"
                                                className="checkbox border-black"
                                                checked={updates.unlimited_end_date}
                                                onChange={(e) => {
                                                    handleFieldChange('unlimited_end_date', e.target.checked);
                                                    if (e.target.checked) {
                                                        handleFieldChange('end_date_time', '');
                                                    }
                                                }}
                                            />
                                        </label>
                                    </div>

                                    {/* Delivery Rate Type */}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">Delivery Rate Type</span>
                                        </label>
                                        <select
                                            className="select select-bordered w-full border-black"
                                            value={updates.delivery_rate_type}
                                            onChange={(e) => handleFieldChange('delivery_rate_type', e.target.value)}
                                        >
                                            <option value="">Select Delivery Rate</option>
                                            <option value="EVENLY">Evenly</option>
                                            <option value="FRONTLOADED">Frontloaded</option>
                                            <option value="AS_FAST_AS_POSSIBLE">As Fast As Possible</option>
                                        </select>
                                    </div>

                                    {/* Cost Type and Cost Per Unit side by side */}
                                    <div className="col-span-2 grid grid-cols-2 gap-4">
                                        {/* Cost Type */}
                                        <div className="form-control">
                                            <label className="label">
                                                <span className="label-text">Cost Type</span>
                                            </label>
                                            <select
                                                className="select select-bordered w-full border-black"
                                                value={updates.cost_type}
                                                onChange={(e) => handleFieldChange('cost_type', e.target.value)}
                                            >
                                                <option value="">Select Cost Type</option>
                                                <option value="CPM">CPM</option>
                                                <option value="CPC">CPC</option>
                                                <option value="CPD">CPD</option>
                                                <option value="VCPM">vCPM</option>
                                                <option value="FLAT_RATE">Flat Rate</option>
                                            </select>
                                        </div>

                                        {/* Cost Per Unit */}
                                        <div className="form-control">
                                            <label className="label">
                                                <span className="label-text">Cost Per Unit</span>
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                className="input input-bordered w-full border-black"
                                                value={updates.cost_per_unit}
                                                onChange={(e) => handleFieldChange('cost_per_unit', e.target.value)}
                                                placeholder="Enter cost per unit"
                                            />
                                        </div>
                                    </div>

                                    {/* Labels */}
                                    <div className="form-control">
                                        <label className="label">
                                            <span className="label-text">Labels</span>
                                        </label>
                                        <SearchableLabels
                                            selectedLabels={selectedLabels}
                                            onChange={handleLabelsChange}
                                        />
                                    </div>

                                    {/* Frequency Cap Settings */}
                                    <div className="col-span-full">
                                        <h4 className="text-lg font-medium mb-4">Frequency Cap Settings</h4>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div className="form-control">
                                                <label className="label">
                                                    <span className="label-text">Max Impressions</span>
                                                </label>
                                                <input
                                                    type="number"
                                                    className="input input-bordered w-full border-black"
                                                    value={updates.frequency_cap_max}
                                                    onChange={(e) => handleFieldChange('frequency_cap_max', e.target.value)}
                                                    placeholder="Enter max impressions"
                                                    min="1"
                                                />
                                            </div>

                                            <div className="form-control">
                                                <label className="label">
                                                    <span className="label-text">Time Units</span>
                                                </label>
                                                <input
                                                    type="number"
                                                    className="input input-bordered w-full border-black"
                                                    value={updates.frequency_cap_time_units}
                                                    onChange={(e) => handleFieldChange('frequency_cap_time_units', e.target.value)}
                                                    placeholder="Enter number of time units"
                                                    min="1"
                                                />
                                            </div>

                                            <div className="form-control">
                                                <label className="label">
                                                    <span className="label-text">Time Unit Type</span>
                                                </label>
                                                <select
                                                    className="select select-bordered w-full border-black"
                                                    value={updates.frequency_cap_time_unit_type}
                                                    onChange={(e) => handleFieldChange('frequency_cap_time_unit_type', e.target.value)}
                                                >
                                                    <option value="">Select Time Unit</option>
                                                    <option value="MINUTE">Minute</option>
                                                    <option value="HOUR">Hour</option>
                                                    <option value="DAY">Day</option>
                                                    <option value="WEEK">Week</option>
                                                    <option value="MONTH">Month</option>
                                                    <option value="PODS">Pods</option>
                                                    <option value="STREAM">Stream</option>
                                                    <option value="LIFETIME">Lifetime</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Targeting Settings */}
                                    <div className="col-span-full">
                                        <h4 className="text-lg font-medium mb-4">Targeting Settings</h4>
                                        
                                        {/* Day Part Targeting */}
                                        <div className="form-control mb-6">
                                            <DayPartSelector
                                                selectedDayParts={selectedDayParts}
                                                onChange={(newDayParts) => {
                                                    setSelectedDayParts(newDayParts);
                                                    setChangedFields(prev => new Set(prev).add('day_part_targeting'));
                                                    setUpdates(prev => ({
                                                        ...prev,
                                                        day_part_targeting: newDayParts.map(dp => 
                                                            `${dp.days.join(',')},${dp.startTime},${dp.endTime},${dp.usePublisherTimeZone ? 'PUBLISHER' : 'USER'},${dp.dontRunOnTheseDays ? 'EXCLUDE' : 'INCLUDE'}`
                                                        ).join(';')
                                                    }));
                                                }}
                                            />
                                        </div>

                                        {/* Inventory Targeting */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                            <div>
                                                <h5 className="font-medium mb-4">Include Ad Units/Placements</h5>
                                                <div className="space-y-4">
                                                    <div className="form-control">
                                                        <label className="label">
                                                            <span className="label-text">Ad Units</span>
                                                        </label>
                                                        <SearchableAdUnits
                                                            selectedAdUnits={selectedIncludedAdUnits}
                                                            onChange={(newAdUnits) => handleAdUnitsChange(newAdUnits, 'include')}
                                                        />
                                                    </div>
                                                    <div className="form-control">
                                                        <label className="label">
                                                            <span className="label-text">Placements</span>
                                                        </label>
                                                        <SearchablePlacements
                                                            selectedPlacements={selectedIncludedPlacements}
                                                            onChange={(newPlacements) => handlePlacementsChange(newPlacements, 'include')}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <h5 className="font-medium mb-4">Exclude Ad Units/Placements</h5>
                                                <div className="space-y-4">
                                                    <div className="form-control">
                                                        <label className="label">
                                                            <span className="label-text">Ad Units</span>
                                                        </label>
                                                        <SearchableAdUnits
                                                            selectedAdUnits={selectedExcludedAdUnits}
                                                            onChange={(newAdUnits) => handleAdUnitsChange(newAdUnits, 'exclude')}
                                                        />
                                                    </div>
                                                    <div className="form-control">
                                                        <label className="label">
                                                            <span className="label-text">Placements</span>
                                                        </label>
                                                        <SearchablePlacements
                                                            selectedPlacements={selectedExcludedPlacements}
                                                            onChange={(newPlacements) => handlePlacementsChange(newPlacements, 'exclude')}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Custom Targeting */}
                                        <div className="col-span-full">
                                            <TargetingCriteria
                                                selectedTargeting={selectedCustomTargeting}
                                                selectedAudienceSegments={selectedAudienceSegments}
                                                selectedCmsMetadata={selectedCmsMetadata}
                                                onChange={handleTargetingCriteriaChange}
                                            />
                                        </div>

                                        {/* Geographic Targeting */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                            <div className="form-control">
                                                <label className="label">
                                                    <span className="label-text">Include Locations</span>
                                                </label>
                                                <SearchableGeoTargeting
                                                    selectedLocations={selectedIncludeLocations}
                                                    onChange={(locations) => {
                                                        setSelectedIncludeLocations(locations);
                                                        setChangedFields(prev => new Set(prev).add('geo_targeting_included'));
                                                        setUpdates(prev => ({
                                                            ...prev,
                                                            geo_targeting_included: locations.map(loc => loc.id).join(',')
                                                        }));
                                                    }}
                                                    isExcluded={false}
                                                />
                                            </div>
                                            <div className="form-control">
                                                <label className="label">
                                                    <span className="label-text">Exclude Locations</span>
                                                </label>
                                                <SearchableGeoTargeting
                                                    selectedLocations={selectedExcludeLocations}
                                                    onChange={(locations) => {
                                                        setSelectedExcludeLocations(locations);
                                                        setChangedFields(prev => new Set(prev).add('geo_targeting_excluded'));
                                                        setUpdates(prev => ({
                                                            ...prev,
                                                            geo_targeting_excluded: locations.map(loc => loc.id).join(',')
                                                        }));
                                                    }}
                                                    isExcluded={true}
                                                />
                                            </div>
                                        </div>

                                        {/* Device Category Targeting */}
                                        <div className="form-control">
                                            <DeviceCategorySelector
                                                selectedDevices={selectedDeviceCategories}
                                                onChange={(newDevices) => {
                                                    setSelectedDeviceCategories(newDevices);
                                                    setChangedFields(prev => new Set(prev).add('device_category_targeting'));
                                                    setUpdates(prev => ({
                                                        ...prev,
                                                        device_category_targeting: newDevices.map(d => d.id).join(',')
                                                    }));
                                                }}
                                            />
                                            <label className="label">
                                                <span className="label-text-alt">
                                                    Select device categories to target
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex gap-4 mt-6">
                                    <button
                                        onClick={() => setStep('input')}
                                        className="btn btn-outline"
                                    >
                                        Back
                                    </button>
                                    <button
                                        onClick={handlePreviewSubmit}
                                        className="btn btn-primary flex-1"
                                        disabled={!Object.values(updates).some(v => v !== '')}
                                    >
                                        Continue to Update
                                    </button>
                                </div>
                            </div>
                        )}

                        {step === 'update' && !loading && !batchId && (
                            <div className="space-y-6">
                                <div className="mb-6">
                                    <h3 className="text-lg font-medium text-gray-900">Confirm Updates</h3>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Review and confirm the updates to be applied to the selected line items.
                                    </p>
                                </div>
                                {error && <ErrorLog error={error} />}
                                <div className="bg-gray-50 p-4 rounded-lg space-y-4">
                                    <div>
                                        <h4 className="font-medium">Selected Line Items:</h4>
                                        <p className="text-sm text-gray-600">
                                            {selectedItems.length > 0 
                                                ? selectedItems.map(item => item.line_item_id).join(', ')
                                                : selectedIds.split(',').map(id => id.trim()).join(', ')
                                            }
                                        </p>
                                    </div>

                                    <div>
                                        <h4 className="font-medium">Updates to Apply:</h4>
                                        <ul className="list-disc list-inside text-sm text-gray-600">
                                            {Object.entries(updates).map(([key, value]) => {
                                                // Skip device_category_targeting, day_part_targeting, and geo_targeting as they have dedicated sections
                                                if (key === 'device_category_targeting' || 
                                                    key === 'day_part_targeting' || 
                                                    key === 'geo_targeting_included' || 
                                                    key === 'geo_targeting_excluded' ||
                                                    key === 'labels') {
                                                    return null;
                                                }

                                                console.log(`Checking field ${key}:`, {
                                                    value,
                                                    isChanged: changedFields.has(key),
                                                    isEmpty: value === null || value === undefined || value === ''
                                                });
                                                
                                                // Only show fields that have been explicitly changed
                                                if (!changedFields.has(key)) {
                                                    console.log(`Skipping ${key} - not in changedFields`);
                                                    return null;
                                                }
                                                
                                                // Skip empty values
                                                if (value === null || value === undefined || value === '') {
                                                    console.log(`Skipping ${key} - empty value`);
                                                    return null;
                                                }

                                                // Format the key for display
                                                const displayKey = key
                                                    .split('_')
                                                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                                                    .join(' ');

                                                // Format the value based on its type
                                                let displayValue = value;
                                                if (key === 'status') {
                                                    const statusMap = {
                                                        'PAUSED': 'Pause',
                                                        'DELIVERING': 'Resume',
                                                        'ARCHIVED': 'Archive'
                                                    };
                                                    displayValue = statusMap[value] || value;
                                                } else if (key.includes('date_time') && value) {
                                                    displayValue = new Date(value).toLocaleString();
                                                } else if (typeof value === 'boolean') {
                                                    displayValue = value ? 'Yes' : 'No';
                                                }
                                                
                                                console.log(`Displaying ${key}:`, displayValue);
                                                
                                                return (
                                                    <li key={key} className="mb-1">
                                                        <span className="font-medium">{displayKey}:</span> {displayValue}
                                                    </li>
                                                );
                                            })}
                                            
                                                    {/* Display Custom Targeting and CMS Metadata in confirmation section */}
                                                    {changedFields.has('custom_targeting') && selectedCustomTargeting?.length > 0 && (
                                                        <div className="mb-4">
                                                            <span className="font-medium">Custom Targeting:</span>
                                                            <ul className="list-none ml-4">
                                                                {selectedCustomTargeting.map((target, idx) => (
                                                                    <li key={idx} className="text-gray-700">
                                                                        {target.displayValue}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                    
                                                    {changedFields.has('audience_segments') && selectedAudienceSegments?.length > 0 && (
                                                        <div className="mb-4">
                                                            <span className="font-medium">Audience Segments:</span>
                                                            <ul className="list-none ml-4">
                                                                {selectedAudienceSegments.map((segment, idx) => (
                                                                    <li key={idx} className="text-gray-700">
                                                                        {segment.displayValue}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                    
                                                    {changedFields.has('cms_metadata') && selectedCmsMetadata?.length > 0 && (
                                                        <div className="mb-4">
                                                            <span className="font-medium">CMS Metadata:</span>
                                                            <ul className="list-none ml-4">
                                                                {selectedCmsMetadata.map((metadata, idx) => (
                                                                    <li key={idx} className="text-gray-700">
                                                                        {metadata.displayValue}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                    
                                                    {changedFields.has('device_category_targeting') && selectedDeviceCategories.length > 0 && (
                                                        <li className="mb-1">
                                                            <span className="font-medium">Device Categories:</span>
                                                            <ul className="list-none ml-4">
                                                                {selectedDeviceCategories.map((device, idx) => (
                                                                    <li key={idx}>{device.name}</li>
                                                                ))}
                                                            </ul>
                                                        </li>
                                                    )}
                                                    
                                                    {changedFields.has('day_part_targeting') && selectedDayParts.length > 0 && (
                                                        <li className="mb-1">
                                                            <span className="font-medium">Day Parts:</span>
                                                            <ul className="list-none ml-4">
                                                                {selectedDayParts.map((dp, idx) => (
                                                                    <li key={idx}>
                                                                        {dp.days.join(', ')} ({dp.startTime} - {dp.endTime})
                                                                        {dp.usePublisherTimeZone ? ' (Publisher TZ)' : ' (User TZ)'}
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </li>
                                                    )}

                                                    {changedFields.has('labels') && selectedLabels.length > 0 && (
                                                        <li className="mb-1">
                                                            <span className="font-medium">Labels:</span>
                                                            <ul className="list-none ml-4">
                                                                {selectedLabels.map((label, idx) => (
                                                                    <li key={idx}>{label.name}</li>
                                                                ))}
                                                            </ul>
                                                        </li>
                                                    )}

                                                    {(changedFields.has('geo_targeting_included') || changedFields.has('geo_targeting_excluded')) && 
                                                     (selectedIncludeLocations.length > 0 || selectedExcludeLocations.length > 0) && (
                                                        <li className="mb-1">
                                                            <span className="font-medium">Geographic Targeting:</span>
                                                            {changedFields.has('geo_targeting_included') && selectedIncludeLocations.length > 0 && (
                                                                <div className="ml-4">
                                                                    <span className="font-medium">Include:</span>
                                                                    <ul className="list-none ml-4">
                                                                        {selectedIncludeLocations.map((loc, idx) => (
                                                                            <li key={idx}>{loc.name}</li>
                                                                        ))}
                                                                    </ul>
                                                                </div>
                                                            )}
                                                            {changedFields.has('geo_targeting_excluded') && selectedExcludeLocations.length > 0 && (
                                                                <div className="ml-4">
                                                                    <span className="font-medium">Exclude:</span>
                                                                    <ul className="list-none ml-4">
                                                                        {selectedExcludeLocations.map((loc, idx) => (
                                                                            <li key={idx}>{loc.name}</li>
                                                                        ))}
                                                                    </ul>
                                                                </div>
                                                            )}
                                                        </li>
                                                    )}
                                                </ul>
                                            </div>
                                        </div>

                                        <div className="flex gap-4 mt-6">
                                            <button
                                                onClick={() => setStep('preview')}
                                                className="btn btn-outline"
                                            >
                                                Back
                                            </button>
                                            <button
                                                onClick={handleSubmit}
                                                className="btn btn-primary flex-1"
                                                disabled={loading}
                                            >
                                                {loading ? 'Updating...' : 'Update Line Items'}
                                            </button>
                                        </div>
                            </div>
                        )}

                        {success && (
                            <div className="alert alert-success mt-6 flex justify-between items-center">
                                <span>Line items updated successfully!</span>
                                <button
                                    onClick={() => router.visit(route('line-items.logs'))}
                                    className="btn btn-primary btn-sm"
                                >
                                    View Logs
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 