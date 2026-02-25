import React from 'react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import LoadingSpinner from './LoadingSpinner';

export default function SearchableCustomTargeting({ selectedTargeting = [], onChange }) {
    const [key, setKey] = useState('');
    const [value, setValue] = useState('');
    const [keyQuery, setKeyQuery] = useState('');
    const [valueQuery, setValueQuery] = useState('');
    const [availableKeys, setAvailableKeys] = useState([]);
    const [availableValues, setAvailableValues] = useState([]);
    const [loadingKeys, setLoadingKeys] = useState(false);
    const [loadingValues, setLoadingValues] = useState(false);
    const [error, setError] = useState(null);
    const [showKeyDropdown, setShowKeyDropdown] = useState(false);
    const [showValueDropdown, setShowValueDropdown] = useState(false);
    
    // Refs for the dropdown containers
    const keyContainerRef = useRef(null);
    const valueContainerRef = useRef(null);

    // Handle click outside to close dropdowns
    useEffect(() => {
        const handleClickOutside = (event) => {
            // Close key dropdown if click is outside
            if (keyContainerRef.current && !keyContainerRef.current.contains(event.target)) {
                setShowKeyDropdown(false);
            }
            
            // Close value dropdown if click is outside
            if (valueContainerRef.current && !valueContainerRef.current.contains(event.target)) {
                setShowValueDropdown(false);
            }
        };

        // Add event listener
        document.addEventListener('mousedown', handleClickOutside);
        
        // Clean up
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    // Debounce search for keys
    useEffect(() => {
        const timer = setTimeout(() => {
            if (keyQuery.length >= 2) {
                console.log('Searching for custom targeting keys with query:', keyQuery);
                loadCustomTargetingKeys(keyQuery);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [keyQuery]);

    // Debounce search for values when a key is selected
    useEffect(() => {
        const timer = setTimeout(() => {
            if (key) {
                console.log('Searching for custom targeting values with key:', key, 'and query:', valueQuery);
                loadCustomTargetingValues(key, valueQuery);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [valueQuery, key]);

    const loadCustomTargetingKeys = async (query) => {
        try {
            console.log('Starting request to load custom targeting keys');
            setLoadingKeys(true);
            setError(null);
            
            const url = `/line-items/custom-targeting-keys?search=${encodeURIComponent(query)}`;
            console.log('Request URL:', url);
            
            const response = await axios.get(url, {
                timeout: 15000 // 15 seconds timeout
            });
            
            console.log('Received response for custom targeting keys:', response.data);
            
            if (Array.isArray(response.data)) {
                setAvailableKeys(response.data);
            } else {
                console.error('Unexpected response format:', response.data);
                setError('Received unexpected response format from server');
                setAvailableKeys([]);
            }
        } catch (err) {
            console.error('Failed to load custom targeting keys:', err);
            setError('Failed to load custom targeting keys: ' + (err.response?.data?.message || err.message));
            setAvailableKeys([]);
        } finally {
            setLoadingKeys(false);
        }
    };

    const loadCustomTargetingValues = async (selectedKey, query) => {
        try {
            console.log('Starting request to load custom targeting values');
            setLoadingValues(true);
            setError(null);
            
            // Find the key ID from the available keys or use the key directly if it's an object
            let keyId;
            if (typeof selectedKey === 'object' && selectedKey !== null && selectedKey.id) {
                keyId = selectedKey.id;
                console.log('Using key object directly:', selectedKey);
            } else {
                const keyObj = availableKeys.find(k => k.name === selectedKey);
                if (!keyObj || !keyObj.id) {
                    throw new Error('Selected key not found or missing ID');
                }
                keyId = keyObj.id;
                console.log('Found key ID from available keys:', keyId);
            }
            
            const url = `/line-items/custom-targeting-values?key_id=${keyId}&search=${encodeURIComponent(query)}`;
            console.log('Request URL:', url);
            
            const response = await axios.get(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                timeout: 15000 // 15 seconds timeout
            });
            
            console.log('Received response for custom targeting values:', response.data);
            
            if (response.data.status === 'success') {
                if (Array.isArray(response.data.values)) {
                    console.log(`Found ${response.data.values.length} values`);
                    setAvailableValues(response.data.values);
                } else if (response.data.values) {
                    console.log('Values returned but not in expected format:', response.data.values);
                    setError('Received values in unexpected format');
                    setAvailableValues([]);
                } else {
                    console.log('No values found in response');
                    setAvailableValues([]);
                }
            } else if (Array.isArray(response.data)) {
                // Handle legacy format
                console.log(`Found ${response.data.length} values (legacy format)`);
                setAvailableValues(response.data);
            } else {
                console.error('Unexpected response format:', response.data);
                setError('Received unexpected response format from server');
                setAvailableValues([]);
            }
        } catch (err) {
            console.error('Failed to load custom targeting values:', err);
            setError('Failed to load custom targeting values: ' + (err.response?.data?.message || err.message));
            setAvailableValues([]);
        } finally {
            setLoadingValues(false);
        }
    };

    const handleAdd = () => {
        if (!key.trim() || !value.trim()) {
            return;
        }
        
        // Check if this key-value pair already exists
        const exists = selectedTargeting.some(
            target => target.key === key && target.value === value
        );
        
        if (exists) {
            setError('This key-value pair is already added');
            return;
        }
        
        const newTargeting = [
            ...selectedTargeting,
            { key, value }
        ];
        
        onChange(newTargeting);
        
        // Reset the value field but keep the key for adding multiple values
        setValue('');
        setValueQuery('');
        setShowValueDropdown(false);
    };

    const handleRemove = (indexToRemove) => {
        const newTargeting = selectedTargeting.filter((_, index) => index !== indexToRemove);
        onChange(newTargeting);
    };

    const handleKeySelect = (selectedKey) => {
        console.log('Selected key:', selectedKey);
        setKey(selectedKey.name);
        setKeyQuery(selectedKey.name);
        setShowKeyDropdown(false);
        // Reset value when key changes
        setValue('');
        setValueQuery('');
        setAvailableValues([]);
        setError(null); // Clear any previous errors
        
        // Pre-load values for this key with an empty search
        if (selectedKey.id) {
            console.log('Pre-loading values for key ID:', selectedKey.id);
            loadCustomTargetingValues(selectedKey, '');
            // Show the value dropdown automatically
            setShowValueDropdown(true);
        }
    };

    const handleValueSelect = (selectedValue) => {
        console.log('Selected value:', selectedValue);
        setValue(selectedValue.name);
        setValueQuery(selectedValue.name);
        setShowValueDropdown(false);
        setError(null); // Clear any previous errors
    };

    const handleRetry = () => {
        console.log('Retrying custom targeting key search');
        setError(null);
        if (keyQuery.length >= 2) {
            loadCustomTargetingKeys(keyQuery);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col md:flex-row gap-4">
                <div className="form-control flex-1 relative" ref={keyContainerRef}>
                    <label className="label">
                        <span className="label-text">Custom Targeting Key</span>
                    </label>
                    <input
                        type="text"
                        className="input input-bordered w-full h-12 text-base border-black"
                        placeholder="Search for key..."
                        value={keyQuery}
                        onChange={(e) => {
                            console.log('Key query changed:', e.target.value);
                            setKeyQuery(e.target.value);
                            setShowKeyDropdown(true);
                            if (error) setError(null); // Clear error when user types
                        }}
                        onFocus={() => setShowKeyDropdown(true)}
                    />
                    
                    {showKeyDropdown && (
                        <div 
                            ref={keyContainerRef}
                            className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg"
                            role="listbox"
                        >
                            {loadingKeys ? (
                                <div className="p-4 text-center">
                                    <LoadingSpinner size="sm" />
                                </div>
                            ) : error ? (
                                <div className="p-4 text-center text-red-500">
                                    <p>{error}</p>
                                    <button 
                                        className="btn btn-sm btn-outline mt-2"
                                        onClick={handleRetry}
                                    >
                                        Retry
                                    </button>
                                </div>
                            ) : availableKeys.length === 0 && keyQuery.length >= 2 ? (
                                <div className="p-4 text-center text-gray-500">
                                    No keys found
                                </div>
                            ) : availableKeys.length > 0 ? (
                                <ul role="listbox">
                                    {availableKeys.map((key, index) => (
                                        <li 
                                            key={index} 
                                            className="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0"
                                            onClick={() => handleKeySelect(key)}
                                            role="option"
                                        >
                                            <div className="font-medium">{key.name}</div>
                                            {key.displayName && key.displayName !== key.name && (
                                                <div className="text-xs text-gray-500">{key.displayName}</div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>
                    )}
                </div>
                
                <div className="form-control flex-1 relative" ref={valueContainerRef}>
                    <label className="label">
                        <span className="label-text">Custom Targeting Value</span>
                    </label>
                    <input
                        type="text"
                        className={`input input-bordered w-full h-12 text-base border-black ${!key ? 'opacity-50' : ''}`}
                        placeholder="Search for value..."
                        value={valueQuery}
                        onChange={(e) => {
                            console.log('Value query changed:', e.target.value);
                            setValueQuery(e.target.value);
                            setShowValueDropdown(true);
                            if (error) setError(null); // Clear error when user types
                        }}
                        onFocus={() => setShowValueDropdown(true)}
                        disabled={!key}
                    />
                    
                    {showValueDropdown && (
                        <div 
                            ref={valueContainerRef}
                            className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg"
                            role="listbox"
                        >
                            {loadingValues ? (
                                <div className="p-4 text-center">
                                    <LoadingSpinner size="sm" />
                                </div>
                            ) : error ? (
                                <div className="p-4 text-center text-red-500">
                                    <p>{error}</p>
                                    <button 
                                        className="btn btn-sm btn-outline mt-2"
                                        onClick={() => loadCustomTargetingValues(key, valueQuery)}
                                    >
                                        Retry
                                    </button>
                                </div>
                            ) : availableValues.length === 0 ? (
                                <div className="p-4 text-center text-gray-500">
                                    No values found
                                </div>
                            ) : availableValues.length > 0 ? (
                                <ul role="listbox">
                                    {availableValues.map((value, index) => (
                                        <li 
                                            key={index} 
                                            className="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0"
                                            onClick={() => handleValueSelect(value)}
                                            role="option"
                                        >
                                            <div className="font-medium">{value.name}</div>
                                            {value.displayName && value.displayName !== value.name && (
                                                <div className="text-xs text-gray-500">{value.displayName}</div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>
                    )}
                </div>
            </div>
            
            <div className="flex justify-end">
                <button
                    type="button"
                    className="btn btn-primary"
                    onClick={handleAdd}
                    disabled={!key.trim() || !value.trim()}
                >
                    Add
                </button>
            </div>

            {error && (
                <div className="alert alert-error text-sm">
                    <div>
                        <span>{error}</span>
                        <button 
                            className="btn btn-xs btn-outline ml-2" 
                            onClick={handleRetry}
                        >
                            Retry
                        </button>
                    </div>
                </div>
            )}

            <div className="flex flex-wrap gap-2">
                {selectedTargeting.map((target, index) => (
                    <span
                        key={index}
                        className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary text-white"
                    >
                        {target.key}={target.value}
                        <button
                            type="button"
                            onClick={() => handleRemove(index)}
                            className="ml-1 inline-flex items-center p-0.5 rounded-full hover:bg-primary-focus focus:outline-none"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                            </svg>
                        </button>
                    </span>
                ))}
            </div>
        </div>
    );
} 