import { cva, type VariantProps } from 'class-variance-authority';

export { default as Button } from './Button.vue';

// Boutons du design system Flexilivre : pilules, texte gras ; le rose plein utilise primary-active pour le contraste.
export const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-bold ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-brand-active text-white hover:bg-brand-hover',
                destructive: 'border-2 border-brand-active bg-transparent text-brand-active hover:bg-brand-tint',
                outline: 'border-2 border-app-border-strong bg-background text-app-strong hover:bg-app-hover',
                secondary: 'border-2 border-brand bg-background text-brand-active hover:bg-brand-tint',
                ghost: 'text-app-strong hover:bg-app-hover',
                link: 'text-info-active underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-5',
                sm: 'h-8 px-3.5 text-xs',
                lg: 'h-12 px-10 text-lg',
                icon: 'size-10 rounded-icon',
            },
        },
        compoundVariants: [{ variant: 'default', size: 'lg', class: 'bg-brand hover:bg-brand-hover' }],
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export type ButtonVariants = VariantProps<typeof buttonVariants>;
