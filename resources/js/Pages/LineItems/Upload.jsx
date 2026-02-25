import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FileUpload from '@/Components/FileUpload';
import FieldMapping from '@/Components/FieldMapping';
import ErrorLog from '@/Components/ErrorLog';
import LoadingSpinner from '@/Components/LoadingSpinner';
import { useState } from 'react';
import axios from 'axios';
import anime from 'animejs';
import { router } from '@inertiajs/react';

export default function Upload({ auth }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [csvData, setCsvData] = useState(null);
    const [csvHeaders, setCsvHeaders] = useState(null);
    const [step, setStep] = useState('upload'); // upload, mapping, preview

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

            if (response.data.status === 'success') {
                setCsvData(response.data.data);
                setCsvHeaders(response.data.headers || []);
                setStep('mapping');
                animateSuccess();
            } else {
                throw new Error(response.data.message || 'Failed to process CSV file');
            }
        } catch (err) {
            setError(err.response?.data?.message || err.message || 'An error occurred while uploading the file');
            animateError();
        } finally {
            setLoading(false);
        }
    };

    const handleMappingComplete = async (mappings) => {
        setLoading(true);
        setError(null);

        try {
            console.log('Sending mapping data:', { mappings, data: csvData });
            const response = await axios.post('/line-items/map-fields', {
                mappings,
                data: csvData
            });

            console.log('Mapping response:', response.data);
            if (response.data.status === 'success') {
                router.visit('/line-items/preview', {
                    method: 'get',
                    data: { sessionId: response.data.id },
                    preserveState: true,
                    preserveScroll: true,
                });
            } else {
                throw new Error(response.data.message || 'Failed to map fields');
            }
        } catch (err) {
            console.error('Mapping error:', err);
            setError(err.response?.data?.message || err.message || 'An error occurred while mapping fields');
            animateError();
        } finally {
            setLoading(false);
        }
    };

    const animateSuccess = () => {
        anime({
            targets: '.success-notification',
            translateY: [-20, 0],
            opacity: [0, 1],
            duration: 800,
            easing: 'easeOutElastic(1, .8)'
        });
    };

    const animateError = () => {
        anime({
            targets: '.error-notification',
            translateX: [-20, 20, -10, 10, 0],
            duration: 600,
            easing: 'easeOutElastic(1, .8)'
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dynamic Bulk Update (CSV)</h2>}
        >
            <Head title="Dynamic Bulk Update" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        {/* Step Indicator */}
                        <div className="mb-8">
                            <ul className="steps w-full">
                                <li className={`step ${step === 'upload' ? 'step-primary' : ''}`}>
                                    Upload CSV
                                </li>
                                <li className={`step ${step === 'mapping' ? 'step-primary' : ''}`}>
                                    Map Fields
                                </li>
                                <li className={`step ${step === 'preview' ? 'step-primary' : ''}`}>
                                    Preview & Update
                                </li>
                            </ul>
                        </div>

                        {error && (
                            <div className="mb-6">
                                <ErrorLog error={error} />
                            </div>
                        )}

                        {loading && <LoadingSpinner />}

                        {step === 'upload' && (
                            <div className="fade-in">
                                <div className="mb-6">
                                    <h3 className="text-lg font-medium text-gray-900">Upload CSV for Dynamic Updates</h3>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Use this form to upload a CSV file containing different values for each line item. Each row in the CSV should contain the line item ID and the specific changes for that line item.
                                    </p>
                                </div>

                                <div className="bg-gray-50 p-4 rounded-lg mb-6">
                                    <h4 className="font-medium mb-2">CSV Format Guidelines:</h4>
                                    <ul className="list-disc list-inside text-sm text-gray-600 space-y-1">
                                        <li>First row should contain column headers</li>
                                        <li>Each row represents a different line item</li>
                                        <li>Required columns: Line Item ID, Line Item Name</li>
                                        <li>Optional columns: Type, Priority, Budget, etc.</li>
                                        <li>Use consistent formatting for dates and numbers</li>
                                    </ul>
                                    <div className="mt-4 no-animation hover:bg-base-200">
                                        <a
                                            href={route('line-items.dynamic-sample-csv')}
                                            className="btn btn-outline no-animation hover:bg-base-200 min-w-[150px]"
                                            download
                                        >
                                            Download Sample CSV
                                        </a>
                                    </div>
                                </div>

                                <FileUpload 
                                    onUpload={handleFileUpload}
                                    loading={loading}
                                />
                            </div>
                        )}

                        {step === 'mapping' && csvHeaders && (
                            <div className="fade-in">
                                <FieldMapping 
                                    csvHeaders={csvHeaders}
                                    onMapComplete={handleMappingComplete}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 