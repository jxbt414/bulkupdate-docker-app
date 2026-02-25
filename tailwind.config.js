import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#2563eb',
                    '50': '#eff6ff',
                    '100': '#dbeafe',
                    '200': '#bfdbfe',
                    '300': '#93c5fd',
                    '400': '#60a5fa',
                    '500': '#3b82f6',
                    '600': '#2563eb',
                    '700': '#1d4ed8',
                    '800': '#1e40af',
                    '900': '#1e3a8a',
                },
            },
        },
    },

    plugins: [
        forms,
        daisyui
    ],

    daisyui: {
        themes: [
            {
                light: {
                    ...require('daisyui/src/theming/themes')['[data-theme=light]'],
                    primary: '#2563eb',
                    'primary-focus': '#1d4ed8',
                    'primary-content': '#ffffff',
                },
            },
        ],
        styled: true,
        base: true,
        utils: true,
        logs: true,
        rtl: false,
    },
};
