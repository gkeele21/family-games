import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                logo: ['Space Grotesk', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // ---- PropOff module tokens (CSS variables scoped to
                // .propoff-app — inert elsewhere) ----
                primary: {
                    DEFAULT: 'rgb(var(--color-primary) / <alpha-value>)',
                    hover: 'rgb(var(--color-primary-hover) / <alpha-value>)',
                },
                success: 'rgb(var(--color-success) / <alpha-value>)',
                warning: 'rgb(var(--color-warning) / <alpha-value>)',
                danger: 'rgb(var(--color-danger) / <alpha-value>)',
                info: 'rgb(var(--color-info) / <alpha-value>)',
                bg: 'rgb(var(--color-bg) / <alpha-value>)',
                surface: {
                    DEFAULT: 'rgb(var(--color-surface) / <alpha-value>)',
                    elevated: 'rgb(var(--color-surface-elevated) / <alpha-value>)',
                    overlay: 'rgb(var(--color-surface-overlay) / <alpha-value>)',
                    inset: 'rgb(var(--color-surface-inset) / <alpha-value>)',
                    header: 'rgb(var(--color-surface-header) / <alpha-value>)',
                },
                body: 'rgb(var(--color-text) / <alpha-value>)',
                muted: 'rgb(var(--color-text-muted) / <alpha-value>)',
                subtle: 'rgb(var(--color-text-subtle) / <alpha-value>)',
                border: {
                    DEFAULT: 'rgb(var(--color-border) / <alpha-value>)',
                    strong: 'rgb(var(--color-border-strong) / <alpha-value>)',
                },
                'gray-light': '#a3a3a3',
                'gray-dark': '#525252',
                secondary: '#525252',
                'propoff-red': '#af1919',
                'propoff-orange': '#f47612',
                'propoff-green': '#57d025',
                'propoff-dark-green': '#186916',
                'propoff-blue': '#1a3490',
            },
        },
    },

    plugins: [forms],
};
