/**
 * Le bouton en mode sombre, et sa variante `glass`.
 *
 * POURQUOI CE FICHIER EXISTE EN DEHORS DES TESTS DU BOUTON. Ceux-ci vérifient le comportement —
 * on presse, ça appelle ; désactivé, ça n'appelle pas. Ici on vérifie la MATIÈRE, qui dépend du
 * thème, ce qui demande de piloter le schéma de couleurs.
 *
 * CE QU'ILS ATTRAPENT. Le bouton avait échappé au garde-fou anti-couleur-en-dur : ses couleurs
 * sont dans des ternaires (`color: isDisabled ? colors.surface[400] : v.text`) et le motif ne
 * regardait que ce qui suit immédiatement les deux-points. Résultat, l'état désactivé posait un
 * gris clair sur le fond nuit — un pavé lumineux là où il fallait une absence.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';

const mockScheme = { colorScheme: 'dark' as 'dark' | 'light', mode: 'dark', setMode: jest.fn() };

jest.mock('@/theme/useColorScheme', () => ({ useColorScheme: () => mockScheme }));

import { Button } from '@/ui/Button';

describe('Button en mode sombre', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'dark';
  });

  it('n’éclaire pas l’état désactivé sur fond nuit', () => {
    render(<Button label="Envoyer" onPress={jest.fn()} disabled />);

    /*
     * `colors.surface[300]` est un gris CLAIR. Posé sur le fond nuit, un bouton désactivé
     * devenait plus visible que le bouton actif — l'inverse exact de ce qu'un état désactivé doit
     * communiquer.
     */
    const fond = aplat(screen.getByRole('button').props.style).backgroundColor;

    expect(fond).not.toBe('#d4d4d4');
    expect(luminosite(fond)).toBeLessThan(0.35);
  });

  it('garde en clair exactement le rendu d’avant', () => {
    mockScheme.colorScheme = 'light';

    render(<Button label="Envoyer" onPress={jest.fn()} disabled />);

    // Le mode clair n'est pas touché par ce chantier : le gris d'origine reste le gris d'origine.
    expect(aplat(screen.getByRole('button').props.style).backgroundColor).toBe('#d4d4d4');
  });
});

