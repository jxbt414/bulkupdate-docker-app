import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import Dashboard from './Dashboard';
import { router } from '@inertiajs/react';
import axios from 'axios';

// Mock Inertia router
jest.mock('@inertiajs/react', () => ({
    router: {
        visit: jest.fn()
    },
    Head: jest.fn(() => null)
}));

// Mock AuthenticatedLayout
jest.mock('@/Layouts/AuthenticatedLayout', () => {
    return jest.fn(({ children, user }) => (
        <div>
            <div data-testid="user-info">
                <span>{user.name}</span>
                <span>{user.email}</span>
            </div>
            {children}
        </div>
    ));
});

// Mock axios
jest.mock('axios');

// Mock route function
global.route = jest.fn((name) => {
    const routes = {
        'line-items.upload': '/line-items/upload',
        'line-items.static-update': '/line-items/static-update'
    };
    return routes[name];
});

describe('Dashboard Component', () => {
    beforeEach(() => {
        // Reset all mocks before each test
        jest.clearAllMocks();
        
        // Mock successful axios response
        axios.get.mockResolvedValue({
            data: {
                status: 'success',
                logs: []
            }
        });
    });

    test('renders dashboard with both update options', () => {
        render(<Dashboard auth={{ user: {} }} />);

        expect(screen.getByRole('link', { name: /Static Update/i })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Dynamic Update/i })).toBeInTheDocument();
    });

    test('navigates to static update page when static update is clicked', async () => {
        render(<Dashboard auth={{ user: {} }} />);

        const staticUpdateLink = screen.getByRole('link', { name: /Static Update/i });
        expect(staticUpdateLink).toHaveAttribute('href', '/line-items/static-update');
    });

    test('navigates to dynamic update page when dynamic update is clicked', async () => {
        render(<Dashboard auth={{ user: {} }} />);

        const dynamicUpdateLink = screen.getByRole('link', { name: /Dynamic Update/i });
        expect(dynamicUpdateLink).toHaveAttribute('href', '/line-items/upload');
    });

    test('displays help text for each update type', () => {
        render(<Dashboard auth={{ user: {} }} />);

        expect(screen.getByText(/Update multiple line items with the same changes/i)).toBeInTheDocument();
        expect(screen.getByText(/Upload a CSV file to update multiple line items with different changes/i)).toBeInTheDocument();
    });

    test('displays user information', () => {
        const user = {
            name: 'Test User',
            email: 'test@example.com'
        };

        render(<Dashboard auth={{ user }} />);

        const userInfo = screen.getByTestId('user-info');
        expect(userInfo).toHaveTextContent(user.name);
        expect(userInfo).toHaveTextContent(user.email);
    });
}); 