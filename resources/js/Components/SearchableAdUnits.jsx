import React from 'react';
import { useState, useEffect } from 'react';
import { Combobox } from '@headlessui/react';
import axios from 'axios';
import LoadingSpinner from '@/Components/LoadingSpinner';

export default function SearchableAdUnits({ selectedAdUnits = [], onChange }) {
    const [query, setQuery] = useState('');
    const [adUnits, setAdUnits] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    // Debounce search
    useEffect(() => {
        if (query.length < 2) {
            setAdUnits([]);
            return;
        }

        const timeoutId = setTimeout(() => {
            loadAdUnits();
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [query]);

    const loadAdUnits = async () => {
        if (query.length < 2) return;
        
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get(`/line-items/available-ad-units?search=${encodeURIComponent(query)}`);
            if (response.data.status === 'success') {
                // Limit to 10 results
                setAdUnits(response.data.adUnits.slice(0, 10));
            } else {
                throw new Error(response.data.message || 'Failed to load ad units');
            }
        } catch (err) {
            console.error('Error loading ad units:', err);
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const handleSelect = (adUnit) => {
        const isSelected = selectedAdUnits.some(selected => selected.id === adUnit.id);
        let newAdUnits;
        
        if (isSelected) {
            newAdUnits = selectedAdUnits.filter(selected => selected.id !== adUnit.id);
        } else {
            newAdUnits = [...selectedAdUnits, adUnit];
        }
        
        onChange(newAdUnits);
    };

    return (
        <div className="relative w-full">
            <Combobox value={selectedAdUnits} onChange={onChange} multiple>
                <div className="relative">
                    <div className="relative w-full cursor-default overflow-hidden rounded-lg bg-white text-left border border-black focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75 focus-visible:ring-offset-2 focus-visible:ring-offset-primary sm:text-sm">
                        <div className="flex flex-wrap gap-1 p-1">
                            {selectedAdUnits.map((adUnit) => (
                                <span
                                    key={adUnit.id}
                                    className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary text-white"
                                >
                                    {adUnit.name}
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            handleSelect(adUnit);
                                        }}
                                        className="ml-1 inline-flex items-center p-0.5 rounded-full hover:bg-primary-focus focus:outline-none"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                        </svg>
                                    </button>
                                </span>
                            ))}
                            <Combobox.Input
                                className="w-full border-none py-2 pl-3 pr-10 text-sm leading-5 text-gray-900 focus:ring-0"
                                placeholder="Type to search ad units..."
                                onChange={(event) => setQuery(event.target.value)}
                                value={query}
                            />
                        </div>
                        <Combobox.Button className="absolute inset-y-0 right-0 flex items-center pr-2">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                            </svg>
                        </Combobox.Button>
                    </div>
                    <Combobox.Options className="absolute mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm z-50">
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
                        ) : adUnits.length === 0 ? (
                            <div className="relative cursor-default select-none py-2 px-4 text-gray-700">
                                No ad units found.
                            </div>
                        ) : (
                            adUnits.map((adUnit) => (
                                <Combobox.Option
                                    key={adUnit.id}
                                    value={adUnit}
                                    className={({ active }) =>
                                        `relative cursor-pointer select-none py-2 pl-10 pr-4 ${
                                            active ? 'bg-primary text-white' : 'text-gray-900'
                                        }`
                                    }
                                >
                                    {({ selected, active }) => (
                                        <>
                                            <span className={`block ${selected ? 'font-medium' : 'font-normal'}`}>
                                                <div>{adUnit.name}</div>
                                                <div className="text-xs text-gray-500 dark:text-gray-400">
                                                    {adUnit.path}
                                                </div>
                                            </span>
                                            {selected ? (
                                                <span
                                                    className={`absolute inset-y-0 left-0 flex items-center pl-3 ${
                                                        active ? 'text-white' : 'text-primary'
                                                    }`}
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                </span>
                                            ) : null}
                                        </>
                                    )}
                                </Combobox.Option>
                            ))
                        )}
                    </Combobox.Options>
                </div>
            </Combobox>
        </div>
    );
} 