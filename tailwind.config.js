import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/home.blade.php',
        './resources/views/itineraries/create.blade.php',
        './resources/views/livewire/plans-form.blade.php',
        './resources/views/itineraries/edit.blade.php',
        './resources/views/livewire/edit-plans-form.blade.php',
        './resources/views/itineraries/index.blade.php',
        './resources/views/livewire/pages/auth/register.blade.php',
        './resources/views/livewire/pages/auth/login.blade.php',
        // './resources/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            screens: {
                'sp2': '420px',
            },
        },
    },

    plugins: [forms],
};
