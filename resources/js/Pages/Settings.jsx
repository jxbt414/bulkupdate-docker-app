import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useState } from 'react';
import axios from 'axios';

// Add custom styles at the top of the file
const customStyles = `
    .no-hover {
        opacity: 1 !important;
        visibility: visible !important;
    }
    .no-hover:hover {
        opacity: 1 !important;
        visibility: visible !important;
    }
    .no-hover * {
        opacity: 1 !important;
        visibility: visible !important;
    }
    .toggle {
        position: relative !important;
        border: 2px solid #d1d5db !important;
        background-color: #f3f4f6 !important;
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        width: 3.5rem !important;
        height: 2rem !important;
    }
    .toggle:checked {
        background-color: #570df8 !important;
        border-color: #4805c8 !important;
    }
    .toggle:after {
        content: "" !important;
        position: absolute !important;
        background-color: #fff !important;
        border: 1px solid #d1d5db !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
        width: 1.5rem !important;
        height: 1.5rem !important;
        top: 2px !important;
        left: 2px !important;
        border-radius: 50% !important;
        transition: transform 0.2s ease-in-out !important;
    }
    .toggle:checked:after {
        transform: translateX(1.5rem) !important;
        border-color: #4805c8 !important;
    }
    .toggle:focus {
        outline: 2px solid #570df8 !important;
        outline-offset: 2px !important;
    }
`;

export default function Settings({ auth }) {
    const [settings, setSettings] = useState({
        autoRollback: true,
        notifyOnError: true,
        retryAttempts: '3'
    });

    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setSaved(false);

        try {
            await axios.post('/settings', settings);
            setSaved(true);
        } catch (err) {
            console.error('Failed to save settings:', err);
        } finally {
            setSaving(false);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>}
        >
            <Head title="Settings">
                <style>{customStyles}</style>
            </Head>

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* Retry Attempts */}
                                <div className="form-control">
                                    <label className="label">
                                        <span className="label-text">Retry Attempts</span>
                                    </label>
                                    <input 
                                        type="number"
                                        className="input input-bordered w-full"
                                        value={settings.retryAttempts}
                                        onChange={(e) => setSettings({
                                            ...settings,
                                            retryAttempts: e.target.value
                                        })}
                                        min="0"
                                        max="5"
                                    />
                                    <label className="label">
                                        <span className="label-text-alt">
                                            Number of times to retry failed updates (0-5)
                                        </span>
                                    </label>
                                </div>

                                {/* Toggles */}
                                <div className="space-y-4">
                                    {/* Auto Rollback Toggle */}
                                    <div className="flex items-center justify-between p-4 bg-base-100 rounded-lg">
                                        <span className="text-sm font-medium">Auto Rollback on Error</span>
                                        <input 
                                            type="checkbox"
                                            className="toggle toggle-primary"
                                            checked={settings.autoRollback}
                                            onChange={(e) => setSettings({
                                                ...settings,
                                                autoRollback: e.target.checked
                                            })}
                                        />
                                    </div>

                                    {/* Notify on Error Toggle */}
                                    <div className="flex items-center justify-between p-4 bg-base-100 rounded-lg">
                                        <span className="text-sm font-medium">Notify on Error</span>
                                        <input 
                                            type="checkbox"
                                            className="toggle toggle-primary"
                                            checked={settings.notifyOnError}
                                            onChange={(e) => setSettings({
                                                ...settings,
                                                notifyOnError: e.target.checked
                                            })}
                                        />
                                    </div>
                                </div>

                                {/* Submit Button */}
                                <div className="flex items-center justify-between">
                                    <button
                                        type="submit"
                                        className={`btn btn-primary ${saving ? 'loading' : ''}`}
                                        disabled={saving}
                                    >
                                        {saving ? 'Saving...' : 'Save Settings'}
                                    </button>

                                    {saved && (
                                        <div className="text-success flex items-center">
                                            <i className="fas fa-check mr-2"></i>
                                            Settings saved successfully
                                        </div>
                                    )}
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 