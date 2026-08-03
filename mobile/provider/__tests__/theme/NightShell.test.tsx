/**
 * La coque nuit : la toile est montée, et rien ne la recouvre.
 *
 * POURQUOI CE FICHIER. Le fond nuit peut être parfait et ne se voir nulle part. Trois couches
 * différentes peuvent le masquer, et aucune ne produit d'erreur :
 *
 *   1. la coque n'est pas montée à la racine ;
 *   2. le conteneur de navigation peint son propre fond sous chaque écran ;
 *   3. `Screen` repeint le sien par-dessus.
 *
 * La troisième est la pire : la couleur masquante est presque celle de la toile, donc une capture
 * d'écran ne montre rien d'anormal — seules les gouttes manquent, et il faut savoir qu'elles
 * devaient être là.
 */
import React from 'react';
import { Text } from 'react-native';
import { render, screen } from '@testing-library/react-native';

const mockScheme = { colorScheme: 'dark' as 'dark' | 'light', mode: 'dark', setMode: jest.fn() };

jest.mock('@/theme/useColorScheme', () => ({ useColorScheme: () => mockScheme }));

import { NightShell, themeDeNavigation } from '@/ui/NightShell';
import { Screen } from '@/ui/Screen';

const MASQUE = { includeHiddenElements: true } as const;

describe('NightShell', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'dark';
  });

  it('monte la toile nuit sous ses enfants', () => {
    render(
      <NightShell>
        <Text>application</Text>
      </NightShell>,
    );

    expect(screen.getByTestId('luxe-background', MASQUE)).toBeTruthy();
    expect(screen.getByText('application')).toBeTruthy();
  });

  it('ne monte aucune toile en mode clair', () => {
    mockScheme.colorScheme = 'light';

    render(
      <NightShell>
        <Text>application</Text>
      </NightShell>,
    );

    expect(screen.queryByTestId('luxe-background', MASQUE)).toBeNull();
    expect(screen.getByText('application')).toBeTruthy();
  });
});

describe('themeDeNavigation', () => {
  it('rend le conteneur de navigation transparent en sombre', () => {
    /*
     * React Navigation peint un fond sous chaque écran, dans une couche qui n'apparaît dans aucun
     * de nos fichiers. Laissée opaque, elle masque la toile entièrement — le genre d'oubli qu'on
     * ne trouve qu'en cherchant pourquoi « le fond ne marche pas ».
     */
    const t = themeDeNavigation(true);

    expect(t.colors.background).toBe('transparent');
    expect(t.colors.card).toBe('transparent');
  });

  it('laisse le thème clair intact', () => {
    const t = themeDeNavigation(false);

    expect(t.colors.background).not.toBe('transparent');
    expect(t.dark).toBe(false);
  });
});

describe('Screen', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'dark';
  });

  it('ne repeint pas son fond en sombre', () => {
    render(
      <Screen>
        <Text>contenu</Text>
      </Screen>,
    );

    expect(aplat(screen.getByTestId('screen-safe').props.style).backgroundColor).toBe(
      'transparent',
    );
  });

  it('garde son fond plein en clair', () => {
    mockScheme.colorScheme = 'light';

    render(
      <Screen>
        <Text>contenu</Text>
      </Screen>,
    );

    // Le mode clair n'est pas touché : la valeur historique, telle quelle.
    expect(aplat(screen.getByTestId('screen-safe').props.style).backgroundColor).toBe(
      '#fafafa',
    );
  });
});

/** Réduit un style React Native — objet, tableau, imbriqué — à un seul objet. */
function aplat(style: unknown): Record<string, unknown> {
  if (Array.isArray(style)) {
    return style.reduce<Record<string, unknown>>((acc, s) => ({ ...acc, ...aplat(s) }), {});
  }

  return (style ?? {}) as Record<string, unknown>;
}
