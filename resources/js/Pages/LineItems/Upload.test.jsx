import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import Upload from './Upload';
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
        'line-items.dynamic-sample-csv': '/line-items/dynamic-sample-csv',
        'line-items.upload': '/line-items/upload',
        'line-items.preview': '/line-items/preview'
    };
    return routes[name] || '/';
});

describe('DynamicUpdate Component', () => {
    const mockFile = new File(['line_item_id,name,status\n123,Test Line Item,READY'], 'test.csv', {
        type: 'text/csv'
    });

    beforeEach(() => {
        // Clear all mocks before each test
        jest.clearAllMocks();
    });

    test('renders file upload section', () => {
        render(<Upload auth={{ user: {} }} />);

        expect(screen.getByText('Upload CSV for Dynamic Updates')).toBeInTheDocument();
        expect(screen.getByText(/Drag and drop your CSV file here/i)).toBeInTheDocument();
    });

    test('handles file upload successfully', async () => {
        // Mock successful response
        axios.post.mockResolvedValueOnce({
            data: {
                status: 'success',
                data: [
                    { line_item_id: '123', name: 'Test Line Item', status: 'READY' }
                ],
                headers: ['line_item_id', 'name', 'status']
            }
        });

        render(<Upload auth={{ user: { name: 'Test User', email: 'test@example.com' } }} />);

        // Get the file input
        const fileInput = screen.getByTestId('file-input');

        // Simulate file upload
        fireEvent.change(fileInput, { target: { files: [mockFile] } });

        // Wait for the upload to complete
        await waitFor(() => {
            expect(axios.post).toHaveBeenCalledWith(
                '/line-items/upload',
                expect.any(FormData),
                expect.objectContaining({
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                })
            );
        });
    });

    test('displays error message on upload failure', async () => {
        const errorMessage = 'Invalid file format';
        axios.post.mockRejectedValueOnce({
            response: {
                data: {
                    message: errorMessage
                }
            }
        });

        render(<Upload auth={{ user: {} }} />);

        // Simulate file drop
        const dropzone = screen.getByText(/Drag and drop your CSV file here/i).closest('div');
        fireEvent.drop(dropzone, {
            dataTransfer: {
                files: [mockFile]
            }
        });

        await waitFor(() => {
            expect(screen.getByText(errorMessage)).toBeInTheDocument();
        });
    });

    test('validates file type', async () => {
        const invalidFile = new File(['invalid'], 'test.txt', { type: 'text/plain' });

        render(<Upload auth={{ user: { name: 'Test User', email: 'test@example.com' } }} />);

        const fileInput = screen.getByLabelText(/choose a file/i);
        await userEvent.upload(fileInput, invalidFile);

        await waitFor(() => {
            expect(screen.getByText(/please select a csv file/i)).toBeInTheDocument();
        }, { timeout: 10000 });
    }, 15000);

    test('displays loading state during file upload', async () => {
        axios.post.mockImplementationOnce(() => new Promise(resolve => setTimeout(resolve, 100)));

        render(<Upload auth={{ user: {} }} />);

        // Simulate file drop
        const dropzone = screen.getByText(/Drag and drop your CSV file here/i).closest('div');
        fireEvent.drop(dropzone, {
            dataTransfer: {
                files: [mockFile]
            }
        });

        expect(screen.getByTestId('loading-spinner')).toBeInTheDocument();

        await waitFor(() => {
            expect(axios.post).toHaveBeenCalled();
        });
    });

    test('allows file selection via click', async () => {
        // Mock successful response
        axios.post.mockResolvedValueOnce({
            data: {
                status: 'success',
                data: [
                    { line_item_id: '123', name: 'Test Line Item', status: 'READY' }
                ],
                headers: ['line_item_id', 'name', 'status']
            }
        });

        render(<Upload auth={{ user: { name: 'Test User', email: 'test@example.com' } }} />);

        // Get the file input
        const fileInput = screen.getByTestId('file-input');

        // Simulate file selection
        fireEvent.change(fileInput, { target: { files: [mockFile] } });

        // Wait for the upload to complete
        await waitFor(() => {
            expect(axios.post).toHaveBeenCalledWith(
                '/line-items/upload',
                expect.any(FormData),
                expect.objectContaining({
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                })
            );
        });
    });

    test('displays preview data after successful upload', async () => {
        const previewData = {
            headers: ['line_item_id', 'name', 'status'],
            preview: [
                {
                    line_item_id: '123',
                    name: 'Test Line Item',
                    status: 'READY'
                }
            ]
        };

        axios.post.mockResolvedValueOnce({
            data: {
                status: 'success',
                message: 'File uploaded successfully',
                ...previewData
            }
        });

        render(<Upload auth={{ user: {} }} />);

        // Simulate file drop
        const dropzone = screen.getByText(/Drag and drop your CSV file here/i).closest('div');
        fireEvent.drop(dropzone, {
            dataTransfer: {
                files: [mockFile]
            }
        });

        await waitFor(() => {
            // Check that we've moved to the mapping step
            expect(screen.getByText('Map Fields')).toBeInTheDocument();
            expect(screen.getByText('Field Descriptions')).toBeInTheDocument();
        });
    });
}); 