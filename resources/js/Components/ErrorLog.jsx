import React from 'react';

export default function ErrorLog({ error }) {
    if (!error) return null;

    const errorMessage = typeof error === 'string' ? error : error.message || 'An unknown error occurred';
    const errorDetails = error.errors ? Object.values(error.errors) : [];

    return (
        <div 
            className="alert alert-error"
            role="alert"
            data-testid="error-message"
            aria-live="polite"
        >
            <div className="flex items-center">
                <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
                <span className="block sm:inline">{errorMessage}</span>
            </div>
            {errorDetails.length > 0 && (
                <ul className="mt-2 list-disc list-inside">
                    {errorDetails.map((detail, index) => (
                        <li key={index} className="text-sm">{detail}</li>
                    ))}
                </ul>
            )}
        </div>
    );
} 