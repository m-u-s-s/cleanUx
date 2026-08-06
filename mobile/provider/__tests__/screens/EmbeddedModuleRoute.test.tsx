import React from 'react';
import { render } from '@testing-library/react-native';

import { EmbeddedModuleRoute } from '@/screens/EmbeddedModuleRoute';

/**
 * L'ESPACE SOCIÉTÉ N'EXISTAIT PAS SUR MOBILE CÔTÉ PRESTATAIRE.
 *
 * L'hôte WebView partagé (`mobile/shared/src/webview`) est aliasé `@/webview` dans les TROIS
 * tables de l'application prestataire — tsconfig, Babel et Jest, vérifié une à une, car elles
 * décrivent les mêmes chemins sans jamais se contrôler mutuellement.
 *
 * Pourtant, aucune route `EmbeddedModule` n'existait de ce côté : l'application cliente
 * l'exposait, la prestataire non. Les écrans société — répartition, équipes terrain, canaux,
 * membres — n'étaient donc atteignables que depuis un navigateur.
 *
 * Ce test fige le branchement : la route rend l'hôte partagé et titre l'en-tête natif.
 */

const mockSetOptions = jest.fn();
const mockGoBack = jest.fn();
const mockPush = jest.fn();

// `useDeviceId` est exporté par le paquet partagé, aux côtés de l'hôte : un seul mock suffit.
jest.mock('@/webview', () => ({
  EmbeddedModuleScreen: ({ path, title }: { path: string; title: string }) => {
    const { Text } = require('react-native');

    return <Text>{`hôte:${path}:${title}`}</Text>;
  },
  useDeviceId: () => 'appareil-de-test',
}));

type PropsDeTest = React.ComponentProps<typeof EmbeddedModuleRoute>;

function creerProps(path: string, title: string): PropsDeTest {
  return {
    route: { params: { path, title }, key: 'k', name: 'EmbeddedModule' },
    navigation: {
      setOptions: mockSetOptions,
      goBack: mockGoBack,
      push: mockPush,
    },
  } as unknown as PropsDeTest;
}

describe('EmbeddedModuleRoute (prestataire)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it("rend l'hôte WebView partagé avec le chemin demandé", () => {
    const { getByText } = render(
      <EmbeddedModuleRoute
        {...creerProps('/dashboard/entreprise-prestataire/equipes-terrain', 'Équipes terrain')}
      />,
    );

    getByText('hôte:/dashboard/entreprise-prestataire/equipes-terrain:Équipes terrain');
  });

  it("titre l'en-tête natif avec le titre du module", () => {
    render(<EmbeddedModuleRoute {...creerProps('/dashboard/entreprise-prestataire/dispatch', 'Répartition')} />);

    expect(mockSetOptions).toHaveBeenCalledWith({ title: 'Répartition' });
  });
});
