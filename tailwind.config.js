/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './html/**/*.{html,js}',
        './js/**/*.{js,html}'
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif']
            }
        }
    },
    plugins: [require('daisyui')],
    daisyui: {
        themes: ['emerald', 'light', 'dark'],
        base: true,
        styled: true,
        utils: true,
        logs: false
    }
};
