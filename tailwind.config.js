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
            // Typo : Inter pour data + Cal Sans-like pour headings.
            // Cohérent avec Stripe/Linear (sérieux SaaS B2B + B2C).
            fontFamily: {
                sans: [
                    "Figtree",
                    "Inter",
                    "ui-sans-serif",
                    "system-ui",
                    "-apple-system",
                    "Segoe UI",
                    ...defaultTheme.fontFamily.sans,
                ],
                display: [
                    "Space Grotesk",
                    "Inter Display",
                    "Inter",
                    ...defaultTheme.fontFamily.sans,
                ],
                mono: ["JetBrains Mono", ...defaultTheme.fontFamily.mono],
            },

            // Palette : indigo principal + neutral gray pour SaaS calme.
            // Legacy `swift` conservée pour compat.
            colors: {
                // Brand principale
                brand: {
                    50: "#eef2ff",
                    100: "#e0e7ff",
                    200: "#c7d2fe",
                    300: "#a5b4fc",
                    400: "#818cf8",
                    500: "#6366f1",
                    600: "#4f46e5",
                    700: "#4338ca",
                    800: "#3730a3",
                    900: "#312e81",
                    950: "#1e1b4b",
                },
                // Surface neutre (cartes, fonds)
                surface: {
                    50: "#fafafa",
                    100: "#f5f5f5",
                    200: "#e5e5e5",
                    300: "#d4d4d4",
                    400: "#a3a3a3",
                    500: "#737373",
                    600: "#525252",
                    700: "#404040",
                    800: "#262626",
                    900: "#171717",
                    950: "#0a0a0a",
                },
                // Sémantique
                success: {
                    50: "#ecfdf5",
                    500: "#10b981",
                    600: "#059669",
                    700: "#047857",
                },
                warning: {
                    50: "#fffbeb",
                    500: "#f59e0b",
                    600: "#d97706",
                    700: "#b45309",
                },
                danger: {
                    50: "#fef2f2",
                    500: "#ef4444",
                    600: "#dc2626",
                    700: "#b91c1c",
                },
                // Legacy compat
                swift: {
                    red: "#E03A2F",
                    green: "#2F9E44",
                    blue: "#003366",
                },
                // Premium accent palette (mobile alignment)
                accent: {
                    amber: '#ffb648',
                    'amber-deep': '#ff8a3d',
                    cyan: '#4fe3d6',
                    violet: '#8b7bff',
                },
            },

            // Box shadows raffinées (Stripe-like)
            boxShadow: {
                "soft-xs": "0 1px 2px rgba(0,0,0,0.04)",
                "soft-sm": "0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.04)",
                soft: "0 2px 4px rgba(15,23,42,0.04), 0 4px 12px rgba(15,23,42,0.06)",
                "soft-md": "0 4px 8px rgba(15,23,42,0.04), 0 8px 24px rgba(15,23,42,0.08)",
                "soft-lg": "0 12px 24px rgba(15,23,42,0.08), 0 24px 48px rgba(15,23,42,0.12)",
                glow: "0 0 0 4px rgba(99,102,241,0.15)",
            },

            // Border radius cohérents
            borderRadius: {
                xl: "0.875rem",
                "2xl": "1.125rem",
                "3xl": "1.5rem",
            },

            // Spacing scale 4-based + tokens pour layouts
            spacing: {
                "18": "4.5rem",
                "22": "5.5rem",
                "30": "7.5rem",
            },

            animation: {
                "fade-up": "fadeUp 0.5s ease-out",
                "fade-down": "fadeDown 0.5s ease-out",
                "fade-in": "fadeIn 0.3s ease-out",
                "soft-pulse": "softPulse 1.8s ease-in-out infinite",
                "shimmer": "shimmer 2s linear infinite",
            },
            keyframes: {
                fadeUp: {
                    "0%": { opacity: 0, transform: "translateY(12px)" },
                    "100%": { opacity: 1, transform: "translateY(0)" },
                },
                fadeDown: {
                    "0%": { opacity: 0, transform: "translateY(-12px)" },
                    "100%": { opacity: 1, transform: "translateY(0)" },
                },
                fadeIn: {
                    "0%": { opacity: 0 },
                    "100%": { opacity: 1 },
                },
                softPulse: {
                    "0%, 100%": { opacity: 1 },
                    "50%": { opacity: 0.62 },
                },
                shimmer: {
                    "0%": { backgroundPosition: "-1000px 0" },
                    "100%": { backgroundPosition: "1000px 0" },
                },
            },

            // Typography presets
            fontSize: {
                "2xs": ["0.6875rem", { lineHeight: "1rem" }],
            },
        },
    },

    plugins: [forms, typography],
};
