import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Quicksand', ...defaultTheme.fontFamily.sans],
                script: ['"Rock Salt"', 'cursive'],
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
                card: '20px',
                task: '14px',
                field: '12px',
                icon: '10px',
            },
            boxShadow: {
                soft: 'var(--shadow-soft)',
                hover: 'var(--shadow-hover)',
                product: 'var(--shadow-product)',
            },
            colors: {
                // Design system Flexilivre (variables de resources/css/tokens.css)
                brand: {
                    DEFAULT: 'var(--primary-500)',
                    hover: 'var(--primary-hover)',
                    active: 'var(--primary-active)',
                    tint: 'var(--primary-tint)',
                    wash: 'var(--primary-wash)',
                    disabled: 'var(--primary-disabled-bg)',
                },
                info: {
                    DEFAULT: 'var(--secondary-500)',
                    hover: 'var(--secondary-hover)',
                    active: 'var(--secondary-active)',
                    deep: 'var(--secondary-deep)',
                    tint: 'var(--secondary-tint)',
                    night: 'var(--secondary-950)',
                },
                app: {
                    bg: 'var(--app-bg)',
                    surface: 'var(--app-surface-2)',
                    hover: 'var(--app-hover)',
                    border: 'var(--app-border)',
                    'border-strong': 'var(--app-border-strong)',
                    muted: 'var(--app-icon-muted)',
                    strong: 'var(--app-text-strong)',
                },
                ink: {
                    DEFAULT: 'var(--grey-max)',
                    soft: 'var(--grey-text)',
                    medium: 'var(--grey-medium)',
                },
                success: { DEFAULT: 'var(--success)', tint: 'var(--success-tint)', text: 'var(--success-text)' },
                warning: { DEFAULT: 'var(--warning)', tint: 'var(--warning-tint)', text: 'var(--warning-text)' },
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                chart: {
                    1: 'hsl(var(--chart-1))',
                    2: 'hsl(var(--chart-2))',
                    3: 'hsl(var(--chart-3))',
                    4: 'hsl(var(--chart-4))',
                    5: 'hsl(var(--chart-5))',
                },
                sidebar: {
                    DEFAULT: 'hsl(var(--sidebar-background))',
                    foreground: 'hsl(var(--sidebar-foreground))',
                    primary: 'hsl(var(--sidebar-primary))',
                    'primary-foreground': 'hsl(var(--sidebar-primary-foreground))',
                    accent: 'hsl(var(--sidebar-accent))',
                    'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
                    border: 'hsl(var(--sidebar-border))',
                    ring: 'hsl(var(--sidebar-ring))',
                },
            },
        },
    },
    plugins: [require('tailwindcss-animate')],
};
