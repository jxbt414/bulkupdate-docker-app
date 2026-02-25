import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useState, useEffect } from 'react';
import axios from 'axios';
import LoadingSpinner from '@/Components/LoadingSpinner';
import { toast } from 'react-hot-toast';

export default function Logs({ auth }) {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(false);
    const [filter, setFilter] = useState('all');
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedLog, setSelectedLog] = useState(null);
    const [showModal, setShowModal] = useState(false);

    useEffect(() => {
        loadLogs();
    }, []); // Only load logs on mount

    const loadLogs = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/line-items/logs/data');
            console.log('Full logs response:', response.data);
            
            if (response.data.status === 'success') {
                setLogs(response.data.logs);
                // Debug log to see the structure of each log entry
                response.data.logs.forEach(log => {
                    console.log('Log entry:', {
                        id: log.id,
                        type: log.type,
                        action: log.action,
                        batch_id: log.batch_id,
                        message: log.message,
                        line_item_id: log.line_item_id,
                        data: log.data,
                        data_type: log.data ? typeof log.data : 'undefined'
                    });
                });
            } else {
                console.error('Failed to load logs:', response.data.message);
                toast.error('Failed to load logs');
            }
        } catch (err) {
            console.error('Failed to load logs:', err.response?.data?.message || err.message);
            toast.error('Failed to load logs');
        } finally {
            setLoading(false);
        }
    };

    const handleRollback = async (lineItemId) => {
        if (!confirm('Are you sure you want to rollback this line item?')) {
            return;
        }

        try {
            console.log('Starting rollback for line item:', lineItemId);
            setLoading(true);
            
            const url = `/line-items/${lineItemId}/rollback`;
            console.log('Making request to:', url);
            
            const response = await axios.post(url);
            console.log('Rollback response:', response.data);
            
            if (response.data.status === 'success') {
                await loadLogs(); // Refresh the logs without showing another success message
            } else {
                throw new Error(response.data.message || 'Failed to rollback changes');
            }
        } catch (error) {
            console.error('Rollback failed:', error);
            console.error('Error details:', {
                message: error.message,
                response: error.response?.data,
                status: error.response?.status
            });
            toast.error(error.response?.data?.message || 'Failed to rollback changes');
        } finally {
            setLoading(false);
        }
    };

    const handleBulkRollback = async (batchId) => {
        if (!confirm('Are you sure you want to rollback all changes in this batch?')) {
            return;
        }

        try {
            console.log('Starting bulk rollback for batch:', batchId);
            setLoading(true);
            
            const url = `/line-items/rollback-batch/${batchId}`;
            console.log('Making request to:', url);
            
            const response = await axios.post(url);
            console.log('Rollback response:', response.data);
            
            if (response.data.status === 'success') {
                await loadLogs(); // Refresh the logs without showing another success message
            } else {
                throw new Error(response.data.message || 'Failed to rollback batch');
            }
        } catch (error) {
            console.error('Bulk rollback failed:', error);
            console.error('Error details:', {
                message: error.message,
                response: error.response?.data,
                status: error.response?.status
            });
            toast.error(error.response?.data?.message || error.message || 'Failed to rollback batch');
        } finally {
            setLoading(false);
        }
    };

    // Handle clicking on a summary log to show details
    const handleShowDetails = (log) => {
        try {
            // Debug log to see what's happening
            console.log('handleShowDetails called with log:', log);
            console.log('Log data:', log.data);
            console.log('Log batch_id:', log.batch_id);
            console.log('Log line_item_id:', log.line_item_id);
            console.log('Log action:', log.action);
            
            // Only process summary logs (batch logs without line_item_id)
            if (log.batch_id && !log.line_item_id && (log.action === 'update' || log.action === 'rollback')) {
                // Check if data is present
                if (log.data) {
                    console.log('Log data is present:', log.data);
                    
                    // Use the data directly - it should already be an object
                    // since Laravel casts the JSON column to an array
                    const parsedData = log.data;
                    console.log('Parsed data:', parsedData);
                    
                    if (parsedData.successful_items) {
                        console.log('Successful items:', parsedData.successful_items);
                        console.log('Items with line_item_name:', parsedData.successful_items.filter(item => item.line_item_name && item.line_item_name.trim() !== '').length);
                        parsedData.successful_items.forEach((item, index) => {
                            console.log(`Item ${index} line_item_name:`, item.line_item_name);
                        });
                    }
                    
                    setSelectedLog({
                        ...log,
                        parsedData: parsedData
                    });
                    setShowModal(true);
                } else {
                    console.error('No data available for this log');
                    toast.error('No details available for this log');
                }
            } else {
                console.log('Not a summary log, ignoring');
            }
        } catch (error) {
            console.error('Error showing details:', error);
            toast.error('Failed to show details');
        }
    };

    // Group logs by batch_id for better organization
    const groupLogsByBatch = () => {
        const grouped = {};
        
        // First pass: collect all logs by batch_id
        logs.forEach(log => {
            if (log.batch_id) {
                if (!grouped[log.batch_id]) {
                    grouped[log.batch_id] = {
                        summary: null,
                        items: []
                    };
                }
                
                // Check if this is a summary log (has batch_id but no line_item_id)
                if (!log.line_item_id && (log.action === 'update' || log.action === 'rollback')) {
                    grouped[log.batch_id].summary = log;
                } else {
                    grouped[log.batch_id].items.push(log);
                }
            }
        });
        
        return grouped;
    };

    const filteredLogs = logs
        .filter(log => {
            if (filter === 'all') return true;
            if (filter === 'rollback') return log.action === 'rollback';
            return log.type === filter;
        })
        .filter(log => {
            if (!searchTerm) return true;
            return (
                log.message.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (log.line_item_id && log.line_item_id.toString().includes(searchTerm))
            );
        });

    // Group logs by batch for display
    const batchGroups = groupLogsByBatch();

    // Modal component to display line item details
    const LineItemDetailsModal = () => {
        if (!selectedLog) {
            console.log('No selected log');
            return null;
        }

        const handleExportCSV = () => {
            // Get successful and failed items
            const successful_items = selectedLog.parsedData.successful_items || [];
            const failed_items = selectedLog.parsedData.failed_items || [];

            // Format data for CSV
            const csvData = [
                // CSV Headers
                ['Line Item ID', 'Line Item Name', 'Status', 
                 isRollback ? 'Previous Values' : 'Updated Fields',
                 isRollback ? 'Rolled Back Values' : 'Verification Status',
                 'Failed Updates']
            ];

            // Add successful items
            successful_items.forEach(item => {
                const verificationStatus = item.verification?.verified ? 'Success' : 
                    (item.verification?.awaiting_buyer ? 'Awaiting Buyer' : 'Failed');
                
                let displayFields = '';
                let rolledBackFields = '';
                let status = 'No Status Change';

                if (selectedLog.action === 'rollback') {
                    // Handle rollback data
                    if (item.data?.current_values) {
                        // Get only the changed values
                        const rolledBack = Object.entries(item.data.current_values)
                            .filter(([key]) => !['id'].includes(key))
                            .filter(([key]) => {
                                // Only show values that were changed during rollback
                                const currentValue = item.data.current_values[key];
                                const previousValue = item.data.previous_values?.[key];
                                return JSON.stringify(currentValue) !== JSON.stringify(previousValue);
                            })
                            .map(([key, value]) => {
                                let displayValue = value;
                                if (key === 'budget' && value) {
                                    displayValue = `${value.currencyCode} ${value.microAmount / 1000000}`;
                                } else if (key === 'primaryGoal' && value) {
                                    displayValue = `${value.units} ${value.goalType}`;
                                } else if (typeof value === 'object' && value !== null) {
                                    displayValue = JSON.stringify(value);
                                }
                                if (key === 'status') {
                                    status = displayValue;
                                }
                                return `${key}: ${displayValue}`;
                            })
                            .join(', ');
                        displayFields = rolledBack;
                        rolledBackFields = rolledBack;
                    }
                } else {
                    // Handle update data
                    displayFields = item.updated_fields || '';
                    status = item.updated_fields?.match(/Status: ([^,]+)/)?.[1] || 'No Status Change';
                }

                const failedUpdates = item.verification?.updates?.failed || {};
                const failedUpdatesList = Object.entries(failedUpdates)
                    .map(([field, values]) => `${field}: expected ${values.expected}, got ${values.actual}`)
                    .join('; ');

                csvData.push([
                    item.line_item_id,
                    item.line_item_name || '',
                    status,
                    displayFields,
                    isRollback ? rolledBackFields : (item.verification?.verified ? 'Success' : 
                        (item.verification?.awaiting_buyer ? 'Awaiting Buyer' : 
                            (Object.keys(failedUpdates).length > 0 ? 'Partial Success' : 'Success'))),
                    failedUpdatesList
                ]);
            });

            // Add failed items
            failed_items.forEach(item => {
                csvData.push([
                    item.line_item_id,
                    item.line_item_name || '',
                    'Error',
                    '',
                    'Failed',
                    selectedLog.action === 'rollback' ? (item.error || item.error_message || '') :
                        (item.error || item.error_message || '')
                ]);
            });

            // Convert to CSV string and download
            downloadCSV(csvData, `line-item-updates-${selectedLog.batch_id || 'batch'}-${new Date().toISOString().split('T')[0]}.csv`);
        };

        const handleExportErrorsCSV = () => {
            // Get failed items
            const failed_items = selectedLog.parsedData.failed_items || [];

            // Format data for CSV
            const csvData = [
                // CSV Headers
                ['Line Item ID', 'Proposal Line Item ID', 'Proposal ID', 'Error Message']
            ];

            // Add failed items
            failed_items.forEach(item => {
                csvData.push([
                    item.line_item_id || '',
                    item.proposal_line_item_id || '',
                    item.proposal_id || '',
                    item.error_message || item.error || ''
                ]);
            });

            // Download CSV
            downloadCSV(csvData, `failed-updates-${selectedLog.batch_id || 'batch'}-${new Date().toISOString().split('T')[0]}.csv`);
        };

        const downloadCSV = (csvData, filename) => {
            // Convert to CSV string
            const csvString = csvData.map(row => 
                row.map(cell => {
                    // Escape quotes and wrap in quotes if contains comma or newline
                    const escaped = String(cell).replace(/"/g, '""');
                    return /[,\n"]/.test(escaped) ? `"${escaped}"` : escaped;
                }).join(',')
            ).join('\n');

            // Create and trigger download
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };

        console.log('Selected log in modal:', selectedLog);
        
        if (!selectedLog.parsedData) {
            console.log('No parsedData in selected log');
            return null;
        }
        
        console.log('parsedData in modal:', selectedLog.parsedData);
        
        // Extract successful_items and failed_items, with fallbacks
        const successful_items = selectedLog.parsedData.successful_items || [];
        const failed_items = selectedLog.parsedData.failed_items || [];
        
        console.log('successful_items:', successful_items);
        console.log('failed_items:', failed_items);
        
        const isRollback = selectedLog.action === 'rollback';
        
        return (
            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">
                <div className="bg-white rounded-lg p-6 max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-xl font-bold">{selectedLog.message}</h2>
                        <button onClick={() => setShowModal(false)} className="text-gray-500 hover:text-gray-700">
                            <i className="fas fa-times"></i>
                        </button>
                    </div>

                    {/* Successful Items */}
                    {successful_items.length > 0 && (
                        <div className="mb-6">
                            <h3 className="text-lg font-semibold mb-2 text-green-600">Successful Updates</h3>
                            <div className="space-y-2">
                                        {successful_items.map((item, index) => (
                                    <div key={index} className="p-3 bg-green-50 rounded-lg">
                                        <div className="flex justify-between items-start">
                                            <div>
                                                <p className="font-medium">Line Item ID: {item.line_item_id}</p>
                                                <p>{item.line_item_name}</p>
                                                {selectedLog.action === 'rollback' ? (
                                                    <div className="mt-2">
                                                        <p className="font-medium">Previous Values:</p>
                                                        <pre className="text-sm bg-gray-100 p-2 rounded mt-1">
                                                            {JSON.stringify(item.data?.previous_values, null, 2)}
                                                        </pre>
                                                        <p className="font-medium mt-2">Rolled Back To:</p>
                                                        <pre className="text-sm bg-gray-100 p-2 rounded mt-1">
                                                            {JSON.stringify(item.data?.current_values, null, 2)}
                                                        </pre>
                                                            </div>
                                                ) : (
                                                    <p className="mt-1 text-sm text-gray-600">
                                                        {item.updated_fields}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                        ))}
                            </div>
                        </div>
                    )}
                    
                    {/* Failed Items */}
                    {failed_items.length > 0 && (
                        <div className="mb-6">
                            <h3 className="text-lg font-semibold mb-2 text-red-600">Failed Updates</h3>
                            <div className="space-y-2">
                                        {failed_items.map((item, index) => (
                                    <div key={index} className="p-3 bg-red-50 rounded-lg">
                                        <p className="font-medium">Line Item ID: {item.line_item_id}</p>
                                        <p className="text-red-600 mt-1">{item.error_message}</p>
                                        {item.proposal_line_item_id && (
                                            <p className="text-sm text-gray-600 mt-1">
                                                Proposal Line Item ID: {item.proposal_line_item_id}
                                            </p>
                                        )}
                                        {item.proposal_id && (
                                            <p className="text-sm text-gray-600">
                                                Proposal ID: {item.proposal_id}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    
                    <div className="flex justify-end space-x-2 mt-4">
                            <button 
                            onClick={handleExportCSV}
                                className="btn btn-primary" 
                        >
                            Export to CSV
                        </button>
                        {failed_items.length > 0 && (
                            <button
                                onClick={() => handleExportErrorsCSV()}
                                className="btn btn-error"
                            >
                                Export Errors CSV
                            </button>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Line Item Update Logs</h2>}
        >
            <Head title="Line Item Update Logs" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Search and Filter Controls */}
                            <div className="flex flex-wrap gap-4 mb-6">
                                <div className="form-control flex-1">
                                    <input
                                        type="text"
                                        placeholder="Search logs..."
                                        className="input input-bordered w-full"
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                    />
                                </div>
                                <select
                                    className="select select-bordered"
                                    value={filter}
                                    onChange={(e) => setFilter(e.target.value)}
                                    data-testid="log-filter"
                                >
                                    <option value="all">All Activities</option>
                                    <option value="success">Successful Updates</option>
                                    <option value="error">Errors</option>
                                    <option value="rollback">Rollbacks</option>
                                </select>
                                <button
                                    className="btn btn-ghost"
                                    onClick={loadLogs}
                                >
                                    <i className="fas fa-sync-alt mr-2"></i>
                                    Refresh
                                </button>
                            </div>

                            {/* Logs Display */}
                            {loading ? (
                                <LoadingSpinner />
                            ) : filteredLogs.length > 0 ? (
                                <div className="space-y-4">
                                    {filteredLogs.map((log) => (
                                        <div
                                            key={`${log.batch_id}-${log.line_item_id || 'summary'}`}
                                            className={`p-4 rounded-lg ${
                                                log.type === 'success'
                                                    ? 'bg-green-100 text-green-700'
                                                    : log.type === 'error'
                                                    ? 'bg-red-100 text-red-700'
                                                    : 'bg-gray-100 text-gray-700'
                                            } border-2 border-blue-500 cursor-pointer hover:bg-blue-50`}
                                            onClick={() => handleShowDetails(log)}
                                            data-testid={`log-entry-${log.batch_id}`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center space-x-4">
                                                    <div className="text-2xl">
                                                        {log.type === 'success' ? (
                                                            <i className="fas fa-check-circle"></i>
                                                        ) : (
                                                            <i className="fas fa-times-circle"></i>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <p className="font-medium">{log.message}</p>
                                                            <p className="text-sm opacity-75">
                                                                Batch ID: {log.batch_id}
                                                            <span className="ml-2 text-blue-600">(Click to view details)</span>
                                                        </p>
                                                        {log.data && (
                                                            <div className="mt-2">
                                                                {log.data.successful_items && log.data.successful_items.map((item, index) => (
                                                                    <p key={index} className="text-sm">
                                                                        ID: {item.line_item_id} - {item.line_item_name}
                                                                    </p>
                                                                ))}
                                                                {log.data.failed_items && log.data.failed_items.map((item, index) => (
                                                                    <p key={index} className="text-sm text-red-600">
                                                                        Failed: {item.line_item_id} - {item.error_message}
                                                                    </p>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex items-center space-x-4">
                                                    <div className="text-sm opacity-75">
                                                        {new Date(log.created_at).toLocaleString()}
                                                    </div>
                                                    {log.type === 'success' && log.action === 'update' && (
                                                        <div className="flex space-x-2">
                                                                <button
                                                                className="btn btn-primary btn-sm"
                                                                    onClick={(e) => {
                                                                    e.stopPropagation();
                                                                        handleBulkRollback(log.batch_id);
                                                                    }}
                                                                    title="Rollback all changes in this batch"
                                                                >
                                                                <i className="fas fa-undo-alt mr-2"></i>
                                                                {' Rollback Entire Batch'}
                                                                </button>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-12">
                                    <p className="text-gray-500">No logs found</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            
            {showModal && <LineItemDetailsModal />}
        </AuthenticatedLayout>
    );
} 