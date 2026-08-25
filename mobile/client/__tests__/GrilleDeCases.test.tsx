import React from 'react';
import { render } from '@testing-library/react-native';
import { GrilleDeCases } from '@/ui/GrilleDeCases';

/**
 * LA GRILLE DE CASES REND CE QU'ON LUI DONNE, ET RIEN DE PLUS.
 *
 * Les pieges qu'on verifie ici sont ceux qui ne se voient pas sur une maquette : une
 * unite collee a la valeur sans espace, une note affichee alors qu'elle est absente, un
 * libelle d'accessibilite qui oublie l'unite — un lecteur d'ecran annoncerait « surface
 * 85 » sans dire de quoi.
 */
describe('GrilleDeCases', () => {
  it('rend le libelle, la valeur et l unite', () => {
    const { getByText } = render(
      <GrilleDeCases cases={[{ libelle: 'Surface', valeur: 85, unite: 'm²' }]} />,
    );

    expect(getByText('Surface')).toBeTruthy();
    expect(getByText(/85/)).toBeTruthy();
    expect(getByText(/m²/)).toBeTruthy();
  });

  it('annonce la valeur ET son unite a un lecteur d ecran', () => {
    const { getByLabelText } = render(
      <GrilleDeCases cases={[{ libelle: 'Distance', valeur: 12, unite: 'km' }]} />,
    );

    expect(getByLabelText('Distance : 12 km')).toBeTruthy();
  });

  /** TEMOIN NEGATIF — sans note, aucune ligne vide ne s'affiche sous la valeur. */
  it('n affiche pas de note quand il n y en a pas', () => {
    const { queryByText } = render(
      <GrilleDeCases cases={[{ libelle: 'Zone', valeur: 'Bruxelles' }]} />,
    );

    expect(queryByText('undefined')).toBeNull();
    expect(queryByText('')).toBeNull();
  });

  it('rend autant de cases qu on lui en donne', () => {
    const { getByText } = render(
      <GrilleDeCases
        colonnes={3}
        cases={[
          { libelle: 'Un', valeur: 1 },
          { libelle: 'Deux', valeur: 2, ton: 'bon' },
          { libelle: 'Trois', valeur: 3, ton: 'alerte' },
        ]}
      />,
    );

    expect(getByText('Un')).toBeTruthy();
    expect(getByText('Deux')).toBeTruthy();
    expect(getByText('Trois')).toBeTruthy();
  });

  /** Une grille vide ne doit pas lever : un ecran sans donnee la rend quand meme. */
  it('supporte une grille vide', () => {
    expect(() => render(<GrilleDeCases cases={[]} />)).not.toThrow();
  });
});
