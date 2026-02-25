import React from 'react';
import { useState, useRef } from 'react';

export default function FileUpload({ onUpload, loading }) {
    const [dragState, setDragState] = useState('idle');
    const [error, setError] = useState(null);
    const fileInputRef = useRef(null);

    const handleDragEnter = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragState('dragging');
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragState('idle');
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const validateFile = (file) => {
        if (!file) {
            setError('No file selected');
            return false;
        }

        const validTypes = ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'];
        if (!validTypes.includes(file.type) && !file.name.toLowerCase().endsWith('.csv')) {
            setError('Please upload a CSV file');
            return false;
        }

        if (file.size > 10 * 1024 * 1024) { // 10MB limit
            setError('File size must be less than 10MB');
            return false;
        }

        setError(null);
        return true;
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragState('idle');

        const file = e.dataTransfer.files[0];
        if (validateFile(file)) {
            onUpload(file);
        }
    };

    const handleFileSelect = (e) => {
        const file = e.target.files[0];
        if (validateFile(file)) {
            onUpload(file);
        }
    };

    return (
        <div className="w-full">
            {error && (
                <div className="bg-error/10 text-error rounded-lg p-4 mb-4" data-testid="error-message">
                    <div className="flex items-start">
                        <i className="fas fa-exclamation-circle mt-1 mr-3"></i>
                        <div>
                            <p className="font-medium">{error}</p>
                        </div>
                    </div>
                </div>
            )}
            <div
                className={`w-full h-64 border-2 border-dashed rounded-lg flex flex-col items-center justify-center p-6 transition-colors duration-200 ease-in-out cursor-pointer ${
                    dragState === 'dragging'
                        ? 'border-primary bg-primary/5'
                        : 'border-gray-300 hover:border-primary'
                }`}
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
                onClick={() => fileInputRef.current?.click()}
            >
                <input
                    type="file"
                    accept=".csv"
                    className="hidden"
                    ref={fileInputRef}
                    onChange={handleFileSelect}
                    data-testid="file-input"
                />
                {loading ? (
                    <div className="text-center" data-testid="loading-spinner">
                        <i className="fas fa-spinner fa-spin text-4xl text-primary mb-4"></i>
                        <p className="text-lg font-medium mb-2">Uploading...</p>
                    </div>
                ) : (
                    <div className="text-center">
                        <i className="fas fa-file-csv text-4xl text-primary mb-4"></i>
                        <p className="text-lg font-medium mb-2">Drag and drop your CSV file here</p>
                        <p className="text-sm text-gray-500">or click to browse (max 10MB)</p>
                    </div>
                )}
            </div>
        </div>
    );
}