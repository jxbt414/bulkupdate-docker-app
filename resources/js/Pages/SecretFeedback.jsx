import { useState, useEffect } from 'react';
import axios from 'axios';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { toast } from 'react-hot-toast';

export default function SecretFeedback({ auth }) {
    const [feedback, setFeedback] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    // Add formatTimestamp function
    const formatTimestamp = (timestamp) => {
        try {
            // Parse the timestamp from backend
            const date = new Date(timestamp);
            
            // Format for display
            const options = { 
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };

            // Get date parts
            const day = date.getDate().toString().padStart(2, '0');
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const year = date.getFullYear();
            
            // Get time in 12-hour format
            const time = date.toLocaleString('en-AU', options);

            // Combine all parts
            return `${day}/${month}/${year} ${time} AEDT`;
        } catch (error) {
            console.error('Error formatting timestamp:', error);
            return timestamp;
        }
    };

    useEffect(() => {
        const fetchFeedback = async () => {
            try {
                const response = await axios.get('/feedback/data');
                console.log('Received feedback data:', response.data);
                setFeedback(response.data);
            } catch (error) {
                console.error('Failed to fetch feedback:', error);
                toast.error('Failed to load feedback messages');
            } finally {
                setIsLoading(false);
            }
        };

        fetchFeedback();
    }, []);

    // Log whenever feedback state changes
    useEffect(() => {
        console.log('Feedback state updated:', feedback);
    }, [feedback]);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Secret Feedback Page</h2>}
        >
            <Head title="Secret Feedback" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {isLoading ? (
                                <div className="flex justify-center items-center h-32">
                                    <span className="loading loading-spinner loading-lg"></span>
                                </div>
                            ) : feedback.length === 0 ? (
                                <p className="text-gray-500 text-center">No feedback messages yet.</p>
                            ) : (
                                <div className="space-y-6">
                                    {feedback.map((item) => (
                                        <div 
                                            key={item.id} 
                                            className={`p-4 rounded-lg border ${
                                                item.type === 'user' ? 'bg-primary/5 border-primary/20' : 'bg-gray-50 border-gray-200'
                                            }`}
                                        >
                                            <div className="flex justify-between items-start mb-2">
                                                <div>
                                                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                                                        item.type === 'user' ? 'bg-primary/10 text-primary' : 'bg-gray-200 text-gray-700'
                                                    }`}>
                                                        {item.type.toUpperCase()}
                                                    </span>
                                                    <span className="ml-2 text-sm text-gray-500">
                                                        from {item.user_name} at {formatTimestamp(item.created_at)}
                                                    </span>
                                                </div>
                                                {item.page_url && (
                                                    <a 
                                                        href={item.page_url} 
                                                        target="_blank" 
                                                        rel="noopener noreferrer"
                                                        className="text-sm text-primary hover:underline"
                                                    >
                                                        View Page
                                                    </a>
                                                )}
                                            </div>
                                            
                                            <p className="text-gray-700 mb-2">{item.message}</p>
                                            
                                            {item.screenshot_url && (
                                                <div className="mt-2">
                                                    <a 
                                                        href={item.screenshot_url} 
                                                        target="_blank" 
                                                        rel="noopener noreferrer"
                                                    >
                                                        <img 
                                                            src={item.screenshot_url} 
                                                            alt="Feedback Screenshot" 
                                                            className="rounded-lg border border-gray-200 max-w-2xl hover:opacity-90 transition-opacity"
                                                        />
                                                    </a>
                                                </div>
                                            )}
                                            
                                            {item.user_agent && (
                                                <div className="mt-2 text-xs text-gray-500">
                                                    User Agent: {item.user_agent}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 