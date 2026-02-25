import React from 'react';
import { useState, useEffect, useRef } from 'react';

export default function DayPartSelector({ selectedDayParts = [], onChange }) {
    const [showTimeSelector, setShowTimeSelector] = useState(false);
    const [selectedDays, setSelectedDays] = useState({
        Sun: true,
        Mon: true,
        Tue: true,
        Wed: true,
        Thu: true,
        Fri: true,
        Sat: true
    });
    const [startTime, setStartTime] = useState('12:00 AM');
    const [endTime, setEndTime] = useState('12:00 AM');
    const [usePublisherTimeZone, setUsePublisherTimeZone] = useState(true);
    const [dontRunOnTheseDays, setDontRunOnTheseDays] = useState(false);
    
    const containerRef = useRef(null);
    
    // Available time options
    const timeOptions = [
        '12:00 AM', '12:30 AM', 
        '1:00 AM', '1:30 AM', 
        '2:00 AM', '2:30 AM', 
        '3:00 AM', '3:30 AM', 
        '4:00 AM', '4:30 AM', 
        '5:00 AM', '5:30 AM', 
        '6:00 AM', '6:30 AM', 
        '7:00 AM', '7:30 AM', 
        '8:00 AM', '8:30 AM', 
        '9:00 AM', '9:30 AM', 
        '10:00 AM', '10:30 AM', 
        '11:00 AM', '11:30 AM',
        '12:00 PM', '12:30 PM', 
        '1:00 PM', '1:30 PM', 
        '2:00 PM', '2:30 PM', 
        '3:00 PM', '3:30 PM', 
        '4:00 PM', '4:30 PM', 
        '5:00 PM', '5:30 PM', 
        '6:00 PM', '6:30 PM', 
        '7:00 PM', '7:30 PM', 
        '8:00 PM', '8:30 PM', 
        '9:00 PM', '9:30 PM', 
        '10:00 PM', '10:30 PM', 
        '11:00 PM', '11:30 PM'
    ];

    // Handle click outside to close dropdown
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (containerRef.current && !containerRef.current.contains(event.target)) {
                setShowTimeSelector(false);
            }
        };

        // Add event listener
        document.addEventListener('mousedown', handleClickOutside);
        
        // Clean up
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    const toggleDay = (day) => {
        setSelectedDays(prev => ({
            ...prev,
            [day]: !prev[day]
        }));
    };

    const handleAddTimePeriod = () => {
        // Format the selected days
        const days = Object.entries(selectedDays)
            .filter(([_, isSelected]) => isSelected)
            .map(([day, _]) => day);
            
        if (days.length === 0) {
            return; // No days selected
        }
        
        const newDayPart = {
            days,
            startTime,
            endTime,
            usePublisherTimeZone,
            dontRunOnTheseDays
        };
        
        onChange([...selectedDayParts, newDayPart]);
        
        // Reset form for next entry
        setShowTimeSelector(false);
    };

    const handleRemove = (index) => {
        const newDayParts = [...selectedDayParts];
        newDayParts.splice(index, 1);
        onChange(newDayParts);
    };

    const formatDaysList = (days) => {
        if (days.length === 7) {
            return 'All days';
        }
        
        if (days.length > 3) {
            return days.join(', ');
        }
        
        return days.join(', ');
    };

    return (
        <div className="w-full" ref={containerRef}>
            <div className="mb-2">
                <label className="label">
                    <span className="label-text font-medium text-base">Day Part Targeting</span>
                </label>
            </div>
            
            <div className="mb-4">
                <label className="flex items-center mb-2">
                    <input 
                        type="checkbox" 
                        className="checkbox checkbox-primary mr-2" 
                        checked={showTimeSelector}
                        onChange={() => setShowTimeSelector(!showTimeSelector)}
                    />
                    <span className="text-lg font-medium">Set days and times</span>
                </label>
                <div className="text-sm text-gray-500 ml-7">
                    Schedule line items using
                </div>
            </div>
            
            {showTimeSelector && (
                <div className="bg-gray-100 p-4 rounded-lg mb-4">
                    <div className="flex gap-4 mb-4">
                        <label className="flex items-center cursor-pointer">
                            <input 
                                type="radio" 
                                name="timeZone" 
                                className="radio radio-primary mr-2 hover:opacity-100 appearance-none checked:opacity-100 checked:bg-primary" 
                                checked={usePublisherTimeZone}
                                onChange={() => setUsePublisherTimeZone(true)}
                            />
                            <span>Publisher's time zone</span>
                        </label>
                        <label className="flex items-center cursor-pointer">
                            <input 
                                type="radio" 
                                name="timeZone" 
                                className="radio radio-primary mr-2 hover:opacity-100 appearance-none checked:opacity-100 checked:bg-primary" 
                                checked={!usePublisherTimeZone}
                                onChange={() => setUsePublisherTimeZone(false)}
                            />
                            <span>User's time zone</span>
                        </label>
                    </div>
                    
                    <div className="mb-4">
                        <div className="font-medium mb-2">
                            {dontRunOnTheseDays ? 'Don\'t run on these days:' : 'Repeating on:'}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => (
                                <button
                                    key={day}
                                    type="button"
                                    className={`btn btn-circle ${selectedDays[day] ? 'btn-primary' : 'btn-outline'}`}
                                    onClick={() => toggleDay(day)}
                                >
                                    {day}
                                </button>
                            ))}
                        </div>
                    </div>
                    
                    <div className="flex flex-wrap gap-6 mb-4">
                        <div className="form-control">
                            <label className="label">
                                <span className="label-text">Start time*</span>
                            </label>
                            <select
                                className="select select-bordered w-40"
                                value={startTime}
                                onChange={(e) => setStartTime(e.target.value)}
                            >
                                {timeOptions.map((time) => (
                                    <option key={time} value={time}>{time}</option>
                                ))}
                            </select>
                        </div>
                        
                        <div className="form-control">
                            <label className="label">
                                <span className="label-text">End time*</span>
                            </label>
                            <select
                                className="select select-bordered w-40"
                                value={endTime}
                                onChange={(e) => setEndTime(e.target.value)}
                            >
                                {timeOptions.map((time) => (
                                    <option key={time} value={time}>{time}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    
                    <div className="mb-4">
                        <label className="flex items-center">
                            <input 
                                type="checkbox" 
                                className="checkbox checkbox-primary mr-2" 
                                checked={dontRunOnTheseDays}
                                onChange={() => setDontRunOnTheseDays(!dontRunOnTheseDays)}
                            />
                            <span>Don't run on these days.</span>
                        </label>
                    </div>
                    
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={handleAddTimePeriod}
                    >
                        Add time period
                    </button>
                </div>
            )}
            
            {/* Display selected day parts */}
            {selectedDayParts.length > 0 && (
                <div className="mt-4">
                    <h3 className="font-medium mb-2">Selected Time Periods:</h3>
                    <div className="space-y-2">
                        {selectedDayParts.map((dayPart, index) => (
                            <div key={index} className="flex justify-between items-center p-2 bg-gray-50 rounded border">
                                <div>
                                    <div className="font-medium">
                                        {formatDaysList(dayPart.days)}
                                    </div>
                                    <div className="text-sm">
                                        {dayPart.dontRunOnTheseDays ? "Don't run: " : "Running: "}
                                        {dayPart.startTime} - {dayPart.endTime}
                                        {dayPart.usePublisherTimeZone ? " (Publisher's time zone)" : " (User's time zone)"}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    className="btn btn-sm btn-circle btn-ghost"
                                    onClick={() => handleRemove(index)}
                                >
                                    ×
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
            
            {/* Add time period button (outside the time selector) */}
            {!showTimeSelector && (
                <button
                    type="button"
                    className="btn btn-link text-blue-600 p-0"
                    onClick={() => setShowTimeSelector(true)}
                >
                    Add time period
                </button>
            )}
        </div>
    );
} 