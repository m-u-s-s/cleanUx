import React from 'react';
import { ScrollView } from 'react-native';
import { fireEvent, render, within } from '@testing-library/react-native';

const mockNavigate = jest.fn();
const mockReplace = jest.fn();
let mockMode: 'asap' | 'scheduled' = 'asap';
let mockResultat: Record<string, unknown> = {};
let mockReduit = false;

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate, replace: mockReplace }),
  useRoute: () => ({ params: { mode: mockMode } }),
}));

jest.mock('@/catalogue', () => ({
  ...jest.requireActual('@/catalogue'),
  useCatalogue: () => mockResultat,
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    Screen: ({ children, testID }: any) => <View testID={testID}>{children}</View>,
    GlassSurface: ({ children, testID, style }: any) => <View testID={testID} style={style}>{children}</View>,
    Icon: ({ name }: any) => <Text>{name}</Text>,
    Button: ({ label, onPress, testID }: any) => <Text onPress={onPress} testID={testID}>{label}</Text>,
    Skeleton: () => <View testID="squelette" />,
    ErrorState: ({ message, onRetry }: any) => <Text onPress={onRetry} testID="erreur">{message}</Text>,
    useReducedMotion: () => mockReduit,
  };
});

import { CatalogueScreen } from '@/screens/catalogue/CatalogueScreen';

const metier = (slug: string, name: string, floor_price_cents: number | null) => ({
  slug, name, icon: null, short_description: null, floor_price_cents, hourly: false,
});

const catalogue = (currency = 'EUR', sectors?: unknown[]) => ({
  mode: mockMode,
  zone_known: true,
  currency,
  sectors: sectors ?? [
    { slug: 'batiment', name: 'Bâtiment', icon: 'hammer', trades: [
      metier('peinture', 'Peinture', 12000),
      metier('plomberie', 'Plomberie', 8500),
      metier('electricite', 'Électricité', 9000),
      metier('toiture', 'Toiture', null),
    ] },
    { slug: 'nettoyage', name: 'Nettoyage', icon: 'sparkles', trades: [metier('vitres', 'Vitres', 6000)] },
  ],
});

const refetch = jest.fn();

const defiler = (y: number, ecran: ReturnType<typeof render>) =>
  fireEvent.scroll(ecran.getByTestId('ligne-de-sonde'), {
    nativeEvent: {
      contentOffset: { x: 0, y },
      contentSize: { width: 390, height: 1000 },
      layoutMeasurement: { width: 390, height: 484 },
    },
  });

beforeEach(() => {
  jest.clearAllMocks();
  mockMode = 'asap';
  mockReduit = false;
  mockResultat = { data: catalogue(), isLoading: false, isError: false, refetch };
});

afterEach(() => jest.restoreAllMocks());

