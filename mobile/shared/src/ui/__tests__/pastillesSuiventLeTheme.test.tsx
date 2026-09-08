import React from 'react';
import { render } from '@testing-library/react-native';
import { Badge } from '../Badge';
import { Tag } from '../Tag';
import * as theme from '../../theme/useThemeColors';
import { colors } from '../../theme/colors';

const jetons = (isDark: boolean) =>
  ({
    isDark,
    textSecondary: isDark ? '#93a4c6' : '#525252',
    brandText: isDark ? colors.brand[400] : colors.brand[600],
    success: isDark ? colors.success[500] : colors.success[700],
    warning: isDark ? colors.warning[500] : colors.warning[700],
    danger: isDark ? colors.danger[500] : colors.danger[600],
    tint: {
      brand: isDark ? 'rgba(99, 102, 241, 0.22)' : 'rgba(99, 102, 241, 0.10)',
      success: isDark ? 'rgba(16, 185, 129, 0.20)' : 'rgba(16, 185, 129, 0.10)',
      warning: isDark ? 'rgba(245, 158, 11, 0.20)' : 'rgba(245, 158, 11, 0.12)',
      danger: isDark ? 'rgba(239, 68, 68, 0.20)' : 'rgba(239, 68, 68, 0.10)',
    },
  }) as unknown as theme.ThemeTokens;

/** Le fond de la pastille : la racine rendue porte le style, `.parent` d'un Text ne l'atteint pas. */
const fond = (arbre: any): string => {
  const style = Array.isArray(arbre.props.style) ? Object.assign({}, ...arbre.props.style) : arbre.props.style;

  return style.backgroundColor;
};

/**
 * LES EXTRÉMITÉS CLAIRES DES RAMPES SONT DES NEUTRES DÉGUISÉS.
 *
 * `Badge` et `Tag` portaient `colors.brand[100]`, `colors.success[50]` et consorts : des voiles
 * conçus pour un fond blanc. Sur la nuit, chaque pastille devenait une tache claire — et le module
 * de thème le dit déjà là où il déclare `tint`, sans que ces deux composants aient migré.
 *
 * Ils sont employés à 61 endroits dans les deux applications : c'est la pastille de statut.
 */
describe('les pastilles suivent le thème', () => {
  const poser = (isDark: boolean) => jest.spyOn(theme, 'useThemeColors').mockReturnValue(jetons(isDark));

  afterEach(() => jest.restoreAllMocks());

  it('le fond d’une pastille change avec le thème', () => {
    poser(false);
    const clair = render(<Badge label="En attente" variant="brand" />);
    const fondClair = fond(clair.toJSON());

    clair.unmount();
    poser(true);
    const sombre = render(<Badge label="En attente" variant="brand" />);

    expect(fondClair).toBeDefined();
    expect(fond(sombre.toJSON())).not.toBe(fondClair);
  });

  it('témoin : plus aucune rampe claire en sombre', () => {
    poser(true);
    const rendu = render(<Badge label="Payé" variant="success" />);

    // `success[50]` est le voile blanc-vert de l'ancienne palette : il ne doit plus apparaître.
    expect(fond(rendu.toJSON())).not.toBe(colors.success[50]);
  });

  it('le tag suit la même règle', () => {
    poser(true);
    const rendu = render(<Tag label="Urgent" variant="urgent" />);

    expect(fond(rendu.toJSON())).not.toBe(colors.danger[50]);
  });

  it('témoin : le clair ne bouge pas pour le neutre', () => {
    poser(false);
    const rendu = render(<Badge label="Brouillon" />);

    expect(fond(rendu.toJSON())).toBe(colors.surface[200]);
  });
});
