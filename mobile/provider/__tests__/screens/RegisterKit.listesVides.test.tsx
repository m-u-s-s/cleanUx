import React from 'react';
import { render, screen } from '@testing-library/react-native';

import { TradePicker, ZonePicker } from '@/screens/auth/kit';
import { useRegistrationOptions } from '@/catalog';

/**
 * « LA REQUÊTE A ÉCHOUÉ » ET « LA LISTE EST VIDE » SONT DEUX CHOSES.
 *
 * Les deux sélecteurs de l'inscription les confondaient : `isError || liste.length === 0` menait au
 * même message, « Impossible de charger la liste des métiers ».
 *
 * La différence n'est pas cosmétique. Dans un cas réessayer résout ; dans l'autre réessayer ne
 * résoudra JAMAIS. Un candidat prestataire devant une liste vide relançait donc indéfiniment un
 * écran qui ne pouvait pas changer — et l'étape suivante lui refuse d'avancer, puisque sans métier
 * déclaré aucune mission ne peut lui être proposée. C'est un candidat perdu, silencieusement.
 *
 * VU EN VRAI sur l'émulateur, à l'étape 7 sur 8 du parcours d'inscription prestataire.
 */
jest.mock('@/catalog', () => ({
  useRegistrationOptions: jest.fn(),
  flattenTrades: (options: any) => options?.trades ?? [],
  zonesPourMetier: (options: any) => options?.zones ?? [],
}));

const mockOptions = useRegistrationOptions as jest.Mock;

describe('Les sélecteurs de l’inscription distinguent l’échec du vide', () => {
  afterEach(() => jest.clearAllMocks());

  describe('les métiers', () => {
    it('annonce une panne de chargement ET propose de réessayer', () => {
      mockOptions.mockReturnValue({ data: undefined, isLoading: false, isError: true, refetch: jest.fn() });

      render(<TradePicker value={null} onChange={jest.fn()} />);

      expect(screen.getByText(/n’a pas pu être chargée/)).toBeTruthy();
      expect(screen.getByTestId('register-trades-retry')).toBeTruthy();
    });

    /**
     * LE CŒUR DU CORRECTIF : une liste vide ne propose PAS de réessayer.
     *
     * Le bouton serait un geste sans effet — et un geste sans effet proposé par l'interface est
     * pire qu'aucun geste : il fait recommencer au lieu de faire comprendre.
     */
    it('dit qu’aucun métier n’est ouvert, SANS proposer de réessayer', () => {
      mockOptions.mockReturnValue({ data: { trades: [] }, isLoading: false, isError: false, refetch: jest.fn() });

      render(<TradePicker value={null} onChange={jest.fn()} />);

      expect(screen.getByTestId('register-trades-empty')).toBeTruthy();
      expect(screen.getByText(/Aucun métier n’est ouvert/)).toBeTruthy();
      expect(screen.queryByTestId('register-trades-retry')).toBeNull();
      // Et surtout : plus le message de panne, qui envoyait vérifier une connexion qui va bien.
      expect(screen.queryByText(/n’a pas pu être chargée/)).toBeNull();
    });

    /**
     * TÉMOIN — avec des métiers, la liste s'affiche.
     *
     * Sans lui, les deux tests ci-dessus passeraient au vert sur un composant qui ne rendrait
     * jamais rien d'autre qu'un message.
     */
    it('témoin : les métiers disponibles s’affichent', () => {
      mockOptions.mockReturnValue({
        data: { trades: [{ id: 7, name: 'Nettoyage' }] },
        isLoading: false,
        isError: false,
        refetch: jest.fn(),
      });

      render(<TradePicker value={null} onChange={jest.fn()} />);

      expect(screen.getByText('Nettoyage')).toBeTruthy();
      expect(screen.queryByTestId('register-trades-empty')).toBeNull();
    });
  });

  describe('les zones', () => {
    /**
     * UNE LISTE VIDE N'Y DIT PAS LA MÊME CHOSE.
     *
     * Les zones sont filtrées PAR MÉTIER : zéro zone signifie que le métier choisi n'est ouvert
     * nulle part, pas que la plateforme n'a aucune zone. Le message renvoie donc vers le choix du
     * métier — le seul geste qui puisse débloquer.
     */
    it('renvoie vers le choix du métier quand aucune zone ne le sert', () => {
      mockOptions.mockReturnValue({ data: { zones: [] }, isLoading: false, isError: false, refetch: jest.fn() });

      render(<ZonePicker tradeId={7} value={[]} onChange={jest.fn()} />);

      expect(screen.getByTestId('register-zones-empty')).toBeTruthy();
      expect(screen.getByText(/Choisissez un autre métier/)).toBeTruthy();
      expect(screen.queryByTestId('register-zones-retry')).toBeNull();
    });

    it('propose de réessayer quand le chargement a vraiment échoué', () => {
      mockOptions.mockReturnValue({ data: undefined, isLoading: false, isError: true, refetch: jest.fn() });

      render(<ZonePicker tradeId={7} value={[]} onChange={jest.fn()} />);

      expect(screen.getByTestId('register-zones-retry')).toBeTruthy();
    });
  });
});
