import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
import typography from "@tailwindcss/typography";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./vendor/laravel/jetstream/**/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.{js,ts,vue}",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                swift: {
                    red: "#E03A2F",
                    green: "#2F9E44",
                    blue: "#003366",
                },
                semantic: {
                    bg: 'var(--color-bg)',
                    surface: 'var(--color-surface)',
                    'surface-2': 'var(--color-surface-2)',
                    text: 'var(--color-text)',
                    'text-2': 'var(--color-text-2)',
                    'text-3': 'var(--color-text-3)',
                    primary: 'var(--color-primary)',
                    'primary-hover': 'var(--color-primary-hover)',
                    accent: 'var(--color-accent)',
                    urgent: 'var(--color-urgent)',
                    'urgent-hover': 'var(--color-urgent-hover)',
                    success: 'var(--color-success)',
                    warning: 'var(--color-warning)',
                    border: 'var(--color-border)',
                    'border-2': 'var(--color-border-2)',
                },
            },
            animation: {
                "fade-up": "fadeUp 0.5s ease-out",
                "fade-down": "fadeDown 0.5s ease-out",
                "soft-pulse": "softPulse 1.8s ease-in-out infinite",
            },
            keyframes: {
                fadeUp: {
                    "0%": { opacity: 0, transform: "translateY(20px)" },
                    "100%": { opacity: 1, transform: "translateY(0)" },
                },
                fadeDown: {
                    "0%": { opacity: 0, transform: "translateY(-20px)" },
                    "100%": { opacity: 1, transform: "translateY(0)" },
                },
                softPulse: {
                    "0%, 100%": { opacity: 1 },
                    "50%": { opacity: 0.62 },
                },
            },
            boxShadow: {
                card: 'var(--shadow-card)',
                fab: 'var(--shadow-fab)',
                cta: 'var(--shadow-cta)',
                call: 'var(--shadow-call)',
            },
        },
    },

    plugins: [forms, typography],
};
