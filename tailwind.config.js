import forms from '@tailwindcss/forms';
import animate from 'tailwindcss-animate';
import defaultTheme from 'tailwindcss/defaultTheme';

export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',
  ],

  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
        mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
      },
      colors: {
        background: 'hsl(var(--background) / <alpha-value>)',
        canvas: 'hsl(var(--canvas) / <alpha-value>)',
        surface: 'hsl(var(--surface) / <alpha-value>)',
        elevated: 'hsl(var(--elevated) / <alpha-value>)',
        overlay: 'hsl(var(--overlay) / <alpha-value>)',
        foreground: 'hsl(var(--foreground) / <alpha-value>)',
        muted: 'hsl(var(--muted) / <alpha-value>)',
        faint: 'hsl(var(--faint) / <alpha-value>)',
        accent: 'hsl(var(--accent) / <alpha-value>)',
        success: 'hsl(var(--success) / <alpha-value>)',
        warning: 'hsl(var(--warning) / <alpha-value>)',
        danger: 'hsl(var(--danger) / <alpha-value>)',
        border: 'hsl(var(--border) / <alpha-value>)',
        'border-strong': 'hsl(var(--border-strong) / <alpha-value>)',
      },
      borderColor: {
        DEFAULT: 'hsl(var(--border) / 1)',
      },
      boxShadow: {
        popover: '0 0 0 1px hsl(var(--border-strong) / 1), 0 16px 40px -12px rgb(0 0 0 / 0.55)',
        drawer: '0 0 0 1px hsl(var(--border) / 1)',
        dialog: '0 0 0 1px hsl(var(--border-strong) / 1), 0 24px 64px -16px rgb(0 0 0 / 0.6)',
      },
      keyframes: {
        'fade-in': {
          from: { opacity: '0' },
          to: { opacity: '1' },
        },
        'slide-up': {
          from: { opacity: '0', transform: 'translateY(6px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
        'dash-flow': {
          to: { strokeDashoffset: '-20' },
        },
        'orbit-spin': {
          to: { transform: 'rotate(360deg)' },
        },
        'hub-pulse': {
          '0%': { opacity: '0.55', transform: 'scale(1)' },
          '100%': { opacity: '0', transform: 'scale(1.6)' },
        },
      },
      animation: {
        'fade-in': 'fade-in 0.2s ease-out',
        'slide-up': 'slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1)',
        'dash-flow': 'dash-flow 1s linear infinite',
        'orbit-spin': 'orbit-spin 90s linear infinite',
        'hub-pulse': 'hub-pulse 2.4s cubic-bezier(0.16, 1, 0.3, 1) infinite',
      },
    },
  },

  plugins: [forms({ strategy: 'class' }), animate],
};