describe('CatalogueScreen', () => {
  it('pose tous les métiers sur la ligne et ouvre la feuille sur le premier', () => {
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getAllByTestId(/^repere-/)).toHaveLength(5);
    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Peinture');
    expect(ecran.getByTestId('feuille-position').props.children).toBe('Bâtiment · 1 sur 5');
  });

  it('défiler de trois crans choisit le quatrième métier', () => {
    const ecran = render(<CatalogueScreen />);

    defiler(3 * 88, ecran);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Toiture');
    expect(ecran.getByTestId('feuille-prix').props.children).toBe('Prix selon vos réponses');
  });

  it('une liste raccourcie borne l’index de la ligne et de la feuille', () => {
    const ecran = render(<CatalogueScreen />);

    defiler(4 * 88, ecran);
    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Vitres');

    mockResultat = {
      data: catalogue('EUR', [
        { slug: 'batiment', name: 'Bâtiment', icon: 'hammer', trades: [
          metier('peinture', 'Peinture', 12000),
          metier('plomberie', 'Plomberie', 8500),
        ] },
      ]),
      isLoading: false,
      isError: false,
      refetch,
    };
    ecran.rerender(<CatalogueScreen />);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Plomberie');
    expect(ecran.getByTestId('repere-plomberie').props.accessibilityState).toEqual({ selected: true });
  });

  it('témoin : un petit mouvement garde le métier choisi', () => {
    const ecran = render(<CatalogueScreen />);

    defiler(30, ecran);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Peinture');
  });

  it('toucher un repère le choisit et le ramène au centre', () => {
    const recentrer = jest.spyOn(ScrollView.prototype as unknown as { scrollTo: (o: unknown) => void }, 'scrollTo');
    const ecran = render(<CatalogueScreen />);

    fireEvent.press(ecran.getByTestId('repere-electricite'));

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Électricité');
    expect(recentrer).toHaveBeenCalledWith({ y: 2 * 88, animated: true });
  });

  it('le recentrage programmé ne fait pas défiler la sélection à travers les repères intermédiaires', () => {
    const ecran = render(<CatalogueScreen />);

    fireEvent.press(ecran.getByTestId('repere-toiture'));
    defiler(1 * 88, ecran);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Toiture');
  });

  it('témoin : le doigt qui reprend la main rend le défilement normal', () => {
    const ecran = render(<CatalogueScreen />);

    fireEvent.press(ecran.getByTestId('repere-toiture'));
    fireEvent(ecran.getByTestId('ligne-de-sonde'), 'scrollBeginDrag');
    defiler(1 * 88, ecran);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Plomberie');
  });

  it('toucher le repère déjà centré ne fige pas le défilement qui suit', () => {
    const ecran = render(<CatalogueScreen />);

    // Peinture est déjà au centre, au décalage 0 : aucun défilement programmé ne viendra lever
    // une cible posée là — le défilement suivant, sans geste de doigt, doit être suivi.
    fireEvent.press(ecran.getByTestId('repere-peinture'));
    defiler(1 * 88, ecran);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Plomberie');
  });

  it('en mouvement réduit, le recentrage ne s’anime pas', () => {
    mockReduit = true;
    const recentrer = jest.spyOn(ScrollView.prototype as unknown as { scrollTo: (o: unknown) => void }, 'scrollTo');
    const ecran = render(<CatalogueScreen />);

    fireEvent.press(ecran.getByTestId('repere-electricite'));

    expect(recentrer).toHaveBeenCalledWith({ y: 2 * 88, animated: false });
  });

  it('Commander ouvre le moteur de commande sur le métier choisi, dans le mode', () => {
    const ecran = render(<CatalogueScreen />);

    fireEvent.press(ecran.getByTestId('repere-plomberie'));
    fireEvent.press(ecran.getByTestId('feuille-commander'));

    expect(mockNavigate).toHaveBeenCalledWith('EmbeddedModule', {
      path: '/commander/batiment/plomberie?mode=asap',
      title: 'Plomberie',
    });
  });

  it('le prix suit la devise du catalogue', () => {
    mockResultat = { data: catalogue('MAD'), isLoading: false, isError: false, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByTestId('feuille-prix').props.children).toContain('MAD');
  });

  it('en immédiat, un catalogue vide propose de prendre rendez-vous', () => {
    mockResultat = { data: catalogue('EUR', []), isLoading: false, isError: false, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByText('Aucun métier n’accepte l’intervention immédiate pour votre adresse.')).toBeTruthy();
    fireEvent.press(ecran.getByTestId('catalogue-vers-rendez-vous'));
    expect(mockReplace).toHaveBeenCalledWith('Catalogue', { mode: 'scheduled' });
  });

  it('témoin : en rendez-vous, un catalogue vide ne renvoie vers aucun autre mode', () => {
    mockMode = 'scheduled';
    mockResultat = { data: catalogue('EUR', []), isLoading: false, isError: false, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByText('Aucun métier n’est proposé pour le moment.')).toBeTruthy();
    expect(ecran.queryByTestId('catalogue-vers-rendez-vous')).toBeNull();
  });

  it('montre des squelettes pendant le chargement', () => {
    mockResultat = { data: undefined, isLoading: true, isError: false, refetch };

    expect(render(<CatalogueScreen />).getAllByTestId('squelette').length).toBeGreaterThan(0);
  });

  it('les squelettes des repères changent de côté comme eux, et la feuille a le sien', () => {
    mockResultat = { data: undefined, isLoading: true, isError: false, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByTestId('catalogue-chargement')).toBeTruthy();

    const cases = ecran.getAllByTestId(/^squelette-case-/);
    expect(cases.some(c => String(c.props.testID).startsWith('squelette-case-droite-'))).toBe(true);
    expect(cases.some(c => String(c.props.testID).startsWith('squelette-case-gauche-'))).toBe(true);

    expect(ecran.getByTestId('squelette-feuille')).toBeTruthy();
  });

  it('témoin : aucune barre de squelette n’est posée à nu sur la scène', () => {
    mockResultat = { data: undefined, isLoading: true, isError: false, refetch };
    const ecran = render(<CatalogueScreen />);

    // Chaque barre vit dans une case de verre ou dans la feuille : leur somme est le total.
    const verres = [...ecran.getAllByTestId(/^squelette-case-/), ecran.getByTestId('squelette-feuille')];
    const posees = verres.reduce((somme, verre) => somme + within(verre).getAllByTestId('squelette').length, 0);

    expect(posees).toBeGreaterThan(0);
    expect(posees).toBe(ecran.getAllByTestId('squelette').length);
  });

  it('en erreur, dit que le catalogue n’a pas pu être chargé et permet de réessayer', () => {
    mockResultat = { data: undefined, isLoading: false, isError: true, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByTestId('erreur').props.children).toBe('Le catalogue n’a pas pu être chargé.');
    fireEvent.press(ecran.getByTestId('erreur'));
    expect(refetch).toHaveBeenCalledTimes(1);
  });

  it('une erreur de rafraîchissement ne remplace pas un catalogue déjà affiché', () => {
    mockResultat = { data: catalogue(), isLoading: false, isError: true, refetch };
    const ecran = render(<CatalogueScreen />);

    expect(ecran.getByTestId('feuille-titre').props.children).toBe('Peinture');
    expect(ecran.queryByTestId('erreur')).toBeNull();
  });
});
