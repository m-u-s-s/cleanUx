/**
 * Tests for the shared KPICard UI component.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';
import { KPICard } from '../KPICard';

describe('KPICard', () => {
  it('renders title and value', () => {
    render(<KPICard title="En cours" value={3} />);
    expect(screen.getByText('En cours')).toBeTruthy();
    expect(screen.getByText('3')).toBeTruthy();
  });

  it('renders string values correctly', () => {
    render(<KPICard title="Revenue" value="€ 1 200" />);
    expect(screen.getByText('€ 1 200')).toBeTruthy();
  });

  it('renders hint text when provided', () => {
    render(<KPICard title="Rating" value="4.9" hint="+2 this week" />);
    expect(screen.getByText('+2 this week')).toBeTruthy();
  });

  it('does not render hint text when omitted', () => {
    render(<KPICard title="Tasks" value={5} />);
    expect(screen.queryByText(/\+/)).toBeNull();
  });

  it('shows loading skeleton instead of content when loading is true', () => {
    render(<KPICard title="En cours" value={3} loading />);
    // When loading, no value text is rendered
    expect(screen.queryByText('3')).toBeNull();
    expect(screen.queryByText('En cours')).toBeNull();
  });

  it('renders all tone variants without crashing', () => {
    const tones = ['neutral', 'success', 'warning', 'danger'] as const;
    tones.forEach((tone) => {
      const { unmount } = render(<KPICard title="KPI" value={1} tone={tone} />);
      expect(screen.getByText('KPI')).toBeTruthy();
      unmount();
    });
  });

  it('has accessibility role text with label combining title and value', () => {
    render(<KPICard title="Terminées" value={7} hint="cette semaine" />);
    const accessible = screen.getByLabelText('Terminées: 7, cette semaine');
    expect(accessible).toBeTruthy();
  });
});
