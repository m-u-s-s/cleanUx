/**
 * Inscription prestataire : ce que la requête doit porter, et quand elle ne doit pas partir.
 *
 * Le parcours est désormais un wizard — une question par écran — mais les invariants restent ceux
 * du formulaire d'origine, tous nés de défauts observés :
 *  - la requête n'annonçait pas le type de compte, donc le serveur créait un CLIENT. Comme toute
 *    la surface prestataire est gardée par `role:employe` — routes d'onboarding comprises — le
 *    compte obtenu était définitivement enfermé hors de tout.
 *  - la requête ne portait aucun jeton captcha alors que /auth/register exige le middleware
 *    `turnstile`, ce qui refusait l'inscription en production.
 *
 * S'y ajoute ce que le wizard introduit : le téléphone vérifié par SMS avant tout le reste, et
 * son jeton transmis à l'inscription.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

/** Brouillon local : mémoire volatile, remise à zéro entre les tests. */
const memoryStore: Record<string, string> = {};
jest.mock('@react-native-async-storage/async-storage', () => ({
  __esModule: true,
  default: {
    getItem: jest.fn(async (k: string) => memoryStore[k] ?? null),
    setItem: jest.fn(async (k: string, v: string) => { memoryStore[k] = v; }),
    removeItem: jest.fn(async (k: string) => { delete memoryStore[k]; }),
  },
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  },
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn(), goBack: jest.fn() }),
}));

jest.mock('@/auth', () => {
  const actual = jest.requireActual('@/auth');
  return { ...actual, useAuth: () => ({ setUser: jest.fn() }) };
});

jest.mock('react-native-reanimated', () => {
  const { View, Text } = require('react-native');
  const ReactLocal = require('react');
  const Passthrough = ReactLocal.forwardRef(({ children, ...rest }: any, ref: any) => (
    <View ref={ref} {...rest}>{children}</View>
  ));
  const TextPassthrough = ReactLocal.forwardRef(({ children, ...rest }: any, ref: any) => (
    <Text ref={ref} {...rest}>{children}</Text>
  ));
  const chainable: any = new Proxy(() => chainable, { get: () => () => chainable });
  return {
    __esModule: true,
    default: { View: Passthrough, Text: TextPassthrough },
    FadeIn: chainable,
    FadeOut: chainable,
    FadeInDown: chainable,
    FadeInRight: chainable,
    Easing: { inOut: () => undefined, out: () => undefined, ease: undefined, cubic: undefined },
    useSharedValue: (v: any) => ({ value: v }),
    useAnimatedStyle: () => ({}),
    withTiming: (v: any) => v,
    withRepeat: (v: any) => v,
    withDelay: (_d: any, v: any) => v,
  };
});

/**
 * Pilote le widget captcha depuis chaque test : 'skipped' reproduit l'absence de clé publique
 * (dev), 'token' une résolution réussie, 'pending' un widget qui n'a pas encore répondu.
 */
const mockCaptcha: { mode: 'skipped' | 'token' | 'pending' } = { mode: 'skipped' };

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  const ReactLocal = require('react');
  return {
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ReactLocal.forwardRef(({ label, value, onChangeText, error }: any, ref: any) => (
      <View>
        <RNTextInput ref={ref} accessibilityLabel={label} value={value} onChangeText={onChangeText} />
        {error ? <Text testID={`input-error-${label}`}>{error}</Text> : null}
      </View>
    )),
    Divider: () => <View />,
    Icon: () => <View />,
    ProgressBar: ({ step, totalSteps }: any) => <Text>{`Étape ${step} sur ${totalSteps}`}</Text>,
    useReducedMotion: () => false,
    TurnstileWidget: ({ onToken, onSkipped, testID }: any) => {
      ReactLocal.useEffect(() => {
        if (mockCaptcha.mode === 'skipped') onSkipped?.();
        if (mockCaptcha.mode === 'token') onToken?.('jeton-turnstile-test');
      }, []);

      return <View testID={testID} />;
    },
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' },
    warning: { 50: '#fffbeb', 700: '#b45309' },
    success: { 600: '#059669' },
    accent: { amber: '#ffb648', amberDeep: '#ff8a3d', cyan: '#4fe3d6', violet: '#8b7bff' },
    surface: { 200: '#e5e5e5', 300: '#d4d4d4', 400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040' },
    danger: { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
    mode: { tool: { ink: '#0f172a', muted: '#64748b' } },
  },
  radius: { md: 14, lg: 22, pill: 999 },
  shadows: { md: {} },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24, xl: 32, '2xl': 40, '3xl': 48 },
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '4xl': 36 },
    fontWeight: { medium: '500', semibold: '600', bold: '700', extraBold: '800' },
  },
}));

