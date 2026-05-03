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
        },
    },

    plugins: [forms, typography],
};
