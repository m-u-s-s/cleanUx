import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { EmptyState } from '@/ui/EmptyState';
import { ErrorState } from '@/ui/ErrorState';

describe('EmptyState', () => {
  it('renders title and message', () => {
    const { getByText } = render(
      <EmptyState title="Pas de données" message="Rien à afficher." />
    );
    expect(getByText('Pas de données')).toBeTruthy();
    expect(getByText('Rien à afficher.')).toBeTruthy();
  });

  it('renders action button when actionLabel + onAction provided', () => {
    const onAction = jest.fn();
    const { getByText } = render(
      <EmptyState title="Vide" message="Msg" actionLabel="Ajouter" onAction={onAction} />
    );
    const btn = getByText('Ajouter');
    expect(btn).toBeTruthy();
    fireEvent.press(btn);
    expect(onAction).toHaveBeenCalledTimes(1);
  });

  it('does not render button when actionLabel is absent', () => {
    const { queryByText } = render(
      <EmptyState title="Vide" message="Msg" />
    );
    expect(queryByText('Ajouter')).toBeNull();
  });
});

describe('ErrorState', () => {
  it('renders default message', () => {
    const { getByText } = render(<ErrorState />);
    expect(getByText('Oups !')).toBeTruthy();
    expect(getByText('Une erreur est survenue.')).toBeTruthy();
  });

  it('renders custom message', () => {
    const { getByText } = render(<ErrorState message="Erreur réseau." />);
    expect(getByText('Erreur réseau.')).toBeTruthy();
  });

  it('renders retry button and calls onRetry', () => {
    const onRetry = jest.fn();
    const { getByText } = render(<ErrorState onRetry={onRetry} />);
    fireEvent.press(getByText('Réessayer'));
    expect(onRetry).toHaveBeenCalledTimes(1);
  });

  it('does not render retry button when onRetry absent', () => {
    const { queryByText } = render(<ErrorState />);
    expect(queryByText('Réessayer')).toBeNull();
  });
});
