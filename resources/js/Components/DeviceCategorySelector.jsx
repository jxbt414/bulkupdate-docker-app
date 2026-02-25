import React from 'react';
import { useState, useEffect, useRef } from 'react';

export default function DeviceCategorySelector({ selectedDevices = [], onChange }) {
    const [showDropdown, setShowDropdown] = useState(false);
    const containerRef = useRef(null);
    
    // Available device categories
    const deviceCategories = [
        { id: 'DESKTOP', name: 'Desktop' },
        { id: 'MOBILE', name: 'Mobile' },
        { id: 'TABLET', name: 'Tablet' },
        { id: 'CONNECTED_TV', name: 'Connected TV' },
        { id: 'SET_TOP_BOX', name: 'Set-top Box' }
    ];

    // Handle click outside to close dropdown
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setShowDropdown(false);
            }
        };

        // Add event listener
        document.addEventListener('mousedown', handleClickOutside);
        
        // Clean up
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    const handleDeviceSelect = (device) => {
        // Check if device is already selected
        if (!selectedDevices.some(d => d.id === device.id)) {
            const newDevices = [...selectedDevices, device];
            onChange(newDevices);
        }
        setShowDropdown(false);
    };

    const handleRemove = (deviceToRemove) => {
        const newDevices = selectedDevices.filter(device => device.id !== deviceToRemove.id);
        onChange(newDevices);
    };

    return (
        <div className="w-full">
            {/* Selected devices display */}
            <div className="flex flex-wrap gap-2 mb-2">
                {selectedDevices.map((device, index) => (
                    <div 
                        key={index} 
                        className="badge badge-primary badge-lg gap-1 p-3"
                    >
                        <span>{device.name}</span>
                        <button 
                            type="button" 
                            className="btn btn-xs btn-circle btn-ghost"
                            onClick={() => handleRemove(device)}
                        >
                            ×
                        </button>
                    </div>
                ))}
            </div>
            
            {/* Device selector */}
            <div className="form-control relative" ref={containerRef}>
                <label className="label">
                    <span className="label-text">Device Categories</span>
                </label>
                <input
                    type="text"
                    className="input input-bordered w-full h-12 text-base"
                    placeholder="Select device categories..."
                    onFocus={() => setShowDropdown(true)}
                    readOnly
                />
                
                {showDropdown && (
                    <div className="absolute left-0 top-full z-10 w-full mt-1 bg-white shadow-lg rounded-md border border-gray-300 max-h-80 overflow-auto">
                        <ul>
                            {deviceCategories.map((device, index) => (
                                <li 
                                    key={index} 
                                    className={`p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0 ${
                                        selectedDevices.some(d => d.id === device.id) ? 'bg-gray-100' : ''
                                    }`}
                                    onClick={() => handleDeviceSelect(device)}
                                >
                                    <div className="font-medium">{device.name}</div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
} 