import { apiClient } from '@/api';
import { LoginScreen } from '@/screens/LoginScreen';

const apiMock = new MockAdapter(apiClient);
const TRADE_ID = 3;
const ZONE_ID = 4;
const PHONE_NATIONAL = '0470123456';
const PHONE_E164 = '+32470123456';

function renderScreen() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <LoginScreen />
    </QueryClientProvider>,
  );
}

function registerCalls() {
  return apiMock.history['post']!.filter(c => c.url === '/auth/register');
}

/** Ouvre l'inscription et franchit la vérification du téléphone, premier écran du parcours. */
async function openRegisterAndVerifyPhone() {
  fireEvent.press(screen.getByText("Pas encore de compte ? S'inscrire"));

  await waitFor(() => screen.getByLabelText('Téléphone'));
  fireEvent.changeText(screen.getByLabelText('Téléphone'), PHONE_NATIONAL);
  fireEvent.press(screen.getByLabelText('Recevoir le code'));

  await waitFor(() => screen.getByLabelText('Code de vérification'));
  fireEvent.changeText(screen.getByLabelText('Code de vérification'), '482915');
  fireEvent.press(screen.getByLabelText('Vérifier'));

  await waitFor(() => screen.getByLabelText('Prénom'));
}

/** Prénom, nom, email, mot de passe : les quatre écrans qui suivent l'OTP. */
async function fillIdentity() {
  fireEvent.changeText(screen.getByLabelText('Prénom'), 'Jean');
  fireEvent.changeText(screen.getByLabelText('Nom'), 'Dupont');
  fireEvent.press(screen.getByLabelText('Continuer'));

  await waitFor(() => screen.getByLabelText('Email'));
  fireEvent.changeText(screen.getByLabelText('Email'), 'jean@exemple.be');
  fireEvent.press(screen.getByLabelText('Continuer'));

  await waitFor(() => screen.getByLabelText('Mot de passe'));
  fireEvent.changeText(screen.getByLabelText('Mot de passe'), 'motdepasse123');
  fireEvent.press(screen.getByLabelText('Continuer'));

  await waitFor(() => screen.getByTestId('register-kind-independent'));
}

async function chooseKind(kind: 'independent' | 'company') {
  fireEvent.press(screen.getByTestId(`register-kind-${kind}`));
  fireEvent.press(screen.getByLabelText('Continuer'));
}

async function chooseTrade() {
  await waitFor(() => screen.getByTestId(`register-trade-${TRADE_ID}`));
  fireEvent.press(screen.getByTestId(`register-trade-${TRADE_ID}`));
  fireEvent.press(screen.getByLabelText('Continuer'));
}

/**
 * OÙ le prestataire intervient — l'étape qui manquait.
 *
 * L'inscription native ne demandait que le métier : `employee_zone_assignments` restait vide, et
 * le dispatch planifié, qui travaille sur les zones DÉCLARÉES, ne trouvait ce prestataire dans
 * aucune zone. Il passait la vérification et ne recevait jamais un seul rendez-vous.
 */
async function chooseZone() {
  await waitFor(() => screen.getByTestId(`register-zone-${ZONE_ID}`));
  fireEvent.press(screen.getByTestId(`register-zone-${ZONE_ID}`));
  fireEvent.press(screen.getByLabelText('Continuer'));
}

async function acceptAndSubmit() {
  await waitFor(() => screen.getByTestId('register-accept-terms'));
  fireEvent.press(screen.getByTestId('register-accept-terms'));
  fireEvent.press(screen.getByLabelText('Créer mon compte'));
}

/** Parcours complet d'un indépendant, sur un métier sans question réglementaire. */
async function completeIndependentJourney() {
  await openRegisterAndVerifyPhone();
  await fillIdentity();
  await chooseKind('independent');
  await chooseTrade();
  await chooseZone();
  await acceptAndSubmit();
}

