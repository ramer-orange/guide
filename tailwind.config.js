import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/itineraries/create.blade.php',
        './resources/views/livewire/plans-form.blade.php',
        './resources/views/itineraries/edit.blade.php',
        './resources/views/livewire/edit-plans-form.blade.php',
        // './resources/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
