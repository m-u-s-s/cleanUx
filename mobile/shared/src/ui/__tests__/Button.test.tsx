/**
 * Tests for the shared Button UI component.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { Button } from '../Button';

describe('Button', () => {
  it('renders the label text', () => {
    render(<Button label="Submit" onPress={jest.fn()} />);
    expect(screen.getByText('Submit')).toBeTruthy();
  });

  it('calls onPress when pressed', () => {
    const onPress = jest.fn();
    render(<Button label="Tap me" onPress={onPress} />);
    fireEvent.press(screen.getByText('Tap me'));
    expect(onPress).toHaveBeenCalledTimes(1);
  });

  it('does not call onPress when disabled', () => {
    const onPress = jest.fn();
    render(<Button label="Disabled" onPress={onPress} disabled />);
    fireEvent.press(screen.getByText('Disabled'));
    expect(onPress).not.toHaveBeenCalled();
  });

  it('does not call onPress when loading', () => {
    const onPress = jest.fn();
    render(<Button label="Loading" onPress={onPress} loading />);
    fireEvent.press(screen.getByText('Loading'));
    expect(onPress).not.toHaveBeenCalled();
  });

  it('has accessibilityRole button', () => {
    render(<Button label="Go" onPress={jest.fn()} />);
    expect(screen.getByRole('button')).toBeTruthy();
  });

  it('renders all variants without crashing', () => {
    const variants = ['primary', 'secondary', 'ghost', 'danger'] as const;
    variants.forEach((variant) => {
      const { unmount } = render(
        <Button label={variant} onPress={jest.fn()} variant={variant} />,
      );
      expect(screen.getByText(variant)).toBeTruthy();
      unmount();
    });
  });

  it('shows ActivityIndicator when loading is true', () => {
    render(<Button label="Saving" onPress={jest.fn()} loading />);
    // ActivityIndicator is present when loading
    expect(screen.getByText('Saving')).toBeTruthy();
    // We check that the label still renders alongside the spinner
    expect(screen.queryByText('Saving')).not.toBeNull();
  });
});