beforeEach(() => {
  apiMock.reset();
  mockCaptcha.mode = 'skipped';
  Object.keys(memoryStore).forEach(k => delete memoryStore[k]);

  apiMock.onPost('/auth/phone/verify-request').reply(201, { ok: true, phone: PHONE_E164 });
  apiMock.onPost('/auth/phone/verify-confirm').reply(200, {
    ok: true,
    phone_verification_token: 'jeton-telephone-test',
  });
  /*
   * LE CATALOGUE, pas la table `trades`. L'écran lit `/catalog/registration-options`, qui ne rend
   * que les métiers réellement VENDUS quelque part : la liste brute des métiers actifs laissait
   * s'inscrire sur un métier que personne ne peut commander. Un métier sans question réglementaire
   * suffit ici, pour garder ces tests sur leur sujet.
   */
  apiMock.onGet(new RegExp('/catalog/registration-options')).reply(200, {
    ok: true,
    data: {
      sectors: [
        {
          id: 1,
          name: 'Extérieur',
          slug: 'exterieur',
          trades: [
            {
              id: TRADE_ID,
              name: 'Jardinage',
              slug: 'jardinage',
              zone_ids: [ZONE_ID],
              allows_asap: true,
            },
          ],
        },
      ],
      zones: [{ id: ZONE_ID, name: 'Bruxelles', slug: 'bruxelles', code: 'BXL' }],
    },
  });
  apiMock.onGet(`/trades/${TRADE_ID}/provider-fields`).reply(200, { fields: [] });
  apiMock.onPost('/auth/register').reply(201, {
    token: 'tok_123',
    user: { id: 7, name: 'Jean Dupont', email: 'jean@exemple.be', role: 'employe' },
  });
});

