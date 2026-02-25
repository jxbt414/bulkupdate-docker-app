import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import axios from 'axios';
import Logs from './Logs';

// Mock axios
jest.mock('axios');

// Mock Inertia Head component
jest.mock('@inertiajs/react', () => ({
    Head: jest.fn(() => null)
}));

// Mock AuthenticatedLayout
jest.mock('@/Layouts/AuthenticatedLayout', () => {
    return jest.fn(({ children }) => <div>{children}</div>);
});

describe('Logs Component', () => {
    const mockSuccessfulUpdate = {
        batch_id: 'batch123',
        action: 'update',
        type: 'success',
        message: 'Successfully updated 2 line items',
        data: {
            successful_items: [
                {
                    line_item_id: '123',
                    line_item_name: 'Test Line Item 1',
                    updated_fields: 'status: READY, budget: USD 1000'
                },
                {
                    line_item_id: '124',
                    line_item_name: 'Test Line Item 2',
                    updated_fields: 'status: READY'
                }
            ],
            failed_items: []
        }
    };

    const mockFailedUpdate = {
        batch_id: 'batch456',
        action: 'update',
        type: 'error',
        message: 'Update completed with errors',
        data: {
            successful_items: [
                {
                    line_item_id: '123',
                    line_item_name: 'Test Line Item 1',
                    updated_fields: 'status: READY'
                }
            ],
            failed_items: [
                {
                    line_item_id: '124',
                    proposal_line_item_id: 'pli456',
                    proposal_id: 'p789',
                    error_message: 'Invalid status transition'
                }
            ]
        }
    };

    const mockRollback = {
        batch_id: 'batch789',
        action: 'rollback',
        type: 'success',
        message: 'Successfully rolled back 1 line item',
        data: {
            successful_items: [
                {
                    line_item_id: '123',
                    line_item_name: 'Test Line Item 1',
                    data: {
                        previous_values: {
                            status: 'READY',
                            budget: { currencyCode: 'USD', microAmount: 1000000000 }
                        },
                        current_values: {
                            status: 'DRAFT',
                            budget: { currencyCode: 'USD', microAmount: 500000000 }
                        }
                    }
                }
            ],
            failed_items: []
        }
    };

    beforeEach(() => {
        // Clear all mocks before each test
        jest.clearAllMocks();
    });

    test('loads and displays logs successfully', async () => {
        // Mock the API response
        axios.get.mockResolvedValueOnce({
            data: {
                status: 'success',
                logs: [mockSuccessfulUpdate]
            }
        });

        render(<Logs auth={{ user: {} }} />);

        // Wait for logs to load
        await waitFor(() => {
            expect(screen.getByTestId('log-entry-batch123')).toBeInTheDocument();
        });

        // Click to show details
        fireEvent.click(screen.getByTestId('log-entry-batch123'));

        // Wait for details to be displayed
        await waitFor(() => {
            expect(screen.getByText(/Line Item ID: 123/)).toBeInTheDocument();
            expect(screen.getByText('Test Line Item 1')).toBeInTheDocument();
        });
    });

    test('displays failed items and allows error CSV export', async () => {
        // Mock the API response
        axios.get.mockResolvedValueOnce({
            data: {
                status: 'success',
                logs: [mockFailedUpdate]
            }
        });

        render(<Logs auth={{ user: {} }} />);

        // Wait for logs to load
        await waitFor(() => {
            expect(screen.getByTestId('log-entry-batch456')).toBeInTheDocument();
        });

        // Click to show details
        fireEvent.click(screen.getByTestId('log-entry-batch456'));

        // Wait for details to be displayed
        await waitFor(() => {
            expect(screen.getByText(/Line Item ID: 124/)).toBeInTheDocument();
            expect(screen.getByText('Invalid status transition')).toBeInTheDocument();
            expect(screen.getByText('Export Errors CSV')).toBeInTheDocument();
        });
    });

    test('displays rollback information correctly', async () => {
        // Mock the API response
        axios.get.mockResolvedValueOnce({
            data: {
                status: 'success',
                logs: [mockRollback]
            }
        });

        render(<Logs auth={{ user: {} }} />);

        // Wait for logs to load
        await waitFor(() => {
            expect(screen.getByTestId('log-entry-batch789')).toBeInTheDocument();
        });

        // Click to show details
        fireEvent.click(screen.getByTestId('log-entry-batch789'));

        // Wait for details to be displayed
        await waitFor(() => {
            expect(screen.getByText('Previous Values:')).toBeInTheDocument();
            expect(screen.getByText('Rolled Back To:')).toBeInTheDocument();
        });
    });

    test('handles API errors gracefully', async () => {
        // Mock a failed API response
        axios.get.mockRejectedValueOnce(new Error('Failed to load logs'));

        render(<Logs auth={{ user: {} }} />);

        // Wait for error message
        await waitFor(() => {
            expect(screen.getByText('No logs found')).toBeInTheDocument();
        });
    });

    test('filters work correctly', async () => {
        // Mock the API response with multiple log types
        axios.get.mockResolvedValueOnce({
            data: {
                status: 'success',
                logs: [mockSuccessfulUpdate, mockFailedUpdate, mockRollback]
            }
        });

        render(<Logs auth={{ user: {} }} />);

        // Wait for logs to load
        await waitFor(() => {
            expect(screen.getByTestId('log-entry-batch123')).toBeInTheDocument();
        });

        // Test error filter
        fireEvent.change(screen.getByTestId('log-filter'), { target: { value: 'error' } });

        // Wait for filtered results
        await waitFor(() => {
            expect(screen.queryByTestId('log-entry-batch123')).not.toBeInTheDocument();
            expect(screen.getByTestId('log-entry-batch456')).toBeInTheDocument();
        });

        // Test rollback filter
        fireEvent.change(screen.getByTestId('log-filter'), { target: { value: 'rollback' } });

        // Wait for filtered results
        await waitFor(() => {
            expect(screen.getByTestId('log-entry-batch789')).toBeInTheDocument();
        });
    });

    test('CSV export functionality works', async () => {
        // Mock the API response
        axios.get.mockResolvedValueOnce({
            data: {
                status: 'success',
                logs: [mockFailedUpdate]
            }
        });

        // Mock URL.createObjectURL
        const mockCreateObjectURL = jest.fn();
        global.URL.createObjectURL = mockCreateObjectURL;
        global.URL.revokeObjectURL = jest.fn();

        render(<Logs auth={{ user: {} }} />);

        // Wait for logs to load
        await waitFor(() => {
            expect(screen.getByTestId('log-entry-batch456')).toBeInTheDocument();
        });

        // Click to show details
        fireEvent.click(screen.getByTestId('log-entry-batch456'));

        // Wait for details to be displayed
        await waitFor(() => {
            expect(screen.getByText('Export to CSV')).toBeInTheDocument();
            expect(screen.getByText('Export Errors CSV')).toBeInTheDocument();
        });

        // Test regular export
        fireEvent.click(screen.getByText('Export to CSV'));
        expect(mockCreateObjectURL).toHaveBeenCalled();

        // Test error export
        fireEvent.click(screen.getByText('Export Errors CSV'));
        expect(mockCreateObjectURL).toHaveBeenCalledTimes(2);
    });
}); 