import React from 'react';
import { useState, useEffect } from 'react';

export default function FieldMapping({ csvHeaders, onMapComplete }) {
    const [fieldMappings, setFieldMappings] = useState({});
    const [error, setError] = useState(null);

    const REQUIRED_FIELDS = ['line_item_id'];
    const AVAILABLE_FIELDS = [
        // Basic line item information
        { value: 'line_item_id', label: 'Line Item ID', required: true },
        { value: 'line_item_name', label: 'Line Item Name', required: false },
        { value: 'budget', label: 'Budget', required: false },
        { value: 'line_item_type', label: 'Line Item Type', required: false },
        { value: 'priority', label: 'Priority', required: false },
        { value: 'impression_goals', label: 'Impression Goals', required: false },
        { value: 'status', label: 'Status', required: false },
        
        // Scheduling
        { value: 'start_date_time', label: 'Start Date/Time', required: false },
        { value: 'end_date_time', label: 'End Date/Time', required: false },
        { value: 'unlimited_end_date', label: 'Unlimited End Date', required: false },
        
        // Delivery settings
        { value: 'delivery_rate_type', label: 'Delivery Rate Type', required: false },
        { value: 'cost_type', label: 'Cost Type', required: false },
        { value: 'cost_per_unit', label: 'Cost Per Unit', required: false },
        
        // Frequency capping
        { value: 'frequency_cap_max', label: 'Frequency Cap Maximum', required: false },
        { value: 'frequency_cap_time_units', label: 'Frequency Cap Time Units', required: false },
        { value: 'frequency_cap_time_unit_type', label: 'Frequency Cap Time Unit Type', required: false },
        { value: 'frequency_cap_remove', label: 'Frequency Cap to Remove', required: false },
        
        // Geo targeting
        { value: 'geo_targeting_included_add', label: 'Add Included Locations', required: false },
        { value: 'geo_targeting_included_remove', label: 'Remove Included Locations', required: false },
        { value: 'geo_targeting_excluded_add', label: 'Add Excluded Locations', required: false },
        { value: 'geo_targeting_excluded_remove', label: 'Remove Excluded Locations', required: false },
        
        // Inventory targeting
        { value: 'inventory_targeting_included_add', label: 'Add Included Inventory', required: false },
        { value: 'inventory_targeting_included_remove', label: 'Remove Included Inventory', required: false },
        { value: 'inventory_targeting_excluded_add', label: 'Add Excluded Inventory', required: false },
        { value: 'inventory_targeting_excluded_remove', label: 'Remove Excluded Inventory', required: false },
        
        // Custom targeting
        { value: 'custom_targeting_add', label: 'Add Custom Targeting', required: false },
        { value: 'custom_targeting_remove', label: 'Remove Custom Targeting', required: false },
        { value: 'custom_targeting_key_remove', label: 'Remove Custom Targeting Key', required: false },
        
        // Day part targeting
        { value: 'day_part_targeting_add', label: 'Add Day Part Targeting', required: false },
        { value: 'day_part_targeting_remove', label: 'Remove Day Part Targeting', required: false },
        
        // Device targeting
        { value: 'device_category_targeting_add', label: 'Add Device Categories', required: false },
        { value: 'device_category_targeting_remove', label: 'Remove Device Categories', required: false },
        
        // Labels
        { value: 'labels_add', label: 'Add Labels', required: false },
        { value: 'labels_remove', label: 'Remove Labels', required: false },
        
        // Targeting presets
        { value: 'targeting_presets', label: 'Targeting Presets', required: false }
    ];

    const handleMappingChange = (csvHeader, field) => {
        setFieldMappings(prev => ({
            ...prev,
            [csvHeader]: field
        }));
    };

    const validateMappings = () => {
        // Check if all required fields are mapped
        const mappedFields = Object.values(fieldMappings);
        const missingRequired = REQUIRED_FIELDS.filter(field => !mappedFields.includes(field));

        if (missingRequired.length > 0) {
            setError(`Missing required fields: ${missingRequired.join(', ')}`);
            return false;
        }

        // Check for duplicate mappings
        const uniqueMappedFields = new Set(mappedFields.filter(f => f !== '')); // Ignore empty mappings
        if (uniqueMappedFields.size !== mappedFields.filter(f => f !== '').length) {
            setError('Each field can only be mapped once');
            return false;
        }

        setError(null);
        return true;
    };

    const handleSubmit = () => {
        if (validateMappings()) {
            onMapComplete(fieldMappings);
        }
    };

    useEffect(() => {
        // Initialize all mappings to empty string (no automatic mapping)
        const initialMappings = {};
        csvHeaders.forEach(header => {
            initialMappings[header] = '';
        });
        setFieldMappings(initialMappings);
    }, [csvHeaders]);

    return (
        <div className="space-y-6">
            <div className="bg-white p-6 rounded-lg shadow">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Map CSV Fields</h3>
                <p className="text-sm text-gray-600 mb-6">
                    Match your CSV columns to the corresponding line item fields. Required fields are marked with an asterisk (*).
                </p>

                {error && (
                    <div className="alert alert-error mb-4">
                        <span>{error}</span>
                    </div>
                )}

                <div className="space-y-4">
                    {csvHeaders.map((header) => (
                        <div key={header} className="flex items-center gap-4">
                            <div className="w-1/3">
                                <span className="text-sm font-medium text-gray-700">{header}</span>
                            </div>
                            <div className="w-2/3">
                                <select
                                    className="select select-bordered w-full"
                                    value={fieldMappings[header] || ''}
                                    onChange={(e) => handleMappingChange(header, e.target.value)}
                                >
                                    <option value="">Do not map</option>
                                    {AVAILABLE_FIELDS.map((field) => (
                                        <option 
                                            key={field.value} 
                                            value={field.value}
                                            disabled={Object.values(fieldMappings).includes(field.value) && fieldMappings[header] !== field.value}
                                        >
                                            {field.label} {field.required && '*'}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="mt-6 flex justify-end">
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={handleSubmit}
                    >
                        Continue with Mapping
                    </button>
                </div>
            </div>

            {/* Field Descriptions */}
            <div className="bg-white p-6 rounded-lg shadow">
                <h4 className="text-md font-medium text-gray-900 mb-4">Field Descriptions</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {AVAILABLE_FIELDS.map((field) => (
                        <div key={field.value} className="text-sm">
                            <span className="font-medium">{field.label}</span>
                            {field.required && <span className="text-red-500 ml-1">*</span>}
                            <p className="text-gray-600 mt-1">
                                {getFieldDescription(field.value)}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function getFieldDescription(field) {
    const descriptions = {
        // Basic line item information
        line_item_id: 'Unique identifier for the line item in Google Ad Manager',
        line_item_name: 'Name of the line item as shown in Google Ad Manager',
        budget: 'Total budget amount for the line item',
        line_item_type: 'Type of line item (e.g., Standard, Sponsorship, Network)',
        priority: 'Priority level of the line item',
        impression_goals: 'Target number of impressions',
        status: 'Current status of the line item',
        
        // Scheduling
        start_date_time: 'Start date and time for the line item',
        end_date_time: 'End date and time for the line item',
        unlimited_end_date: 'Whether the line item has no end date',
        
        // Delivery settings
        delivery_rate_type: 'How the line item should be delivered (EVENLY, FRONTLOADED, AS_FAST_AS_POSSIBLE)',
        cost_type: 'Type of cost (CPM, CPC, etc.)',
        cost_per_unit: 'Cost per unit for the line item',
        
        // Frequency capping
        frequency_cap_max: 'Maximum number of impressions in the frequency cap',
        frequency_cap_time_units: 'Number of time units for frequency cap',
        frequency_cap_time_unit_type: 'Type of time unit for frequency cap (MINUTE, HOUR, DAY, WEEK, MONTH)',
        frequency_cap_remove: 'Frequency cap to remove from the line item',
        
        // Geo targeting
        geo_targeting_included_add: 'Locations to add to included targeting',
        geo_targeting_included_remove: 'Locations to remove from included targeting',
        geo_targeting_excluded_add: 'Locations to add to excluded targeting',
        geo_targeting_excluded_remove: 'Locations to remove from excluded targeting',
        
        // Inventory targeting
        inventory_targeting_included_add: 'Ad units/placements to add to included targeting',
        inventory_targeting_included_remove: 'Ad units/placements to remove from included targeting',
        inventory_targeting_excluded_add: 'Ad units/placements to add to excluded targeting',
        inventory_targeting_excluded_remove: 'Ad units/placements to remove from excluded targeting',
        
        // Custom targeting
        custom_targeting_add: 'Custom targeting criteria to add',
        custom_targeting_remove: 'Custom targeting criteria to remove',
        custom_targeting_key_remove: 'Custom targeting keys to remove entirely',
        
        // Day part targeting
        day_part_targeting_add: 'Day and time targeting to add',
        day_part_targeting_remove: 'Day and time targeting to remove',
        
        // Device targeting
        device_category_targeting_add: 'Device categories to add to targeting',
        device_category_targeting_remove: 'Device categories to remove from targeting',
        
        // Labels
        labels_add: 'Labels to add to the line item',
        labels_remove: 'Labels to remove from the line item',
        
        // Targeting presets
        targeting_presets: 'Predefined targeting configurations to apply'
    };
    return descriptions[field] || 'No description available';
} 