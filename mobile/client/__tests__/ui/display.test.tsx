import React from 'react';
import { Text } from 'react-native';
import { render } from '@testing-library/react-native';
import { StatCard } from '@/ui/StatCard';
import { KPICard } from '@/ui/KPICard';
import { Skeleton } from '@/ui/Skeleton';
import { Screen } from '@/ui/Screen';

// react-native-reanimated is globally mocked via jest.config.ts moduleNameMapper

describe('UI data display', () => {
  it('StatCard renders title and value', () => {
    const { getByText } = render(<StatCard title="Revenue" value="€1,234" trend="up" tone="success" />);
    expect(getByText('Revenue')).toBeTruthy();
    expect(getByText('€1,234')).toBeTruthy();
    expect(getByText('↑')).toBeTruthy();
  });

  it('StatCard renders down trend arrow', () => {
    const { getByText } = render(<StatCard title="Loss" value="€100" trend="down" tone="danger" />);
    expect(getByText('↓')).toBeTruthy();
  });

  it('StatCard renders without trend', () => {
    const { getByText } = render(<StatCard title="Total" value={0} />);
    expect(getByText('Total')).toBeTruthy();
    expect(getByText('0')).toBeTruthy();
  });

  it('KPICard renders with hint', () => {
    const { getByText } = render(<KPICard title="Bookings" value={42} hint="This month" />);
    expect(getByText('Bookings')).toBeTruthy();
    expect(getByText('42')).toBeTruthy();
    expect(getByText('This month')).toBeTruthy();
  });

  it('KPICard renders without hint', () => {
    const { getByText } = render(<KPICard title="Revenue" value="€500" tone="success" />);
    expect(getByText('Revenue')).toBeTruthy();
    expect(getByText('€500')).toBeTruthy();
  });

  it('Skeleton renders without crash', () => {
    const { toJSON } = render(<Skeleton width={200} height={20} />);
    expect(toJSON()).toBeTruthy();
  });

  it('Skeleton renders with custom borderRadius', () => {
    const { toJSON } = render(<Skeleton width="100%" height={40} borderRadius={8} />);
    expect(toJSON()).toBeTruthy();
  });

  it('Screen wraps children with safe area', () => {
    const { getByText } = render(<Screen><Text>Hello</Text></Screen>);
    expect(getByText('Hello')).toBeTruthy();
  });

  it('Screen renders scroll variant', () => {
    const { getByText } = render(<Screen scroll><Text>ScrollContent</Text></Screen>);
    expect(getByText('ScrollContent')).toBeTruthy();
  });
});
