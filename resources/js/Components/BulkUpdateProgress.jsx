import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { motion, AnimatePresence } from 'framer-motion';
import { formatDistanceToNow } from 'date-fns';
import toast from 'react-hot-toast';

const BulkUpdateProgress = ({ batchId, onComplete }) => {
    const [status, setStatus] = useState(null);
    const [error, setError] = useState(null);
    const [isPolling, setIsPolling] = useState(true);
    const [isRetrying, setIsRetrying] = useState(false);
    const [showFailedItems, setShowFailedItems] = useState(false);

    useEffect(() => {
        let pollInterval;

        const fetchStatus = async () => {
            try {
                // Don't fetch status for temporary batch IDs
                if (batchId.startsWith('temp_')) {
                    setStatus({
                        batch_id: batchId,
                        total: 0,
                        completed: 0,
                        failed: 0,
                        in_progress: 0,
                        status: 'preparing',
                        started_at: new Date().toISOString(),
                        completed_at: null,
                        failed_items: [],
                        successful_items: []
                    });
                    return;
                }

                const response = await axios.get(`/line-items/bulk-update-status/${batchId}`);
                const newStatus = response.data.data;
                setStatus(newStatus);

                if (newStatus.status === 'completed') {
                    setIsPolling(false);
                    if (onComplete) {
                        onComplete(newStatus);
                    }
                }
            } catch (err) {
                setError(err.response?.data?.message || 'Failed to fetch status');
                setIsPolling(false);
            }
        };

        // Initial fetch
        fetchStatus();

        // Poll every 3 seconds if not completed and not a temporary batch ID
        if (isPolling && !batchId.startsWith('temp_')) {
            pollInterval = setInterval(fetchStatus, 3000);
        }

        return () => {
            if (pollInterval) {
                clearInterval(pollInterval);
            }
        };
    }, [batchId, isPolling]);

    const handleRetry = async () => {
        setIsRetrying(true);
        try {
            await axios.post(`/line-items/retry-bulk-update/${batchId}`);
            setIsPolling(true);
            setError(null);
            toast.success('Retrying failed updates', {
                icon: '🔄',
                duration: 4000
            });
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to retry updates');
            toast.error('Failed to retry updates', {
                icon: '❌',
                duration: 4000
            });
        } finally {
            setIsRetrying(false);
        }
    };

    if (error) {
        return (
            <div className="bg-error text-error-content p-4 rounded-lg shadow">
                <h3 className="font-bold">Error</h3>
                <p>{error}</p>
                <button 
                    onClick={() => {
                        setError(null);
                        setIsPolling(true);
                    }}
                    className="btn btn-sm btn-outline mt-2"
                >
                    Try Again
                </button>
            </div>
        );
    }

    if (!status) {
        return (
            <div className="flex items-center justify-center p-8">
                <div className="loading loading-spinner loading-lg text-primary"></div>
            </div>
        );
    }

    // Show a special progress bar for temporary batch IDs
    if (batchId.startsWith('temp_')) {
        return (
            <AnimatePresence>
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -20 }}
                    className="bg-base-200 p-6 rounded-lg shadow-lg"
                >
                    <div className="flex justify-between items-center mb-4">
                        <h3 className="text-lg font-semibold">Preparing Update</h3>
                        <div className="flex items-center gap-2">
                            <span className="badge badge-primary">initializing</span>
                        </div>
                    </div>

                    {/* Indeterminate Progress Bar */}
                    <div className="w-full bg-base-300 rounded-full h-4 mb-4 overflow-hidden relative">
                        <motion.div
                            initial={{ x: "-100%" }}
                            animate={{ x: "100%" }}
                            transition={{
                                repeat: Infinity,
                                duration: 1,
                                ease: "linear"
                            }}
                            className="h-full w-full bg-primary absolute"
                        />
                    </div>

                    <div className="text-sm text-base-content/70">
                        <p>Initializing update process...</p>
                    </div>
                </motion.div>
            </AnimatePresence>
        );
    }

    const progress = Math.round(((status.completed + status.failed) / status.total) * 100);

    return (
        <AnimatePresence>
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -20 }}
                className="bg-base-200 p-6 rounded-lg shadow-lg"
            >
                <div className="flex justify-between items-center mb-4">
                    <h3 className="text-lg font-semibold">Bulk Update Progress</h3>
                    <div className="flex items-center gap-2">
                        <span className={`badge ${
                            status.status === 'completed'
                                ? status.failed === 0
                                    ? 'badge-success'
                                    : 'badge-warning'
                                : 'badge-primary'
                        }`}>
                            {status.status}
                        </span>
                        {status.failed > 0 && (
                            <button
                                onClick={() => setShowFailedItems(!showFailedItems)}
                                className="btn btn-sm btn-ghost"
                            >
                                <i className={`fas fa-chevron-${showFailedItems ? 'up' : 'down'}`}></i>
                            </button>
                        )}
                    </div>
                </div>

                {/* Progress Bar */}
                <div className="mt-4">
                    <div
                        data-testid="progress-bar"
                        className="progress"
                        style={{ width: `${progress}%` }}
                    ></div>
                </div>

                {/* Progress Text */}
                <div className="text-sm text-center mb-4">
                    <p>
                        {status.status === 'completed' 
                            ? `Completed updating ${status.completed + status.failed} line items`
                            : `Updating ${status.completed + status.failed} out of ${status.total} line items (${progress}%)`
                        }
                    </p>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div className="stat bg-base-100 rounded-lg p-4">
                        <div className="stat-title">Total</div>
                        <div className="stat-value">{status.total}</div>
                    </div>
                    <div className="stat bg-base-100 rounded-lg p-4">
                        <div className="stat-title">Completed</div>
                        <div className="stat-value text-success">{status.completed}</div>
                    </div>
                    <div className="stat bg-base-100 rounded-lg p-4">
                        <div className="stat-title">Failed</div>
                        <div className="stat-value text-error">{status.failed}</div>
                    </div>
                    <div className="stat bg-base-100 rounded-lg p-4">
                        <div className="stat-title">Progress</div>
                        <div className="stat-value">{progress}%</div>
                    </div>
                </div>

                {/* Failed Items List */}
                <AnimatePresence>
                    {showFailedItems && status.failed_items && (
                        <motion.div
                            initial={{ height: 0, opacity: 0 }}
                            animate={{ height: 'auto', opacity: 1 }}
                            exit={{ height: 0, opacity: 0 }}
                            className="mb-4 overflow-hidden"
                        >
                            <div className="bg-base-100 rounded-lg p-4">
                                <h4 className="font-semibold mb-2">Failed Items</h4>
                                <div className="space-y-2 max-h-60 overflow-y-auto">
                                    {status.failed_items.map((item, index) => (
                                        <div key={index} className="flex justify-between items-center p-2 bg-base-200 rounded">
                                            <span>Line Item ID: {item.line_item_id}</span>
                                            <span className="text-error">{item.error}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>

                {/* Timestamps */}
                <div className="text-sm text-base-content/70">
                    {status.started_at && (
                        <p>Started: {formatDistanceToNow(new Date(status.started_at))} ago</p>
                    )}
                    {status.completed_at && (
                        <p>Completed: {formatDistanceToNow(new Date(status.completed_at))} ago</p>
                    )}
                </div>

                {/* Action Buttons */}
                {status.status === 'completed' && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        className="mt-4 flex gap-2"
                    >
                        {status.failed > 0 && (
                            <button
                                onClick={handleRetry}
                                disabled={isRetrying}
                                className="btn btn-warning"
                            >
                                {isRetrying ? (
                                    <>
                                        <span className="loading loading-spinner loading-sm"></span>
                                        Retrying...
                                    </>
                                ) : (
                                    <>
                                        <i className="fas fa-redo mr-2"></i>
                                        Retry Failed Items
                                    </>
                                )}
                            </button>
                        )}
                        <button
                            onClick={() => window.location.reload()}
                            className="btn btn-primary"
                        >
                            Refresh Page
                        </button>
                        <button
                            onClick={() => window.location.href = '/line-items/logs'}
                            className="btn btn-ghost"
                        >
                            View Logs
                        </button>
                    </motion.div>
                )}
            </motion.div>
        </AnimatePresence>
    );
};

export default BulkUpdateProgress; 