describe("Inscription depuis l'app prestataire", () => {
  it("annonce au serveur qu'il faut créer un compte prestataire", async () => {
    renderScreen();
    await completeIndependentJourney();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    expect(JSON.parse(registerCalls()[0]!.data).account_type).toBe('provider');
  });

  it('joint le jeton captcha quand le widget en a produit un', async () => {
    mockCaptcha.mode = 'token';

    renderScreen();
    await completeIndependentJourney();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    expect(registerCalls()[0]!.headers?.['X-Turnstile-Token']).toBe('jeton-turnstile-test');
  });

  it("retient la requête tant que le captcha n'a pas répondu", async () => {
    mockCaptcha.mode = 'pending';

    renderScreen();
    await completeIndependentJourney();

    await waitFor(() => expect(screen.getByTestId('register-form-error')).toBeTruthy());
    expect(screen.getByText(/vérification anti-robot/i)).toBeTruthy();
    // Une requête vouée à un 400 ne doit pas partir.
    expect(registerCalls()).toHaveLength(0);
  });

  it('vérifie le téléphone avant de demander quoi que ce soit d\'autre', async () => {
    renderScreen();
    fireEvent.press(screen.getByText("Pas encore de compte ? S'inscrire"));

    // Le premier écran ne pose que le téléphone : ni identité, ni email, ni mot de passe.
    await waitFor(() => screen.getByLabelText('Téléphone'));
    expect(screen.queryByLabelText('Prénom')).toBeNull();
    expect(screen.queryByLabelText('Email')).toBeNull();
    expect(screen.queryByLabelText('Mot de passe')).toBeNull();
  });

  it('transmet le jeton du téléphone vérifié', async () => {
    renderScreen();
    await completeIndependentJourney();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    const body = JSON.parse(registerCalls()[0]!.data);
    expect(body.phone_verification_token).toBe('jeton-telephone-test');
    // Normalisé en E.164 avant l'envoi : le serveur refuse la forme nationale.
    expect(body.phone).toBe(PHONE_E164);
  });

  it("n'appelle pas le serveur avec un numéro invalide", async () => {
    renderScreen();
    fireEvent.press(screen.getByText("Pas encore de compte ? S'inscrire"));

    await waitFor(() => screen.getByLabelText('Téléphone'));
    fireEvent.changeText(screen.getByLabelText('Téléphone'), '12');
    fireEvent.press(screen.getByLabelText('Recevoir le code'));

    await waitFor(() => expect(screen.getByTestId('register-step-error')).toBeTruthy());
    expect(apiMock.history['post']!.filter(c => c.url === '/auth/phone/verify-request')).toHaveLength(0);
  });

  it('refuse une adresse email sans domaine', async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();

    fireEvent.changeText(screen.getByLabelText('Prénom'), 'Jean');
    fireEvent.changeText(screen.getByLabelText('Nom'), 'Dupont');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => screen.getByLabelText('Email'));
    // `includes('@')`, l'ancien contrôle, acceptait cette saisie.
    fireEvent.changeText(screen.getByLabelText('Email'), 'jean@');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('register-step-error')).toBeTruthy());
    expect(screen.queryByLabelText('Mot de passe')).toBeNull();
  });

  it('refuse un mot de passe trop court', async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();

    fireEvent.changeText(screen.getByLabelText('Prénom'), 'Jean');
    fireEvent.changeText(screen.getByLabelText('Nom'), 'Dupont');
    fireEvent.press(screen.getByLabelText('Continuer'));
    await waitFor(() => screen.getByLabelText('Email'));
    fireEvent.changeText(screen.getByLabelText('Email'), 'jean@exemple.be');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => screen.getByLabelText('Mot de passe'));
    fireEvent.changeText(screen.getByLabelText('Mot de passe'), 'court');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('register-step-error')).toBeTruthy());
    expect(screen.queryByTestId('register-kind-independent')).toBeNull();
  });

  it("ne demande rien sur la société à un indépendant", async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('independent');

    // L'écran suivant est le métier : l'étape société est absente du parcours.
    await waitFor(() => screen.getByTestId(`register-trade-${TRADE_ID}`));
    expect(screen.queryByLabelText('Raison sociale')).toBeNull();

    await chooseTrade();
    await chooseZone();
    await acceptAndSubmit();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    const body = JSON.parse(registerCalls()[0]!.data);
    expect(body.provider_kind).toBe('independent');
    expect(body.company_name).toBeUndefined();
  });

  /**
   * LE MÊME CATALOGUE QUE LE WEB — pressé, pas lu dans le source.
   *
   * L'écran interrogeait `GET /api/trades`, c'est-à-dire la table telle quelle : tous les métiers
   * actifs, y compris ceux qu'aucune zone n'ouvre. Le formulaire web, lui, lisait déjà
   * `registration-options`. Deux listes construites séparément : un métier ouvert par
   * l'administration apparaissait d'un côté et pas de l'autre, et personne ne savait lequel disait
   * vrai. Ce test échoue si l'écran revient à l'ancienne source, parce que la seule route bouchonnée
   * est celle du catalogue.
   */
  it('propose les métiers du catalogue, pas la table des métiers', async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('independent');

    await waitFor(() => screen.getByTestId(`register-trade-${TRADE_ID}`));
    expect(screen.getByText('Jardinage')).toBeTruthy();
  });

  /**
   * SANS ZONE, AUCUNE MISSION — et c'est le dispatch planifié qui le prouve : il travaille sur les
   * zones DÉCLARÉES (`employee_zone_assignments`), pas sur la position du jour. Un inscrit natif
   * n'en avait aucune : il passait la vérification et n'était candidat nulle part.
   */
  it('transmet les zones déclarées au serveur', async () => {
    renderScreen();
    await completeIndependentJourney();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    const body = JSON.parse(registerCalls()[0]!.data);
    expect(body.zone_ids).toEqual([ZONE_ID]);
  });

  it("refuse de continuer tant qu'aucune zone n'est cochée", async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('independent');
    await chooseTrade();

    // On arrive sur les zones et on pousse « Continuer » sans rien cocher.
    await waitFor(() => screen.getByTestId(`register-zone-${ZONE_ID}`));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('register-step-error')).toBeTruthy());
    // Toujours sur l'étape zones : le parcours n'a pas avancé.
    expect(screen.getByTestId(`register-zone-${ZONE_ID}`)).toBeTruthy();
    expect(registerCalls()).toHaveLength(0);
  });

  it("transmet la raison sociale et le numéro d'entreprise quand on choisit société", async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('company');

    await waitFor(() => screen.getByLabelText("Numéro d'entreprise"));
    fireEvent.changeText(screen.getByLabelText("Numéro d'entreprise"), 'BE0202239951');
    fireEvent.changeText(screen.getByLabelText('Raison sociale'), 'Nettoyage Dupont SPRL');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await chooseTrade();
    await chooseZone();
    await acceptAndSubmit();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    const body = JSON.parse(registerCalls()[0]!.data);
    expect(body.provider_kind).toBe('company');
    expect(body.company_name).toBe('Nettoyage Dupont SPRL');
    expect(body.vat_number).toBe('BE0202239951');
  });

  it('refuse une société sans raison sociale sans appeler le serveur', async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('company');

    await waitFor(() => screen.getByLabelText("Numéro d'entreprise"));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('register-step-error')).toBeTruthy());
    // Le serveur refuserait de toute façon (required_if) : inutile de l'appeler.
    expect(registerCalls()).toHaveLength(0);
  });

  /**
   * Contrôle de clé côté client, miroir de `App\Support\Validation\BusinessNumber`. Ce numéro
   * part ensuite aux registres officiels : le signaler pendant la frappe évite un dossier rejeté
   * plusieurs jours plus tard.
   */
  it("refuse un numéro d'entreprise dont la clé est fausse", async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('company');

    await waitFor(() => screen.getByLabelText("Numéro d'entreprise"));
    fireEvent.changeText(screen.getByLabelText("Numéro d'entreprise"), 'BE0000000000');
    fireEvent.changeText(screen.getByLabelText('Raison sociale'), 'Nettoyage Dupont SPRL');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('register-step-error')).toBeTruthy());
    expect(screen.queryByTestId(`register-trade-${TRADE_ID}`)).toBeNull();
  });

  it('transmet le métier déclaré et les réponses à ses questions', async () => {
    // Un métier réglementé : le serveur ajoute ses questions selon ses propres exigences.
    apiMock.onGet(`/trades/${TRADE_ID}/provider-fields`).reply(200, {
      fields: [
        { key: 'experience_years', type: 'number', label: "Années d'expérience", required: true },
        { key: 'certification_reference', type: 'text', label: 'Référence de certification', required: true },
      ],
    });

    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('independent');
    await chooseTrade();
    await chooseZone();

    await waitFor(() => screen.getByLabelText("Années d'expérience *"));
    fireEvent.changeText(screen.getByLabelText("Années d'expérience *"), '7');
    fireEvent.changeText(screen.getByLabelText('Référence de certification *'), 'RGIE-2024-118');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await acceptAndSubmit();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    const body = JSON.parse(registerCalls()[0]!.data);
    expect(body.trade_id).toBe(TRADE_ID);
    expect(body.trade_answers).toEqual({ experience_years: '7', certification_reference: 'RGIE-2024-118' });
  });

  it('refuse de continuer sans métier choisi', async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('independent');

    await waitFor(() => screen.getByTestId(`register-trade-${TRADE_ID}`));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('register-step-error')).toBeTruthy());
    // Sans métier, le prestataire ne recevrait aucune mission : inutile d'appeler le serveur.
    expect(registerCalls()).toHaveLength(0);
  });

  it("n'exige pas d'accepter les conditions en silence", async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('independent');
    await chooseTrade();
    await chooseZone();

    await waitFor(() => screen.getByTestId('register-accept-terms'));
    fireEvent.press(screen.getByLabelText('Créer mon compte'));

    await waitFor(() => expect(screen.getByTestId('register-step-error')).toBeTruthy());
    expect(registerCalls()).toHaveLength(0);
  });

  /**
   * L'effet recherché : le prestataire tape son numéro, sa raison sociale remonte du registre
   * officiel et il confirme au lieu de recopier.
   */
  it("pré-remplit la raison sociale depuis le registre", async () => {
    apiMock.onPost('/auth/company-lookup').reply(200, {
      ok: true,
      found: true,
      company: { legal_name: 'Proximus SA', legal_form: 'SA', address: 'Bruxelles', vat_id: null },
    });

    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('company');

    await waitFor(() => screen.getByLabelText("Numéro d'entreprise"));
    fireEvent.changeText(screen.getByLabelText("Numéro d'entreprise"), 'BE0202239951');

    await waitFor(() => screen.getByLabelText('Retrouver ma société'));
    fireEvent.press(screen.getByLabelText('Retrouver ma société'));

    await waitFor(() => expect(screen.getByTestId('register-company-suggestion')).toBeTruthy());
    expect(screen.getByLabelText('Raison sociale').props.value).toBe('Proximus SA');
  });

  /** Une clé fausse ne doit pas partir vers le registre : le contrôle est local d'abord. */
  it("n'interroge pas le registre avec un numéro invalide", async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();
    await chooseKind('company');

    await waitFor(() => screen.getByLabelText("Numéro d'entreprise"));
    fireEvent.changeText(screen.getByLabelText("Numéro d'entreprise"), 'BE0000000000');

    expect(screen.queryByLabelText('Retrouver ma société')).toBeNull();
    expect(apiMock.history['post']!.filter(c => c.url === '/auth/company-lookup')).toHaveLength(0);
  });

  /**
   * Un parcours en dix écrans se perd à la moindre interruption. Le mot de passe reste hors du
   * brouillon : AsyncStorage n'est pas chiffré.
   */
  it('conserve les réponses localement mais jamais le mot de passe', async () => {
    renderScreen();
    await openRegisterAndVerifyPhone();
    await fillIdentity();

    await waitFor(() => {
      const saved = Object.values(memoryStore).join('');
      expect(saved).toContain('jean@exemple.be');
    });

    const saved = Object.values(memoryStore).join('');
    expect(saved).toContain('Dupont');
    expect(saved).not.toContain('motdepasse123');
  });
});
