/**
 * Tests for the shared EmptyState UI component.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { EmptyState } from '../EmptyState';

describe('EmptyState', () => {
  it('renders title and message', () => {
    render(<EmptyState title="Nothing here" message="Try adding something." />);
    expect(screen.getByText('Nothing here')).toBeTruthy();
    expect(screen.getByText('Try adding something.')).toBeTruthy();
  });

  it('renders icon when provided', () => {
    render(<EmptyState title="No results" message="Empty." icon="search-outline" />);
    // Icon renders as Text with testID icon-<name>
    expect(screen.getByTestId('icon-search-outline')).toBeTruthy();
  });

  it('does not render icon when omitted', () => {
    render(<EmptyState title="Empty" message="No data." />);
    expect(screen.queryByTestId(/^icon-/)).toBeNull();
  });

  it('renders action button when actionLabel and onAction are provided', () => {
    render(
      <EmptyState
        title="No bookings"
        message="Book a service to get started."
        actionLabel="Book now"
        onAction={jest.fn()}
      />,
    );
    expect(screen.getByText('Book now')).toBeTruthy();
  });

  it('does not render action button when actionLabel is omitted', () => {
    render(<EmptyState title="Empty" message="Nothing." />);
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('fires onAction callback when button is pressed', () => {
    const onAction = jest.fn();
    render(
      <EmptyState
        title="Empty"
        message="Try again."
        actionLabel="Retry"
        onAction={onAction}
      />,
    );
    fireEvent.press(screen.getByText('Retry'));
    expect(onAction).toHaveBeenCalledTimes(1);
  });

  it('does not render button when only onAction is provided without actionLabel', () => {
    render(<EmptyState title="Empty" message="Nothing." onAction={jest.fn()} />);
    expect(screen.queryByRole('button')).toBeNull();
  });
});
