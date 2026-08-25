export const colors = {
  brand: {
    50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
    400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
    800: '#3730a3', 900: '#312e81', 950: '#1e1b4b',
  },
  surface: {
    50: '#fafafa', 100: '#f5f5f5', 200: '#e5e5e5', 300: '#d4d4d4',
    400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040',
    800: '#262626', 900: '#171717', 950: '#0a0a0a',
  },
  success: { 50: '#ecfdf5', 500: '#10b981', 600: '#059669', 700: '#047857' },
  warning: { 50: '#fffbeb', 500: '#f59e0b', 600: '#d97706', 700: '#b45309' },
  danger:  { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
  accent: { amber: '#ffb648', amberDeep: '#ff8a3d', cyan: '#4fe3d6', violet: '#8b7bff' },
  mode: {
    tool: { ink: '#1a2436', muted: '#5b6b85', card: 'rgba(255,255,255,0.9)', cardStrong: 'rgba(255,255,255,0.96)' },
    showcase: { night: '#070b14', nightSoft: '#0c1322', panel: '#111a2e', text: '#e8eefc', muted: '#93a4c6' },
  },
} as const;
