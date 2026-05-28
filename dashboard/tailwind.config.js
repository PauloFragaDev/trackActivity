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
                // Escala ink → CSS variables RGB. Cada tema define los
                // valores en :root[data-theme="X"] (light) y .dark
                // (variantes oscuras). Asi TODA clase Tailwind
                // bg-ink-*, text-ink-*, border-ink-*, ring-ink-* sigue
                // respondiendo al tema activo sin tocar el HTML.
                // Sintaxis Tailwind: rgb(var(--ink-N) / <alpha-value>)
                // permite seguir usando bg-ink-100/50 con opacidad.
                ink: {
                    50:  'rgb(var(--ink-50)  / <alpha-value>)',
                    100: 'rgb(var(--ink-100) / <alpha-value>)',
                    200: 'rgb(var(--ink-200) / <alpha-value>)',
                    300: 'rgb(var(--ink-300) / <alpha-value>)',
                    400: 'rgb(var(--ink-400) / <alpha-value>)',
                    500: 'rgb(var(--ink-500) / <alpha-value>)',
                    600: 'rgb(var(--ink-600) / <alpha-value>)',
                    700: 'rgb(var(--ink-700) / <alpha-value>)',
                    800: 'rgb(var(--ink-800) / <alpha-value>)',
                    900: 'rgb(var(--ink-900) / <alpha-value>)',
                    950: 'rgb(var(--ink-950) / <alpha-value>)',
                },
            },
        },
    },
    plugins: [],
};
