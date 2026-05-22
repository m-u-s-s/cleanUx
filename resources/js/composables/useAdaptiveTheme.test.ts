import { describe, it, expect, beforeEach } from 'vitest';
import { useAdaptiveTheme } from './useAdaptiveTheme';

describe('useAdaptiveTheme', () => {
  beforeEach(() => {
    document.documentElement.removeAttribute('data-theme');
  });

  it('sets data-theme attribute to light by default', () => {
    const { theme, setTheme } = useAdaptiveTheme();
    setTheme('light');
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    expect(theme.value).toBe('light');
  });

  it('switches to dark', () => {
    const { setTheme } = useAdaptiveTheme();
    setTheme('dark');
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });

  it('initializes from initial prop', () => {
    const { theme } = useAdaptiveTheme('dark');
    expect(theme.value).toBe('dark');
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });
});
