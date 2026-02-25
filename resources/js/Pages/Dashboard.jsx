import React from 'react';
import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import axios from 'axios';
import anime from 'animejs';
import LineItemTable from '@/Components/LineItemTable';
import ErrorLog from '@/Components/ErrorLog';
import LoadingSpinner from '@/Components/LoadingSpinner';
import BulkUpdateProgress from '@/Components/BulkUpdateProgress';
import { toast } from 'react-hot-toast';

export default function Dashboard({ auth }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [logs, setLogs] = useState([]);
    const [stats, setStats] = useState({
        totalUpdates: 0,
        successfulUpdates: 0,
        failedUpdates: 0,
        pendingUpdates: 0
    });
    const [activeBatch, setActiveBatch] = useState(null);
    const [activeTab, setActiveTab] = useState('overview');

    // Fetch logs from backend
    useEffect(() => {
        const fetchLogs = async () => {
            try {
                setLoading(true);
                const response = await axios.get('/line-items/logs/data');
                if (response.data.status === 'success') {
                    setLogs(response.data.logs);
                }
            } catch (err) {
                setError(err.response?.data?.message || 'Failed to fetch logs');
            } finally {
                setLoading(false);
            }
        };

        fetchLogs();
        
        // Set up polling for new logs every 30 seconds
        const interval = setInterval(fetchLogs, 30000);
        
        return () => clearInterval(interval);
    }, []);

    // Update stats when logs change
    useEffect(() => {
        const newStats = logs.reduce((acc, log) => {
            acc.totalUpdates++;
            if (log.type === 'success') {
                acc.successfulUpdates++;
            } else if (log.type === 'error') {
                acc.failedUpdates++;
            }
            return acc;
        }, {
            totalUpdates: 0,
            successfulUpdates: 0,
            failedUpdates: 0,
            pendingUpdates: activeBatch ? 1 : 0 // Set to 1 if there's an active batch
        });
        setStats(newStats);
    }, [logs, activeBatch]);

    const handleUpdateComplete = (status) => {
        if (status.failed === 0) {
            toast.success('All line items updated successfully', {
                icon: '✅',
                duration: 4000
            });
        } else {
            toast.warning(`Update completed with ${status.failed} failures`, {
                icon: '⚠️',
                duration: 4000
            });
        }
        // Refresh logs after update completes
        fetchLogs();
    };

    // Function to format the timestamp
    const formatTimestamp = (timestamp) => {
        const date = new Date(timestamp);
        return date.toLocaleString();
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>}
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Quick Actions */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                        <a href={route('line-items.upload')} className="btn btn-primary">
                            <i className="fas fa-magic mr-2"></i>
                            Dynamic Update
                        </a>
                        <a href={route('line-items.static-update')} className="btn btn-secondary">
                            <i className="fas fa-list mr-2"></i>
                            Static Update
                        </a>
                    </div>

                    {/* Stats Section */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                        <div className="stat bg-white shadow rounded-lg">
                            <div className="stat-title">Total Updates</div>
                            <div className="stat-value text-primary">{stats.totalUpdates}</div>
                        </div>
                        <div className="stat bg-white shadow rounded-lg">
                            <div className="stat-title">Successful</div>
                            <div className="stat-value text-success">{stats.successfulUpdates}</div>
                        </div>
                        <div className="stat bg-white shadow rounded-lg">
                            <div className="stat-title">Failed</div>
                            <div className="stat-value text-error">{stats.failedUpdates}</div>
                        </div>
                        <div className="stat bg-white shadow rounded-lg">
                            <div className="stat-title">Pending</div>
                            <div className="stat-value text-warning">{stats.pendingUpdates}</div>
                        </div>
                    </div>

                    {/* Main Content */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        {/* Tabs */}
                        <div className="tabs tabs-boxed bg-base-200 p-2">
                            <button 
                                className={`tab ${activeTab === 'overview' ? 'tab-active' : ''}`}
                                onClick={() => setActiveTab('overview')}
                            >
                                <i className="fas fa-chart-bar mr-2"></i>
                                Overview
                            </button>
                            <button 
                                className={`tab ${activeTab === 'logs' ? 'tab-active' : ''}`}
                                onClick={() => setActiveTab('logs')}
                            >
                                <i className="fas fa-history mr-2"></i>
                                Activity Logs
                            </button>
                        </div>

                        <div className="p-6">
                            {error && (
                                <div className="error-notification mb-4">
                                    <ErrorLog error={error} />
                                </div>
                            )}

                            {loading && <LoadingSpinner />}

                            {/* Tab Content */}
                            {activeTab === 'overview' && (
                                <div>
                                    {activeBatch && (
                                        <BulkUpdateProgress
                                            batchId={activeBatch}
                                            onComplete={handleUpdateComplete}
                                        />
                                    )}
                                    <div className="mt-4">
                                        <h3 className="text-lg font-medium mb-4">Recent Activity</h3>
                                        {logs.length > 0 ? (
                                            <div className="space-y-2">
                                                {logs.slice(0, 5).map((log, index) => (
                                                    <div
                                                        key={index}
                                                        className={`p-3 rounded-lg ${
                                                            log.type === 'success'
                                                                ? 'bg-success/10 text-success'
                                                                : 'bg-error/10 text-error'
                                                        }`}
                                                    >
                                                        <div className="flex justify-between">
                                                            <span>{log.message}</span>
                                                            <span className="text-sm opacity-70">
                                                                {formatTimestamp(log.created_at)}
                                                            </span>
                                                        </div>
                                                        {log.line_item_id && (
                                                            <div className="text-sm opacity-70 mt-1">
                                                                Line Item: {log.line_item_id}
                                                            </div>
                                                        )}
                                                        {log.user && (
                                                            <div className="text-sm opacity-70">
                                                                By: {log.user.name}
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-gray-500">No recent activity</p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {activeTab === 'logs' && (
                                <div>
                                    <h3 className="text-lg font-medium mb-4">Activity Logs</h3>
                                    {logs.length > 0 ? (
                                        <div className="space-y-2">
                                            {logs.map((log, index) => (
                                                <div
                                                    key={index}
                                                    className={`p-3 rounded-lg ${
                                                        log.type === 'success'
                                                            ? 'bg-success/10 text-success'
                                                            : 'bg-error/10 text-error'
                                                    }`}
                                                >
                                                    <div className="flex justify-between">
                                                        <span>{log.message}</span>
                                                        <span className="text-sm opacity-70">
                                                            {formatTimestamp(log.created_at)}
                                                        </span>
                                                    </div>
                                                    {log.line_item_id && (
                                                        <div className="text-sm opacity-70 mt-1">
                                                            Line Item: {log.line_item_id}
                                                        </div>
                                                    )}
                                                    {log.user && (
                                                        <div className="text-sm opacity-70">
                                                            By: {log.user.name}
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-gray-500">No activity logs found</p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
