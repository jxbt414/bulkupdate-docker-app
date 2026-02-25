import React from 'react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import LoadingSpinner from './LoadingSpinner';

export default function TargetingCriteria({ 
    selectedTargeting = [], 
    selectedAudienceSegments = [],
    selectedCmsMetadata = [],
    onChange = () => {} 
}) {
    // State for Custom Targeting
    const [customKey, setCustomKey] = useState('');
    const [customValue, setCustomValue] = useState('');
    const [customKeyQuery, setCustomKeyQuery] = useState('');
    const [customValueQuery, setCustomValueQuery] = useState('');
    const [availableCustomKeys, setAvailableCustomKeys] = useState([]);
    const [availableCustomValues, setAvailableCustomValues] = useState([]);
    const [customLogicalOperator, setCustomLogicalOperator] = useState('IS_ANY_OF');

    // State for Audience Segments
    const [audienceQuery, setAudienceQuery] = useState('');
    const [availableAudiences, setAvailableAudiences] = useState([]);
    const [audienceLogicalOperator, setAudienceLogicalOperator] = useState('IS_ANY_OF');

    // State for CMS Metadata
    const [cmsMetadataKey, setCmsMetadataKey] = useState('');
    const [cmsMetadataValue, setCmsMetadataValue] = useState('');
    const [cmsMetadataKeyQuery, setCmsMetadataKeyQuery] = useState('');
    const [cmsMetadataValueQuery, setCmsMetadataValueQuery] = useState('');
    const [availableCmsKeys, setAvailableCmsKeys] = useState([]);
    const [availableCmsValues, setAvailableCmsValues] = useState([]);
    const [cmsLogicalOperator, setCmsLogicalOperator] = useState('IS_ANY_OF');

    // Loading and error states
    const [loading, setLoading] = useState({
        custom: false,
        audience: false,
        cms: false
    });
    const [error, setError] = useState(null);

    // Dropdown visibility states
    const [showDropdowns, setShowDropdowns] = useState({
        customKey: false,
        customValue: false,
        audience: false,
        cmsKey: false,
        cmsValue: false
    });

    // Refs for dropdowns
    const dropdownRefs = {
        customKey: useRef(null),
        customValue: useRef(null),
        audience: useRef(null),
        cmsKey: useRef(null),
        cmsValue: useRef(null)
    };

    // Initialize state from props
    useEffect(() => {
        if (selectedTargeting) {
            // Handle selected targeting
        }
        if (selectedAudienceSegments) {
            // Handle selected audience segments
        }
        if (selectedCmsMetadata) {
            // Handle selected CMS metadata
        }
    }, [selectedTargeting, selectedAudienceSegments, selectedCmsMetadata]);

    // Handle click outside to close dropdowns
    useEffect(() => {
        const handleClickOutside = (event) => {
            Object.entries(dropdownRefs).forEach(([key, ref]) => {
                if (ref.current && !ref.current.contains(event.target)) {
                    setShowDropdowns(prev => ({ ...prev, [key]: false }));
                }
            });
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Load data functions
    const loadCustomTargetingKeys = async (query) => {
        if (query.length < 2) {
            setAvailableCustomKeys([]);
            return;
        }
        
        setLoading(prev => ({ ...prev, custom: true }));
        setError(null);
        try {
            const response = await axios.get(`/line-items/custom-targeting-keys?search=${encodeURIComponent(query)}`);
            setAvailableCustomKeys(Array.isArray(response.data) ? response.data : []);
        } catch (err) {
            setError('Failed to load custom targeting keys: ' + err.message);
            setAvailableCustomKeys([]);
        } finally {
            setLoading(prev => ({ ...prev, custom: false }));
        }
    };

    const loadCustomTargetingValues = async (selectedKey, query) => {
        if (!selectedKey) return;
        
        setLoading(prev => ({ ...prev, custom: true }));
        setError(null);
        try {
            const response = await axios.get(`/line-items/custom-targeting-values?key_id=${selectedKey.id}&search=${encodeURIComponent(query)}`);
            setAvailableCustomValues(response.data.values || []);
        } catch (err) {
            setError('Failed to load custom targeting values: ' + err.message);
        } finally {
            setLoading(prev => ({ ...prev, custom: false }));
        }
    };

    const loadAudienceSegments = async (query) => {
        if (query.length < 2) return;
        
        setLoading(prev => ({ ...prev, audience: true }));
        setError(null);
        try {
            const response = await axios.get(`/line-items/audience-segments?search=${encodeURIComponent(query)}`);
            setAvailableAudiences(response.data.segments || []);
        } catch (err) {
            setError('Failed to load audience segments: ' + err.message);
        } finally {
            setLoading(prev => ({ ...prev, audience: false }));
        }
    };

    const loadCmsMetadataKeys = async (query) => {
        if (query.length < 2) return;
        
        setLoading(prev => ({ ...prev, cms: true }));
        setError(null);
        try {
            const response = await axios.get(`/line-items/cms-metadata-keys?search=${encodeURIComponent(query)}`);
            setAvailableCmsKeys(response.data.keys || []);
        } catch (err) {
            setError('Failed to load CMS metadata keys: ' + err.message);
        } finally {
            setLoading(prev => ({ ...prev, cms: false }));
        }
    };

    const loadCmsMetadataValues = async (selectedKey, query) => {
        if (!selectedKey) return;
        
        setLoading(prev => ({ ...prev, cms: true }));
        setError(null);
        try {
            console.log('Fetching CMS metadata values:', { keyId: selectedKey.id, query });
            const response = await axios.get(`/line-items/cms-metadata-values?key_id=${selectedKey.id}&search=${encodeURIComponent(query || '')}`);
            console.log('CMS metadata values response:', response.data);
            setAvailableCmsValues(response.data.values || []);
            
            // Always show dropdown when values are loaded
            setShowDropdowns(prev => ({ ...prev, cmsValue: true }));
        } catch (err) {
            console.error('Failed to load CMS metadata values:', err);
            setError('Failed to load CMS metadata values: ' + err.message);
        } finally {
            setLoading(prev => ({ ...prev, cms: false }));
        }
    };

    // Handle add functions
    const handleAddCustomTargeting = () => {
        if (!customKey || !customValue) return;
        
        const newTargeting = [
            ...selectedTargeting,
            { 
                key: customKey.name,
                value: customValue.name,
                operator: customLogicalOperator
            }
        ];
        
        onChange({
            type: 'custom',
            targeting: newTargeting
        });
        
        setCustomValue('');
        setCustomValueQuery('');
    };

    const handleAddAudienceSegment = (segment) => {
        console.log('Adding audience segment:', segment);
        const newSegments = [
            ...selectedAudienceSegments,
            {
                id: segment.id,
                name: segment.name,
                operator: audienceLogicalOperator
            }
        ];
        
        console.log('New segments:', newSegments);
        onChange({
            type: 'audience',
            segments: newSegments
        });
        
        setAudienceQuery('');
    };

    const handleAddCmsMetadata = () => {
        if (!cmsMetadataKey || !cmsMetadataValue) return;
        
        const newMetadata = [
            ...selectedCmsMetadata,
            {
                key: cmsMetadataKey.name,
                value: cmsMetadataValue.name,
                operator: cmsLogicalOperator
            }
        ];
        
        onChange({
            type: 'cms',
            metadata: newMetadata
        });
        
        setCmsMetadataValue('');
        setCmsMetadataValueQuery('');
    };

    // Handle remove functions
    const handleRemoveCustomTargeting = (index) => {
        const newTargeting = selectedTargeting.filter((_, i) => i !== index);
        onChange({
            type: 'custom',
            targeting: newTargeting
        });
    };

    const handleRemoveAudienceSegment = (segmentId) => {
        const newSegments = selectedAudienceSegments.filter(segment => segment.id !== segmentId);
        onChange({
            type: 'audience',
            segments: newSegments
        });
    };

    const handleRemoveCmsMetadata = (index) => {
        const newMetadata = selectedCmsMetadata.filter((_, i) => i !== index);
        onChange({
            type: 'cms',
            metadata: newMetadata
        });
    };

    return (
        <div className="space-y-8">
            {/* Custom Targeting Section */}
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-medium">Custom Targeting</h3>
                    <select
                        className="select select-bordered border-black"
                        value={customLogicalOperator}
                        onChange={(e) => setCustomLogicalOperator(e.target.value)}
                    >
                        <option value="IS_ANY_OF">Is any of</option>
                        <option value="IS_NONE_OF">Is none of</option>
                    </select>
                </div>
                
                <div className="flex flex-col md:flex-row gap-4">
                    {/* Custom Key Input */}
                    <div className="form-control flex-1 relative" ref={dropdownRefs.customKey}>
                        <label className="label">
                            <span className="label-text">Custom Targeting Key</span>
                        </label>
                        <input
                            type="text"
                            className="input input-bordered w-full border-black"
                            placeholder="Search for key..."
                            value={customKeyQuery}
                            onChange={(e) => {
                                setCustomKeyQuery(e.target.value);
                                setShowDropdowns(prev => ({ ...prev, customKey: true }));
                                loadCustomTargetingKeys(e.target.value);
                            }}
                        />
                        {/* Dropdown for custom keys */}
                        {showDropdowns.customKey && (
                            <div className="absolute left-0 top-full z-10 w-full mt-1 bg-white shadow-lg rounded-md border border-black max-h-60 overflow-auto">
                                {loading.custom ? (
                                    <div className="p-4 text-center">
                                        <LoadingSpinner size="sm" />
                                    </div>
                                ) : availableCustomKeys.map((key, index) => (
                                    <div
                                        key={index}
                                        className="p-2 hover:bg-gray-100 cursor-pointer"
                                        onClick={() => {
                                            setCustomKey(key);
                                            setCustomKeyQuery(key.name);
                                            setShowDropdowns(prev => ({ ...prev, customKey: false }));
                                        }}
                                    >
                                        {key.name}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Custom Value Input */}
                    <div className="form-control flex-1 relative" ref={dropdownRefs.customValue}>
                        <label className="label">
                            <span className="label-text">Custom Targeting Value</span>
                        </label>
                        <input
                            type="text"
                            className="input input-bordered w-full border-black"
                            placeholder="Search for value..."
                            value={customValueQuery}
                            onChange={(e) => {
                                setCustomValueQuery(e.target.value);
                                setShowDropdowns(prev => ({ ...prev, customValue: true }));
                                if (customKey) {
                                    loadCustomTargetingValues(customKey, e.target.value);
                                }
                            }}
                            disabled={!customKey}
                        />
                        {/* Dropdown for custom values */}
                        {showDropdowns.customValue && customKey && (
                            <div className="absolute left-0 top-full z-10 w-full mt-1 bg-white shadow-lg rounded-md border border-black max-h-60 overflow-auto">
                                {loading.custom ? (
                                    <div className="p-4 text-center">
                                        <LoadingSpinner size="sm" />
                                    </div>
                                ) : availableCustomValues.map((value, index) => (
                                    <div
                                        key={index}
                                        className="p-2 hover:bg-gray-100 cursor-pointer"
                                        onClick={() => {
                                            setCustomValue(value);
                                            setCustomValueQuery(value.name);
                                            setShowDropdowns(prev => ({ ...prev, customValue: false }));
                                        }}
                                    >
                                        {value.name}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex justify-end">
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={handleAddCustomTargeting}
                        disabled={!customKey || !customValue}
                    >
                        Add Custom Targeting
                    </button>
                </div>

                {/* Display selected custom targeting */}
                <div className="flex flex-wrap gap-2">
                    {selectedTargeting.map((target, index) => (
                        <span
                            key={index}
                            className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary text-white"
                        >
                            {`${target.key} ${target.operator === 'IS_ANY_OF' ? 'is any of' : 'is none of'} ${target.value}`}
                            <button
                                type="button"
                                onClick={() => handleRemoveCustomTargeting(index)}
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

            {/* Audience Segments Section */}
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-medium">Audience Segments</h3>
                    <select
                        className="select select-bordered border-black"
                        value={audienceLogicalOperator}
                        onChange={(e) => setAudienceLogicalOperator(e.target.value)}
                    >
                        <option value="IS_ANY_OF">Is any of</option>
                        <option value="IS_NONE_OF">Is none of</option>
                    </select>
                </div>

                <div className="form-control relative" ref={dropdownRefs.audience}>
                    <input
                        type="text"
                        className="input input-bordered w-full border-black"
                        placeholder="Search for audience segments..."
                        value={audienceQuery}
                        onChange={(e) => {
                            setAudienceQuery(e.target.value);
                            setShowDropdowns(prev => ({ ...prev, audience: true }));
                            loadAudienceSegments(e.target.value);
                        }}
                    />
                    {/* Dropdown for audience segments */}
                    {showDropdowns.audience && (
                        <div className="absolute left-0 top-full z-10 w-full mt-1 bg-white shadow-lg rounded-md border border-black max-h-60 overflow-auto">
                            {loading.audience ? (
                                <div className="p-4 text-center">
                                    <LoadingSpinner size="sm" />
                                </div>
                            ) : availableAudiences.map((segment) => (
                                <div
                                    key={segment.id}
                                    className="p-2 hover:bg-gray-100 cursor-pointer"
                                    onClick={() => {
                                        handleAddAudienceSegment(segment);
                                        setShowDropdowns(prev => ({ ...prev, audience: false }));
                                    }}
                                >
                                    {segment.name}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Display selected audience segments */}
                <div className="flex flex-wrap gap-2">
                    {selectedAudienceSegments && selectedAudienceSegments.map((segment) => (
                        <span
                            key={segment.id}
                            className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary text-white"
                        >
                            {segment.name}
                            <button
                                type="button"
                                onClick={() => handleRemoveAudienceSegment(segment.id)}
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

            {/* CMS Metadata Section */}
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-medium">CMS Metadata Criteria</h3>
                    <select
                        className="select select-bordered border-black"
                        value={cmsLogicalOperator}
                        onChange={(e) => setCmsLogicalOperator(e.target.value)}
                    >
                        <option value="IS_ANY_OF">Is any of</option>
                        <option value="IS_NONE_OF">Is none of</option>
                    </select>
                </div>

                <div className="flex flex-col md:flex-row gap-4">
                    {/* CMS Metadata Key Input */}
                    <div className="form-control flex-1 relative" ref={dropdownRefs.cmsKey}>
                        <label className="label">
                            <span className="label-text">CMS Metadata Key</span>
                        </label>
                        <input
                            type="text"
                            className="input input-bordered w-full border-black"
                            placeholder="Search for key..."
                            value={cmsMetadataKeyQuery}
                            onChange={(e) => {
                                setCmsMetadataKeyQuery(e.target.value);
                                setShowDropdowns(prev => ({ ...prev, cmsKey: true }));
                                loadCmsMetadataKeys(e.target.value);
                            }}
                        />
                        {/* Dropdown for CMS metadata keys */}
                        {showDropdowns.cmsKey && (
                            <div className="absolute left-0 top-full z-10 w-full mt-1 bg-white shadow-lg rounded-md border border-black max-h-60 overflow-auto">
                                {loading.cms ? (
                                    <div className="p-4 text-center">
                                        <LoadingSpinner size="sm" />
                                    </div>
                                ) : availableCmsKeys.map((key, index) => (
                                    <div
                                        key={index}
                                        className="p-2 hover:bg-gray-100 cursor-pointer"
                                        onClick={() => {
                                            setCmsMetadataKey(key);
                                            setCmsMetadataKeyQuery(key.name);
                                            setShowDropdowns(prev => ({ ...prev, cmsKey: false }));
                                            
                                            // Automatically load values when a key is selected
                                            loadCmsMetadataValues(key, '');
                                        }}
                                    >
                                        {key.name}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* CMS Metadata Value Input */}
                    <div className="form-control flex-1 relative" ref={dropdownRefs.cmsValue}>
                        <label className="label">
                            <span className="label-text">CMS Metadata Value</span>
                        </label>
                        <input
                            type="text"
                            className="input input-bordered w-full border-black"
                            placeholder="Search for value..."
                            value={cmsMetadataValueQuery}
                            onChange={(e) => {
                                setCmsMetadataValueQuery(e.target.value);
                                setShowDropdowns(prev => ({ ...prev, cmsValue: true }));
                                if (cmsMetadataKey) {
                                    loadCmsMetadataValues(cmsMetadataKey, e.target.value);
                                }
                            }}
                            onFocus={() => {
                                // Load values when input is focused, even without typing
                                if (cmsMetadataKey) {
                                    loadCmsMetadataValues(cmsMetadataKey, cmsMetadataValueQuery);
                                }
                            }}
                            disabled={!cmsMetadataKey}
                        />
                        {/* Dropdown for CMS metadata values */}
                        {showDropdowns.cmsValue && cmsMetadataKey && (
                            <div className="absolute left-0 top-full z-10 w-full mt-1 bg-white shadow-lg rounded-md border border-black max-h-60 overflow-auto">
                                {loading.cms ? (
                                    <div className="p-4 text-center">
                                        <LoadingSpinner size="sm" />
                                    </div>
                                ) : availableCmsValues.map((value, index) => (
                                    <div
                                        key={index}
                                        className="p-2 hover:bg-gray-100 cursor-pointer"
                                        onClick={() => {
                                            setCmsMetadataValue(value);
                                            setCmsMetadataValueQuery(value.name);
                                            setShowDropdowns(prev => ({ ...prev, cmsValue: false }));
                                        }}
                                    >
                                        {value.name}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex justify-end">
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={handleAddCmsMetadata}
                        disabled={!cmsMetadataKey || !cmsMetadataValue}
                    >
                        Add CMS Metadata
                    </button>
                </div>

                {/* Display selected CMS metadata */}
                <div className="flex flex-wrap gap-2">
                    {selectedCmsMetadata.map((metadata, index) => (
                        <span
                            key={index}
                            className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary text-white"
                        >
                            {`${metadata.key} ${metadata.operator === 'IS_ANY_OF' ? 'is any of' : 'is none of'} ${metadata.value}`}
                            <button
                                type="button"
                                onClick={() => handleRemoveCmsMetadata(index)}
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

            {error && (
                <div className="alert alert-error text-sm">
                    <div>
                        <span>{error}</span>
                    </div>
                </div>
            )}
        </div>
    );
} 