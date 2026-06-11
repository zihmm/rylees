/** Rylees frontend — shared theme for both apps (console + history).
 *  Tokens consolidated from DESIGN-SPEC-DL.md §2 and DESIGN-SPEC-RH.md §2.
 *  Single brand accent: #ffc00e. */
export default {
  content: ['./src/**/*.{vue,js}', './*.html'],
  theme: {
    extend: {
      colors: {
        accent: '#ffc00e',
        page: '#f5f4f7',
        panel: '#f9fafb',
        card: '#ffffff',
        'card-border': '#dcdcdc',
        'field-border': '#e9e9ea',
        ink: '#141414',
        title: '#2a2a2a',
        subhead: '#6b6b6b',
        'field-label': '#2e2e2e',
        helper: '#747474',
        muted: '#a5a5a5',
        meta: '#adadad',
        'label-inactive': '#bebebe',
        'sidebar-heading': '#7a7a7a',
        danger: '#df5e70',
        'pill-bg': '#f4f0eb',
        'pill-text': '#5f5f5f',
        'beta-bg': '#fcf1c3',
      },
      borderRadius: { card: '10px', field: '4px', pill: '25px' },
      boxShadow: { card: '0 0 14px -5px rgba(0,0,0,0.25)' },
      fontFamily: {
        sans: ['Noto Sans', 'system-ui', 'sans-serif'],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
      },
    },
  },
  plugins: [],
};
