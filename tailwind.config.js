import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
        './app/View/Components/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#b50e0e',
                    hover: '#9a0101',
                    dark: '#800707',
                    light: '#fef2f2',
                    50: '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    500: '#ef4444',
                    600: '#b50e0e',
                    700: '#9a0101',
                    800: '#800707',
                    900: '#5a0505',
                },
            },
        },
    },

    plugins: [forms],
};