describe('Button variante glass', () => {
  beforeEach(() => {
    mockScheme.colorScheme = 'dark';
  });

  it('pose un voile translucide en mode sombre', () => {
    render(<Button label="Continuer" onPress={jest.fn()} variant="glass" />);

    const fond = aplat(screen.getByRole('button').props.style).backgroundColor;

    // Translucide, donc rgba avec un alpha strictement entre 0 et 1 : ni un aplat, ni rien.
    expect(fond).toMatch(/^rgba\(/);
    expect(alpha(fond)).toBeGreaterThan(0);
    expect(alpha(fond)).toBeLessThan(1);
  });

  it('écrit son libellé en clair sur le voile', () => {
    render(<Button label="Continuer" onPress={jest.fn()} variant="glass" />);

    /*
     * Un voile clair à 10 % sur fond nuit reste sombre. Le libellé doit donc être clair — c'est
     * la faute classique du verre : on translucidifie le fond et on garde le texte du mode clair.
     */
    expect(luminosite(aplat(screen.getByText('Continuer').props.style).color)).toBeGreaterThan(0.7);
  });

  /*
   * CE TEST EXIGEAIT QUE `glass` RETOMBE SUR `secondary` EN CLAIR, ET C'EST LEVÉ.
   *
   * La raison d'origine tenait : sans rien derrière à filtrer, un voile translucide sur un
   * aplat uni ne se distingue pas d'une surface opaque. Depuis que la toile claire porte des
   * auras, il y a quelque chose à filtrer.
   */
  it('pose un voile translucide en mode clair aussi', () => {
    mockScheme.colorScheme = 'light';

    render(<Button label="Continuer" onPress={jest.fn()} variant="glass" />);

    const fond = aplat(screen.getByRole('button').props.style).backgroundColor;

    expect(fond).toMatch(/^rgba\(/);
    expect(alpha(fond)).toBeGreaterThan(0);
    expect(alpha(fond)).toBeLessThan(1);
  });

  /**
   * TÉMOIN — le voile CLAIR est plus dense que le sombre, et ce n'est pas un détail.
   *
   * C'est le voile qui porte le contraste du libellé, pas le flou : un flou mélange les
   * pixels, il ne les fonce pas. Un voile clair aussi ténu que le sombre laisserait le
   * libellé illisible dès qu'une aura passe dessous.
   */
  it('le voile clair est plus dense que le voile sombre', () => {
    mockScheme.colorScheme = 'light';
    render(<Button label="Continuer" onPress={jest.fn()} variant="glass" />);
    const clair = alpha(aplat(screen.getByRole('button').props.style).backgroundColor);

    screen.unmount();

    mockScheme.colorScheme = 'dark';
    render(<Button label="Continuer" onPress={jest.fn()} variant="glass" />);
    const sombre = alpha(aplat(screen.getByRole('button').props.style).backgroundColor);

    expect(clair).toBeGreaterThan(sombre);
  });

  /** Et son libellé reste SOMBRE : la faute inverse de celle du mode nuit. */
  it('ecrit son libelle en sombre sur le voile clair', () => {
    mockScheme.colorScheme = 'light';

    render(<Button label="Continuer" onPress={jest.fn()} variant="glass" />);

    expect(luminosite(aplat(screen.getByText('Continuer').props.style).color)).toBeLessThan(0.4);
  });

  it('respecte disabled et loading comme les autres variantes', () => {
    const onPress = jest.fn();

    render(<Button label="Continuer" onPress={onPress} variant="glass" disabled />);
    fireEvent.press(screen.getByText('Continuer'));
    expect(onPress).not.toHaveBeenCalled();

    screen.unmount();
    render(<Button label="Charger" onPress={onPress} variant="glass" loading />);
    fireEvent.press(screen.getByText('Charger'));
    expect(onPress).not.toHaveBeenCalled();
  });

  it('reste pressable quand rien ne l’empêche', () => {
    const onPress = jest.fn();

    render(<Button label="Continuer" onPress={onPress} variant="glass" />);
    fireEvent.press(screen.getByText('Continuer'));

    expect(onPress).toHaveBeenCalledTimes(1);
  });
});

/** Réduit un style React Native — objet, tableau, imbriqué — à un seul objet. */
function aplat(style: unknown): Record<string, unknown> {
  if (Array.isArray(style)) {
    return style.reduce<Record<string, unknown>>((acc, s) => ({ ...acc, ...aplat(s) }), {});
  }

  return (style ?? {}) as Record<string, unknown>;
}

/** Alpha d'un `rgba(…)`. */
function alpha(couleur: unknown): number {
  const m = /rgba\([^,]+,[^,]+,[^,]+,\s*([\d.]+)\s*\)/.exec(String(couleur));

  return m ? Number(m[1]) : 1;
}

/**
 * Le fond nuit, sur lequel toute couleur translucide est composée.
 *
 * Le tuple est explicite : sous `noUncheckedIndexedAccess`, un `number[]` rendrait chaque accès
 * indexé possiblement `undefined`.
 */
const NUIT: readonly [number, number, number] = [7, 11, 20];

/**
 * Luminosité PERÇUE, de 0 (noir) à 1 (blanc).
 *
 * DEUX PIÈGES, tous deux rencontrés en écrivant ces tests.
 *
 * Le premier : une couleur translucide doit être composée sur son fond avant d'être jugée.
 * `rgba(232, 238, 252, 0.08)` a des composantes quasi blanches et se lit pourtant très sombre sur
 * le nuit — juger les composantes brutes donnait 0,93 pour une couleur qui vaut 0,11 à l'œil.
 *
 * Le second : la pondération. ITU-R BT.601, parce que l'œil est bien plus sensible au vert qu'au
 * bleu. Une moyenne arithmétique jugerait un bleu vif aussi clair qu'un vert vif.
 */
function luminosite(couleur: unknown): number {
  const texte = String(couleur);
  let r: number;
  let g: number;
  let b: number;

  const hex = /^#([0-9a-f]{6})$/i.exec(texte);
  const rgba = /rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/.exec(texte);

  if (hex) {
    const n = parseInt(hex[1] ?? '', 16);
    [r, g, b] = [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  } else if (rgba) {
    [r, g, b] = [Number(rgba[1]), Number(rgba[2]), Number(rgba[3])];
  } else {
    throw new Error(`couleur illisible : ${texte}`);
  }

  const a = alpha(texte);
  const melange = (c: number, fond: number) => c * a + fond * (1 - a);

  return (
    (0.299 * melange(r, NUIT[0]) +
      0.587 * melange(g, NUIT[1]) +
      0.114 * melange(b, NUIT[2])) /
    255
  );
}
