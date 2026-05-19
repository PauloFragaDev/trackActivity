import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/View/Components/**/*.php',
        './app/Http/Controllers/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                ink: {
                    50:  '#f7f7f8',
                    100: '#ebecef',
                    200: '#d2d5db',
                    300: '#a9aeb9',
                    400: '#777e8c',
                    500: '#525a6a',
                    600: '#3a4150',
                    700: '#2a313e',
                    800: '#1c222d',
                    900: '#13171f',
                    950: '#0a0d13',
                },
            },
        },
    },
    plugins: [],
};
