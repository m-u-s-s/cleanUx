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
        },
    },

    plugins: [forms, typography],

    extend: {
        animation: {
            "fade-up": "fadeUp 0.5s ease-out",
            "fade-down": "fadeDown 0.5s ease-out",
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
        },
    },
};
