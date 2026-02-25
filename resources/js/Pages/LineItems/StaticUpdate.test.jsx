import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import '@testing-library/jest-dom';
import StaticUpdate from './StaticUpdate';
import axios from 'axios';
import userEvent from '@testing-library/user-event';

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

// Mock route function
global.route = jest.fn((name) => {
    const routes = {
        'line-items.static-sample-csv': '/line-items/static-sample-csv',
        'line-items.upload': '/line-items/upload',
        'line-items.preview': '/line-items/preview'
    };
    return routes[name] || '/';
});

describe('StaticUpdate Component', () => {
    let mockLineItems;
    const mockUser = { name: 'Test User', email: 'test@example.com' };

    beforeEach(() => {
        // Reset all mocks
        jest.clearAllMocks();
        
        // Mock the initial state
        mockLineItems = {
            data: [
                { 
                    id: '123', 
                    name: 'Line Item 123',
                    customTargeting: [],
                    audienceSegments: [],
                    cmsMetadata: []
                },
                { 
                    id: '124', 
                    name: 'Line Item 124',
                    customTargeting: [],
                    audienceSegments: [],
                    cmsMetadata: []
                }
            ]
        };

        // Mock successful line item search
        axios.get.mockImplementation((url) => {
            if (url.includes('/line-items/search')) {
                return Promise.resolve({ data: mockLineItems });
            } else if (url.includes('/line-items/custom-targeting-keys')) {
                return Promise.resolve({ data: mockCustomTargetingKeys });
            } else if (url.includes('/line-items/custom-targeting-values')) {
                return Promise.resolve({ data: { values: mockCustomTargetingValues } });
            }
            return Promise.reject(new Error('Not found'));
        });
    });

    const mockCustomTargetingKeys = [
        { id: '1', name: 'key1', displayName: 'key1' },
        { id: '2', name: 'key2', displayName: 'key2' }
    ];

    const mockCustomTargetingValues = [
        { id: '1', name: 'value1', displayName: 'value1' },
        { id: '2', name: 'value2', displayName: 'value2' }
    ];

    test('renders line item search form', () => {
        render(<StaticUpdate auth={{ user: {} }} />);

        expect(screen.getByPlaceholderText(/Enter line item IDs/i)).toBeInTheDocument();
        expect(screen.getByText(/Continue to Preview/i)).toBeInTheDocument();
    });

    test('searches for line items successfully', async () => {
        render(<StaticUpdate auth={{ user: {} }} />);

        const input = screen.getByPlaceholderText(/Enter line item IDs/i);
        fireEvent.change(input, { target: { value: '123,124' } });

        const searchButton = screen.getByText(/Continue to Preview/i);
        fireEvent.click(searchButton);

        // Wait for the step to change to 'preview'
        await waitFor(() => {
            expect(screen.getByText('Preview Line Items')).toBeInTheDocument();
        });

        // Check if the line item IDs are displayed
        expect(screen.getByText('123, 124')).toBeInTheDocument();
    });

    test('displays error message when search fails', async () => {
        // Mock the API response to return an error
        axios.post.mockRejectedValueOnce({
            response: {
                data: {
                    message: 'An error occurred'
                }
            }
        });

        render(<StaticUpdate auth={{ user: mockUser }} />);

        const input = screen.getByPlaceholderText('Enter line item IDs separated by commas');
        await userEvent.type(input, '123456');

        // Click continue button
        const continueButton = screen.getByRole('button', { name: /continue to preview/i });
        await userEvent.click(continueButton);

        // Wait for the error message to appear
        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });

        const errorElement = screen.getByRole('alert');
        expect(errorElement.textContent).toContain('An error occurred');
    });

    test('loads custom targeting options', async () => {
        // Mock the API response for line item search
        axios.post.mockResolvedValueOnce({
            data: {
                status: 'success',
                data: mockLineItems.data
            }
        });

        // Mock the API response for custom targeting keys
        axios.get.mockResolvedValueOnce({
            data: {
                status: 'success',
                keys: ['Key 1', 'Key 2']
            }
        });

        render(<StaticUpdate auth={{ user: mockUser }} />);

        // Enter line item IDs
        const input = screen.getByPlaceholderText('Enter line item IDs separated by commas');
        await userEvent.type(input, '123456');

        // Click continue button
        const continueButton = screen.getByRole('button', { name: /continue to preview/i });
        await userEvent.click(continueButton);

        // Click Custom Targeting heading
        const customTargetingHeading = screen.getByText('Custom Targeting');
        await userEvent.click(customTargetingHeading);

        // Wait for search input to appear
        await waitFor(() => {
            const searchInput = screen.getByPlaceholderText('Type to search labels...');
            expect(searchInput).toBeInTheDocument();
        }, { timeout: 10000 });
    }, 15000);

    test('handles update submission', async () => {
        // Mock successful response
        axios.post.mockResolvedValueOnce({
            data: {
                status: 'success',
                message: 'Update started'
            }
        });

        render(<StaticUpdate auth={{ user: {} }} />);

        // Enter line item IDs
        const textarea = screen.getByPlaceholderText(/Enter line item IDs/i);
        fireEvent.change(textarea, { target: { value: '123456' } });

        // Click continue to preview
        const previewButton = screen.getByRole('button', { name: /continue to preview/i });
        fireEvent.click(previewButton);

        // Wait for the step to change to 'preview'
        await waitFor(() => {
            expect(screen.getByText('Preview Line Items')).toBeInTheDocument();
        });

        // Make some changes
        const statusSelect = screen.getByLabelText(/Status/i);
        fireEvent.change(statusSelect, { target: { value: 'PAUSED' } });

        // Click continue to update
        const continueButton = screen.getByRole('button', { name: /continue to update/i });
        await userEvent.click(continueButton);

        // Wait for the step to change to 'update'
        await waitFor(() => {
            expect(screen.getByText('Confirm Updates')).toBeInTheDocument();
        });

        // Submit the update
        const submitButton = screen.getByRole('button', { name: 'Update Line Items' });
        await userEvent.click(submitButton);

        await waitFor(() => {
            expect(axios.post).toHaveBeenCalledWith(
                '/line-items/bulk-update',
                expect.objectContaining({
                    line_items: expect.arrayContaining([
                        expect.objectContaining({
                            line_item_id: '123456',
                            line_item_name: '123456'
                        })
                    ])
                })
            );
        });
    });

    test('displays progress bar during update', async () => {
        // Mock the post request to return a batch ID
        axios.post.mockResolvedValueOnce({
            data: {
                status: 'success',
                results: {
                    batch_id: 'test_batch_id'
                }
            }
        });

        render(<StaticUpdate auth={{ user: {} }} />);

        // Search for line items
        const input = screen.getByPlaceholderText(/Enter line item IDs/i);
        fireEvent.change(input, { target: { value: '123,124' } });

        const searchButton = screen.getByText(/Continue to Preview/i);
        fireEvent.click(searchButton);

        // Wait for the step to change to 'preview'
        await waitFor(() => {
            expect(screen.getByText('Preview Line Items')).toBeInTheDocument();
        });

        // Continue to update
        const continueButton = screen.getByText(/Continue to Update/i);
        fireEvent.click(continueButton);

        // Wait for the step to change to 'update'
        await waitFor(() => {
            expect(screen.getByText('Confirm Updates')).toBeInTheDocument();
        });

        // Submit the update
        const updateButton = screen.getByText(/Update Line Items/i);
        fireEvent.click(updateButton);

        // Wait for the progress bar to appear
        await waitFor(() => {
            expect(screen.getByTestId('progress-bar')).toBeInTheDocument();
        });
    });

    test('validates required fields before update', async () => {
        // Mock successful line item search
        axios.post.mockResolvedValueOnce({
            data: {
                status: 'success',
                data: mockLineItems.data
            }
        });

        render(<StaticUpdate auth={{ user: mockUser }} />);

        // Enter line item IDs
        const input = screen.getByPlaceholderText('Enter line item IDs separated by commas');
        await userEvent.type(input, '123456');

        // Click continue button
        const continueButton = screen.getByRole('button', { name: /continue to preview/i });
        await userEvent.click(continueButton);

        // Wait for preview step
        await waitFor(() => {
            expect(screen.getByText('Preview Line Items')).toBeInTheDocument();
        });

        // Click continue without selecting any fields
        const updateButton = screen.getByRole('button', { name: /continue to update/i });
        await userEvent.click(updateButton);

        // Wait for the error message to appear
        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });

        const errorElement = screen.getByRole('alert');
        expect(errorElement.textContent).toContain('Please specify at least one field to update');
    });

    test('allows clearing of selected line items', async () => {
        render(<StaticUpdate auth={{ user: { name: 'Test User', email: 'test@example.com' } }} />);

        // Set initial line items
        const input = screen.getByPlaceholderText(/Enter line item IDs/i);
        fireEvent.change(input, { target: { value: '123,124' } });

        // Verify the input has the initial value
        expect(input.value).toBe('123,124');

        // Click clear button
        const clearButton = screen.getByTestId('clear-button');
        fireEvent.click(clearButton);

        // Verify the input is cleared
        await waitFor(() => {
            expect(input.value).toBe('');
        });
    });
});