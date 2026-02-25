import React, { useEffect } from 'react';

export default function LineItemTable({ 
    data = [], 
    selectedItems = [], 
    onSelectItems = () => {} 
}) {
    // Ensure data is an array and has valid line_item_id
    const validData = Array.isArray(data) ? data.filter(item => item && item.line_item_id) : [];

    // Set all items selected by default
    useEffect(() => {
        if (validData.length > 0 && selectedItems.length === 0) {
            onSelectItems(validData);
        }
    }, [validData]);

    const handleSelectAll = (e) => {
        if (e.target.checked) {
            onSelectItems(validData);
        } else {
            onSelectItems([]);
        }
    };

    const handleSelectItem = (item) => {
        if (!item || !item.line_item_id) return;

        const isSelected = selectedItems.some(selected => selected.line_item_id === item.line_item_id);
        let newSelectedItems;

        if (isSelected) {
            newSelectedItems = selectedItems.filter(selected => selected.line_item_id !== item.line_item_id);
        } else {
            newSelectedItems = [...selectedItems, item];
        }

        onSelectItems(newSelectedItems);
    };

    // Helper function to format values
    const formatValue = (value) => {
        if (value === null || value === undefined) return 'N/A';
        if (typeof value === 'number') {
            if (value >= 1000000) {
                return `${(value / 1000000).toFixed(2)}M`;
            } else if (value >= 1000) {
                return `${(value / 1000).toFixed(1)}K`;
            }
            return value.toString();
        }
        return value;
    };

    // Get fields that will be updated
    const getUpdateFields = (item) => {
        // Include all fields that have values, including priority and impression_goals
        return Object.keys(item).filter(key => 
            !key.startsWith('original_') && 
            key !== 'line_item_id' &&
            key !== 'line_item_name' &&
            item[key] !== null && 
            item[key] !== undefined
        );
    };

    const renderCurrentValue = (item, field) => {
        // Get the original field value
        const originalField = `original_${field}`;
        
        if (item[originalField] === undefined) {
            return <span className="text-gray-400">-</span>;
        }
        
        // Format based on field type
        if (field === 'budget' && item[originalField] !== null) {
            return <span>${parseFloat(item[originalField]).toFixed(2)}</span>;
        } else if ((field === 'priority' || field === 'impression_goals') && item[originalField] !== null) {
            return <span>{parseInt(item[originalField], 10)}</span>;
        } else {
            return <span>{item[originalField]}</span>;
        }
    };
    
    const renderNewValue = (item, field) => {
        // Only show new value if it exists and is different from original
        const originalField = `original_${field}`;
        
        if (item[field] === undefined || item[field] === null) {
            return <span className="text-gray-400">-</span>;
        }
        
        // Format based on field type
        if (field === 'budget') {
            return <span className="font-medium text-blue-600">${parseFloat(item[field]).toFixed(2)}</span>;
        } else if (field === 'priority' || field === 'impression_goals') {
            return <span className="font-medium text-blue-600">{parseInt(item[field], 10)}</span>;
        } else {
            return <span className="font-medium text-blue-600">{item[field]}</span>;
        }
    };

    if (!validData.length) {
        return (
            <div className="text-center py-4 text-gray-500">
                No valid line items found
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="table w-full">
                <thead>
                    <tr>
                        <th className="w-16">
                            <label className="cursor-pointer label">
                                <input
                                    type="checkbox"
                                    className="checkbox checkbox-primary"
                                    checked={selectedItems.length === validData.length && validData.length > 0}
                                    onChange={handleSelectAll}
                                />
                            </label>
                        </th>
                        <th>Line Item ID</th>
                        <th>Name</th>
                        <th>Fields to Update</th>
                        <th>Current Values</th>
                        <th>New Values</th>
                    </tr>
                </thead>
                <tbody>
                    {validData.map((item, index) => {
                        const updateFields = getUpdateFields(item);
                        return (
                            <tr key={item.line_item_id || index} className="hover">
                                <td>
                                    <label className="cursor-pointer label">
                                        <input
                                            type="checkbox"
                                            className="checkbox checkbox-primary"
                                            checked={selectedItems.some(selected => selected.line_item_id === item.line_item_id)}
                                            onChange={() => handleSelectItem(item)}
                                        />
                                    </label>
                                </td>
                                <td>{item.line_item_id}</td>
                                <td>
                                    {item.original_name || item.line_item_name || 'N/A'}
                                </td>
                                <td>
                                    <div className="space-y-1 text-sm">
                                        {updateFields.length > 0 ? (
                                            updateFields.map(field => (
                                                <div key={field} className="badge badge-outline">
                                                    {field.replace('line_item_', '')}
                                                </div>
                                            ))
                                        ) : (
                                            <div className="text-gray-400 italic">No changes</div>
                                        )}
                                    </div>
                                </td>
                                <td>
                                    <div className="space-y-1 text-sm">
                                        {updateFields.map(field => (
                                            <div key={field}>
                                                {renderCurrentValue(item, field)}
                                            </div>
                                        ))}
                                    </div>
                                </td>
                                <td>
                                    <div className="space-y-1 text-sm">
                                        {updateFields.map(field => (
                                            <div key={field}>
                                                {renderNewValue(item, field)}
                                            </div>
                                        ))}
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
} 