import type { Config } from 'tailwindcss'

// Design system RT CASA LIVE — palette navy/oro/avorio (§3 specifiche)
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        navy: {
          deep: '#0E1B2E',
          DEFAULT: '#16273F',
          panel: '#1C3050',
        },
        gold: {
          DEFAULT: '#C29B52',
          light: '#DCC28C',
        },
        ivory: {
          DEFAULT: '#F6F2EA',
          soft: '#FBF9F4',
        },
        muted: '#5C6B80',
        success: '#2E7D5B',
        error: '#B3402E',
        warning: '#C98A2B',
        info: '#3E6B9E',
      },
      fontFamily: {
        display: ['"Playfair Display"', 'Georgia', 'serif'],
        sans: ['Jost', 'system-ui', 'sans-serif'],
        script: ['Allura', 'cursive'],
      },
      borderRadius: {
        rt: '14px',
      },
      boxShadow: {
        rt: '0 24px 60px rgba(14,27,46,.18)',
      },
      keyframes: {
        'fade-in': {
          from: { opacity: '0', transform: 'translateY(6px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
        typing: {
          '0%, 60%, 100%': { transform: 'translateY(0)' },
          '30%': { transform: 'translateY(-4px)' },
        },
      },
      animation: {
        'fade-in': 'fade-in .35s ease-out both',
        typing: 'typing 1.2s ease-in-out infinite',
      },
    },
  },
  plugins: [],
} satisfies Config
