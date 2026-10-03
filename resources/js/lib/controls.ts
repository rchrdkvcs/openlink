import { cva, type VariantProps } from 'class-variance-authority';

export const controlVariants = cva(
  'w-full min-w-0 rounded-lg border border-transparent bg-elevated/70 text-foreground shadow-none outline-none transition-[color,background-color,border-color,box-shadow] duration-150 placeholder:text-faint hover:bg-elevated focus-visible:border-accent/60 focus-visible:bg-elevated focus-visible:ring-2 focus-visible:ring-accent/15 disabled:cursor-not-allowed disabled:opacity-50 aria-[invalid=true]:border-danger/60',
  {
    variants: {
      size: {
        sm: 'h-7 px-2.5 text-[13px]',
        md: 'h-8 px-2.5 text-[13px]',
        lg: 'h-10 px-3 text-sm',
      },
    },
    defaultVariants: {
      size: 'md',
    },
  },
);

export type ControlSize = NonNullable<VariantProps<typeof controlVariants>['size']>;

export type SelectOption<V = string> = { value: V; label: string };
