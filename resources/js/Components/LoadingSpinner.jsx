import React from 'react';

export default function LoadingSpinner({ size = 'md', text }) {
    const sizeClasses = {
        sm: 'w-4 h-4',
        md: 'w-8 h-8',
        lg: 'w-12 h-12'
    };

    return (
        <div className="flex flex-col justify-center items-center">
            <div className={`animate-spin rounded-full border-4 border-primary border-t-transparent ${sizeClasses[size]}`}></div>
            {text && <p className="mt-2 text-sm text-gray-600">{text}</p>}
        </div>
    );
}