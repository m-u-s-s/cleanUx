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

  /*
   * LE CLAIR A SA TOILE, LUI AUSSI — depuis Iceberg.
   *
   * Ce test disait « ne monte aucune toile en mode clair » et interrogeait `luxe-background`. Il
   * est resté vert quand le clair s'est mis à rendre, parce que la toile claire porte un AUTRE
   * point de montage : il mesurait un renommage, plus une absence.
   */
  it('monte la toile claire sous ses enfants', () => {
    mockScheme.colorScheme = 'light';

    render(
      <NightShell>
        <Text>application</Text>
      </NightShell>,
    );

    expect(screen.getByTestId('luxe-background-clair', MASQUE)).toBeTruthy();
    expect(screen.getByText('application')).toBeTruthy();
  });

  /** TÉMOIN : les deux toiles ne se confondent pas — le clair ne monte pas celle de la nuit. */
  it('la toile claire n est pas celle de la nuit', () => {
    mockScheme.colorScheme = 'light';

    render(
      <NightShell>
        <Text>application</Text>
      </NightShell>,
    );

    expect(screen.queryByTestId('luxe-background', MASQUE)).toBeNull();
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

  /*
   * LE MÊME PIÈGE DU CÔTÉ CLAIR. Ce test exigeait l'inverse — « laisse le thème clair intact » —
   * ce qui était juste tant que le clair n'avait rien à laisser voir. Depuis Iceberg, un fond
   * opaque ici rend la page uniformément #f4f8fb : l'iceberg est dessiné et ne se voit nulle part.
   */
  it('rend le conteneur de navigation transparent en clair aussi', () => {
    const t = themeDeNavigation(false);

    expect(t.colors.background).toBe('transparent');
    expect(t.colors.card).toBe('transparent');
  });

  /** TÉMOIN : transparent des deux côtés ne veut pas dire identique — le reste suit le thème. */
  it('les deux thèmes restent distincts', () => {
    const clair = themeDeNavigation(false);
    const sombre = themeDeNavigation(true);

    expect(clair.dark).toBe(false);
    expect(sombre.dark).toBe(true);
    expect(clair.colors.text).not.toBe(sombre.colors.text);
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

  /*
   * CE TEST EXIGEAIT UN FOND PLEIN EN CLAIR, ET CE N'EST PLUS VRAI.
   *
   * Tant que la toile ne rendait rien en clair, un aplat par écran ne masquait rien. Depuis
   * qu'elle porte des auras, cet aplat effacerait exactement ce que le verre est censé
   * filtrer — et la régression serait invisible sur une capture, la couleur étant presque la
   * même.
   */
  it('ne repeint pas son fond en clair non plus', () => {
    mockScheme.colorScheme = 'light';

    render(
      <Screen>
        <Text>contenu</Text>
      </Screen>,
    );

    expect(aplat(screen.getByTestId('screen-safe').props.style).backgroundColor).toBe(
      'transparent',
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
