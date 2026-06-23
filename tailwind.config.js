import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Noto Sans Malayalam', 'Instrument Sans', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                base: ['1.125rem', { lineHeight: '1.75rem' }],
            },
        },
    },
    plugins: [],
};
