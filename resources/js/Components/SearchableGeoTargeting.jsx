import React from 'react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import LoadingSpinner from '@/Components/LoadingSpinner';

export default function SearchableGeoTargeting({ selectedLocations = [], onChange, isExcluded = false }) {
    const [query, setQuery] = useState('');
    const [availableLocations, setAvailableLocations] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [showDropdown, setShowDropdown] = useState(false);
    
    const containerRef = useRef(null);
    
    // Debounce search
    useEffect(() => {
        if (query.length < 2) {
            setAvailableLocations([]);
            return;
        }

        const timeoutId = setTimeout(() => {
            loadLocations();
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [query]);
    
    // Handle click outside to close dropdown
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setShowDropdown(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    const loadLocations = async () => {
        if (query.length < 2) return;
        
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get(`/line-items/available-locations?search=${encodeURIComponent(query)}`);
            if (response.data.status === 'success') {
                // Limit to 20 results
                setAvailableLocations(response.data.locations.slice(0, 20));
                setShowDropdown(true);
            } else {
                throw new Error(response.data.message || 'Failed to load locations');
            }
        } catch (err) {
            console.error('Error loading locations:', err);
            setError(err.message || 'Failed to load locations');
        } finally {
            setLoading(false);
        }
    };

    const handleSelect = (location) => {
        // Check if already selected
        const isSelected = selectedLocations.some(selected => selected.id === location.id);
        
        if (isSelected) {
            // Remove if already selected
            onChange(selectedLocations.filter(selected => selected.id !== location.id));
        } else {
            // Add with excluded flag based on prop
            onChange([...selectedLocations, { ...location, excluded: isExcluded }]);
        }
        
        // Clear search after selection
        setQuery('');
        setShowDropdown(false);
    };

    const handleRemove = (locationId) => {
        onChange(selectedLocations.filter(location => location.id !== locationId));
    };

    return (
        <div className="relative w-full" ref={containerRef}>
            <div className="relative w-full">
                <div className="relative w-full cursor-default overflow-hidden rounded-lg bg-white text-left border border-black focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75 focus-visible:ring-offset-2 focus-visible:ring-offset-primary sm:text-sm">
                    <div className="flex flex-wrap gap-1 p-1">
                        {selectedLocations.map((location) => (
                            <span
                                key={location.id}
                                className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary text-white"
                            >
                                {location.name}
                                <button
                                    type="button"
                                    onClick={() => handleRemove(location.id)}
                                    className="ml-1 inline-flex items-center p-0.5 rounded-full hover:bg-primary-focus focus:outline-none"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                </button>
                            </span>
                        ))}
                        <input
                            className="w-full border-none py-2 pl-3 pr-10 text-sm leading-5 text-gray-900 focus:ring-0"
                            placeholder="Type to search locations..."
                            onChange={(e) => setQuery(e.target.value)}
                            value={query}
                            onFocus={() => query.length >= 2 && setShowDropdown(true)}
                        />
                    </div>
                    <button 
                        type="button"
                        className="absolute inset-y-0 right-0 flex items-center pr-2"
                        onClick={() => setShowDropdown(!showDropdown)}
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                        </svg>
                    </button>
                </div>
                
                {showDropdown && (
                    <div className="absolute left-0 top-full mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm z-50">
                        {loading ? (
                            <div className="relative cursor-default select-none py-2 px-4 text-gray-700">
                                <LoadingSpinner size="sm" />
                            </div>
                        ) : error ? (
                            <div className="relative cursor-default select-none py-2 px-4 text-red-500">
                                {error}
                            </div>
                        ) : query.length < 2 ? (
                            <div className="relative cursor-default select-none py-2 px-4 text-gray-700">
                                Type at least 2 characters to search...
                            </div>
                        ) : availableLocations.length === 0 ? (
                            <div className="relative cursor-default select-none py-2 px-4 text-gray-700">
                                No locations found.
                            </div>
                        ) : (
                            availableLocations.map((location) => {
                                const isSelected = selectedLocations.some(selected => selected.id === location.id);
                                return (
                                    <div
                                        key={location.id}
                                        className={`relative cursor-pointer select-none py-2 pl-10 pr-4 ${
                                            isSelected ? 'bg-primary text-white' : 'text-gray-900 hover:bg-gray-100'
                                        }`}
                                        onClick={() => handleSelect(location)}
                                    >
                                        <span className="block truncate">
                                            {location.name}
                                            {location.canonicalName && (
                                                <span className="text-xs ml-2 text-gray-500">
                                                    {location.canonicalName}
                                                </span>
                                            )}
                                        </span>
                                        {isSelected && (
                                            <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                </svg>
                                            </span>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>
                )}
            </div>
        </div>
    );
} 