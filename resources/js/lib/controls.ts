import { cva, type VariantProps } from 'class-variance-authority';

/**
 * The single source of truth for text-like form controls (Input, Textarea,
 * Select trigger, DateTimeField trigger, TagInput shell). Change the look of
 * every field here, never per page.
 *
 * Focus styles use focus-visible: text fields match it on any focus, while a
 * Select trigger refocused after a mouse pick does not keep a ring.
 */
export const controlVariants = cva(
  'w-full min-w-0 rounded-md border border-border bg-surface text-foreground shadow-none outline-none transition-colors duration-150 placeholder:text-faint hover:border-border-strong focus-visible:border-accent/60 focus-visible:ring-2 focus-visible:ring-accent/25 disabled:cursor-not-allowed disabled:opacity-50 aria-[invalid=true]:border-danger/60',
  {
    variants: {
      size: {
        sm: 'h-8 px-2.5 text-[13px]',
        md: 'h-9 px-3 text-sm',
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
