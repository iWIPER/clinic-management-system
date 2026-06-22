/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/Http/Middleware/*.php',
    ],
    theme: {
        extend: {},
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
};
