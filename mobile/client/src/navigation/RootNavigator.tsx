import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { apparenceDEnTete } from '@/ui';
import { useAuth } from '@/auth';
import { useTraduction } from '@/i18n';
import { EcranConfirmationEmail } from '@/auth/EcranConfirmationEmail';
import { LoginScreen } from '@/screens/LoginScreen';
import { OnboardingScreen, hasCompletedOnboarding } from '@/screens/OnboardingScreen';
import { TabNavigator } from './TabNavigator';
import { MissionTrackingScreen } from '@/screens/MissionTrackingScreen';
import { OnSiteScreen } from '@/screens/OnSiteScreen';
import { BookingDetailScreen } from '@/screens/BookingDetailScreen';
import { QRScanScreen } from '@/screens/QRScanScreen';
import { PaymentCheckoutScreen } from '@/screens/PaymentCheckoutScreen';
import { SavedPaymentMethodsScreen } from '@/screens/SavedPaymentMethodsScreen';
import { ChatScreen } from '@/screens/ChatScreen';
import { ChatListScreen } from '@/screens/ChatListScreen';
import { NotificationsScreen } from '@/screens/NotificationsScreen';
import { NotificationDetailScreen } from '@/screens/NotificationDetailScreen';
// Sprint 9
import { RatingScreen } from '@/screens/RatingScreen';
import { LoyaltyScreen } from '@/screens/LoyaltyScreen';
import { PlacesScreen } from '@/screens/PlacesScreen';
import { BudgetScreen } from '@/screens/BudgetScreen';
import { ProtectionScreen } from '@/screens/ProtectionScreen';
import { ReceivedQuotesScreen } from '@/screens/ReceivedQuotesScreen';
import { ReferralScreen } from '@/screens/ReferralScreen';
import { AiQuoteScreen } from '@/screens/AiQuoteScreen';
// Sprint 10
import { DisputesScreen } from '@/screens/DisputesScreen';
import { GDPRScreen } from '@/screens/GDPRScreen';
import { ProfileEditScreen } from '@/screens/ProfileEditScreen';
import { TipsScreen } from '@/screens/TipsScreen';
import { NPSScreen } from '@/screens/NPSScreen';
import { ForgotPasswordScreen } from '@/screens/ForgotPasswordScreen';
import { LegalScreen } from '@/screens/LegalScreen';
// Polish — UX screens
import { NotificationPreferencesScreen } from '@/screens/NotificationPreferencesScreen';
import { LanguageScreen } from '@/screens/LanguageScreen';
import { AppearanceScreen } from '@/screens/AppearanceScreen';
// Invoices
import { InvoicesScreen } from '@/screens/InvoicesScreen';
import { InvoiceDetailScreen } from '@/screens/InvoiceDetailScreen';
// Embedded web modules
import { EmbeddedModuleRoute } from '@/screens/EmbeddedModuleRoute';
// Espace société cliente — écrans natifs, servis par l'API /client/company/*.
import { CompanyOverviewScreen } from '@/screens/company/CompanyOverviewScreen';
import { CompanySitesScreen } from '@/screens/company/CompanySitesScreen';
import { CompanyBookingsScreen } from '@/screens/company/CompanyBookingsScreen';
import { CompanyMembersScreen } from '@/screens/company/CompanyMembersScreen';
import { CompanyContractsScreen } from '@/screens/company/CompanyContractsScreen';
import { CompanyBillingScreen } from '@/screens/company/CompanyBillingScreen';
import { CompanyGovernanceScreen } from '@/screens/company/CompanyGovernanceScreen';
// L'espace société cliente : aiguillage, préférence retenue, sélecteur et navigateur dédié.
import { resolveClientSpace } from '@/company/space';
import { useClientSpacePreference } from '@/company/useClientSpacePreference';
import { ClientCompanyNavigator } from '@/company/ClientCompanyNavigator';
import { ClientSpaceSwitcherScreen } from '@/screens/company/ClientSpaceSwitcherScreen';
import { ModulesRoute } from '@/screens/ModulesRoute';
import { BrowseProvidersScreen } from '@/screens/BrowseProvidersScreen';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { RootStackParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function RootNavigator() {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const { user, isAuthenticated, isLoading } = useAuth();

  // L'espace retenu d'un compte à double vie. Tant qu'il se lit, on n'ouvre rien : ouvrir l'espace
  // par défaut le temps d'une lecture asynchrone ferait clignoter un écran qui n'est pas le sien.
  const { space: chosenSpace, isLoading: spaceLoading, choose } = useClientSpacePreference();

  /*
   * LA PRÉSENTATION DE L'APPLICATION, MONTRÉE UNE FOIS.
   *
   * `OnboardingScreen` et son témoin `hasCompletedOnboarding()` existaient depuis toujours, sans
   * qu'aucun navigateur ne les monte : personne n'a jamais vu ce carrousel. Le prestataire avait
   * exactement le même trou, réparé de cette façon.
   *
   * `undefined` = on ne sait pas encore, et l'on n'affiche RIEN plutôt que de faire clignoter
   * l'application puis le carrousel. Le témoin rend déjà `true` si SecureStore est indisponible :
   * en cas de doute, on ne bloque pas l'accès.
   */
  const [presentationVue, setPresentationVue] = React.useState<boolean | undefined>(undefined);

  React.useEffect(() => {
    let vivant = true;

    hasCompletedOnboarding()
      .then((vue) => { if (vivant) setPresentationVue(vue); })
      .catch(() => { if (vivant) setPresentationVue(true); });

    return () => { vivant = false; };
  }, []);

  const space = resolveClientSpace({
    isLoading: isLoading || spaceLoading,
    isAuthenticated,
    user,
    chosenSpace,
  });

  if (presentationVue === false) {
    return <OnboardingScreen onComplete={() => setPresentationVue(true)} />;
  }

  if (space === 'loading' || presentationVue === undefined) {
    return (
      <View testID="root-navigator" style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: theme.page }}>
        <ActivityIndicator size="large" color={colors.brand[500]} />
      </View>
    );
  }

  /*
   * LE MUR DE CONFIRMATION, AVANT TOUT LE RESTE.
   *
   * Le sélecteur d'espace n'a rien à proposer à qui ne peut ouvrir aucun écran : le serveur
   * refuse 530 de ses 537 routes authentifiées tant que l'adresse n'est pas confirmée.
   */
  if (space === 'emailNonConfirme') {
    return (
      <View testID="root-navigator" style={{ flex: 1 }}>
        <EcranConfirmationEmail />
      </View>
    );
  }

  if (space === 'switcher') {
    return (
      <View testID="root-navigator" style={{ flex: 1 }}>
        <ClientSpaceSwitcherScreen onChoose={(next) => void choose(next)} />
      </View>
    );
  }

  /*
   * L'ESPACE SOCIÉTÉ CLIENTE, rendu HORS de la pile personnelle.
   *
   * Aucun écran de la pile perso ne concerne un responsable de sites au travail — parrainage,
   * fidélité, pourboires, NPS. Les y laisser atteignables donnerait un espace « société » qui
   * ressemble au compte personnel avec quatre écrans de plus.
   *
   * Les écrans PARTAGÉS restent montés ici : le détail d'une réservation et celui d'une facture
   * sont les mêmes objets, et en écrire une seconde version pour la société dupliquerait le suivi,
   * les photos, le litige et le PDF signé.
   */
  if (space === 'clientCompany') {
    return (
      <View testID="root-navigator" style={{ flex: 1 }}>
        <Stack.Navigator screenOptions={{ headerShown: false, ...apparenceDEnTete() }}>
          <Stack.Screen name="ClientCompanySpace" component={ClientCompanyNavigator} />
          <Stack.Screen
            name="Modules"
            component={ModulesRoute}
            options={{ headerShown: true, title: tr('nav.modules') }}
          />
          <Stack.Screen
            name="CompanyMembers"
            component={CompanyMembersScreen}
            options={{ headerShown: true, title: tr('nav.membres') }}
          />
          <Stack.Screen
            name="CompanyContracts"
            component={CompanyContractsScreen}
            options={{ headerShown: true, title: tr('nav.contrats') }}
          />
          <Stack.Screen
            name="BookingDetail"
            component={BookingDetailScreen}
            options={{ headerShown: true, title: tr('nav.detail_reservation') }}
          />
          <Stack.Screen
            name="InvoiceDetail"
            component={InvoiceDetailScreen}
            options={{ headerShown: true, title: tr('nav.facture') }}
          />

          {/*
            CE QUE `BookingDetail` ET `Modules` VISENT DÉJÀ, DEPUIS CETTE PILE.

            Les deux écrans étaient montés ici — le premier exprès, comme écran PARTAGÉ —
            mais AUCUNE de leurs destinations ne l'était. Le bouton « Payer » s'affichait
            pour une réservation en attente et ne payait pas ; le répertoire de modules
            n'ouvrait aucun module. Rien ne plantait : ce fichier le dit lui-même, une route
            absente ne lève rien, elle ne fait simplement rien.

            Ces routes n'élargissent pas l'espace société. Aucune n'est atteignable depuis la
            barre : leur seule porte est le détail d'une réservation, déjà monté ici. La
            règle du haut — ni parrainage ni fidélité dans cet espace — reste entière.
          */}
          <Stack.Screen
            name="EmbeddedModule"
            component={EmbeddedModuleRoute}
            options={{ headerShown: true }}
          />
          <Stack.Screen
            name="PaymentCheckout"
            component={PaymentCheckoutScreen}
            options={{ headerShown: true, title: tr('nav.paiement') }}
          />
          <Stack.Screen
            name="MissionTracking"
            component={MissionTrackingScreen}
            options={{ headerShown: true, title: tr('nav.suivi') }}
          />
          <Stack.Screen
            name="QRScan"
            component={QRScanScreen}
            options={{ headerShown: true, title: tr('nav.code_de_presence') }}
          />
          <Stack.Screen
            name="Rating"
            component={RatingScreen}
            options={{ headerShown: true, title: tr('nav.votre_avis') }}
          />
          <Stack.Screen
            name="Tips"
            component={TipsScreen}
            options={{ headerShown: true, title: tr('nav.pourboire') }}
          />
          <Stack.Screen
            name="CompanyGovernance"
            component={CompanyGovernanceScreen}
            options={{ headerShown: true, title: tr('nav.pilotage') }}
          />
          {/*
            L'ISSUE VERS L'ESPACE PERSONNEL EST UN ONGLET, PAS UNE ROUTE DÉCLARÉE ICI.

            Elle a d'abord été écrite ainsi — `<Stack.Screen name="Profile" ...>` — avec le
            commentaire qui convenait sur l'enfermement. Elle n'a jamais fonctionné : aucun
            `navigate('Profile')` n'existait dans cette application et la barre d'onglets n'en
            parlait pas, si bien que la route restait montée et joignable par personne.

            Elle aurait de toute façon mal fini si on l'avait atteinte : `ProfileScreen` ouvre le
            parrainage, la fidélité, les moyens de paiement — aucune de ces routes n'est montée sur
            cette pile. Voir `CompanyProfileScreen`, qui porte la sortie depuis la barre.
          */}
          <Stack.Screen
            name="Legal"
            component={LegalScreen}
            options={({ route }) => ({
              title: route.params.type === 'terms' ? 'CGU' : tr('root_navigator.confidentialite'),
              headerShown: true,
            })}
          />
        </Stack.Navigator>
      </View>
    );
  }

  return (
    <View testID="root-navigator" style={{ flex: 1 }}>
      <Stack.Navigator screenOptions={{ headerShown: false, ...apparenceDEnTete() }}>
        {isAuthenticated ? (
          <>
            <Stack.Screen name="MainTabs" component={TabNavigator} />
            <Stack.Screen
              name="Modules"
              component={ModulesRoute}
              options={{ headerShown: true, title: tr('nav.modules') }}
            />
            {/*
              L'assistant de réservation en cinq étapes N'EST PLUS MONTÉ.
              La réservation passe par le moteur de commande, servi en vue embarquée.

              Le laisser déclaré sans qu'aucun écran n'y mène produirait une route orpheline :
              joignable par un `navigate('BookingWizard')` oublié quelque part, invisible dans les
              tests, et écrivant `bookings` sans secteur, sans question propre au métier et sans
              instantané de réponse — donc un devis inexplicable, selon la porte empruntée.

              `BookingNavigator` et ses écrans restent sur le disque : leurs tests les montent
              directement et continuent de passer. C'est du code mort, à supprimer sciemment
              plutôt qu'au détour de ce changement.
            */}
            <Stack.Screen
              name="MissionTracking"
              component={MissionTrackingScreen}
              options={{ title: tr('nav.suivi_mission') }}
            />
            {/*
              Le suivi CONTINUE après la sonnette. La carte s'arrête à l'arrivée ; cet écran
              couvre les deux heures suivantes, celles où le client se demande ce qui se passe
              chez lui.
            */}
            <Stack.Screen
              name="OnSite"
              component={OnSiteScreen}
              options={{ title: tr('nav.intervention_en_cours') }}
            />
            <Stack.Screen
              name="BookingDetail"
              component={BookingDetailScreen}
              options={{ title: tr('nav.detail_reservation') }}
            />
            <Stack.Screen
              name="QRScan"
              component={QRScanScreen}
              options={{ headerShown: false, presentation: 'fullScreenModal' }}
            />
            <Stack.Screen
              name="PaymentCheckout"
              component={PaymentCheckoutScreen}
              options={{ title: tr('nav.paiement'), headerShown: true }}
            />
            <Stack.Screen
              name="SavedPaymentMethods"
              component={SavedPaymentMethodsScreen}
              options={{ title: tr('nav.moyens_de_paiement'), headerShown: true }}
            />
            <Stack.Screen
              name="ChatList"
              component={ChatListScreen}
              options={{ title: tr('nav.messagerie'), headerShown: true }}
            />
            {/* La porte de repli d'« Explorer », qui a quitte la barre d'onglets. */}
            <Stack.Screen
              name="Explore"
              component={BrowseProvidersScreen}
              options={{ title: tr('profile.explorer_les_prestataires'), headerShown: true }}
            />
            <Stack.Screen
              name="Chat"
              component={ChatScreen}
              options={({ route }) => ({ title: route.params.title, headerShown: true })}
            />
            <Stack.Screen
              name="Notifications"
              component={NotificationsScreen}
              options={{ title: tr('nav.notifications'), headerShown: true }}
            />
            <Stack.Screen
              name="NotificationDetail"
              component={NotificationDetailScreen}
              options={{ title: tr('nav.notification'), headerShown: true }}
            />
            {/* Sprint 9 */}
            <Stack.Screen
              name="Rating"
              component={RatingScreen}
              options={{ title: tr('nav.evaluer'), headerShown: true }}
            />
            <Stack.Screen
              name="Loyalty"
              component={LoyaltyScreen}
              options={{ title: tr('nav.fidelite'), headerShown: true }}
            />
            <Stack.Screen
              name="ReceivedQuotes"
              component={ReceivedQuotesScreen}
              options={{ title: tr('nav.devis_recus'), headerShown: true }}
            />
            <Stack.Screen
              name="Places"
              component={PlacesScreen}
              options={{ title: tr('nav.mes_lieux'), headerShown: true }}
            />
            <Stack.Screen
              name="Budget"
              component={BudgetScreen}
              options={{ title: tr('nav.mon_budget'), headerShown: true }}
            />
            <Stack.Screen
              name="Protection"
              component={ProtectionScreen}
              options={{ title: tr('nav.ma_protection'), headerShown: true }}
            />
            <Stack.Screen
              name="Referral"
              component={ReferralScreen}
              options={{ title: tr('nav.parrainage'), headerShown: true }}
            />
            <Stack.Screen
              name="AiQuote"
              component={AiQuoteScreen}
              options={{ title: tr('nav.devis_ia'), headerShown: true }}
            />
            {/* Sprint 10 */}
            <Stack.Screen
              name="Disputes"
              component={DisputesScreen}
              options={{ title: tr('nav.litiges'), headerShown: true }}
            />
            <Stack.Screen
              name="GDPR"
              component={GDPRScreen}
              options={{ title: tr('nav.mes_donnees'), headerShown: true }}
            />
            <Stack.Screen
              name="ProfileEdit"
              component={ProfileEditScreen}
              options={{ title: tr('nav.modifier_le_profil'), headerShown: true }}
            />
            <Stack.Screen
              name="Tips"
              component={TipsScreen}
              options={{ title: tr('nav.pourboire'), headerShown: true }}
            />
            <Stack.Screen
              name="NPS"
              component={NPSScreen}
              options={{ title: tr('nav.votre_avis'), headerShown: true }}
            />
            <Stack.Screen
              name="Legal"
              component={LegalScreen}
              options={({ route }) => ({
                title: route.params.type === 'terms' ? "CGU" : tr('root_navigator.confidentialite'),
                headerShown: true,
              })}
            />
            {/* Polish — UX screens */}
            <Stack.Screen
              name="NotificationPreferences"
              component={NotificationPreferencesScreen}
              options={{ title: tr('nav.preferences_notifications'), headerShown: true }}
            />
            <Stack.Screen
              name="Language"
              component={LanguageScreen}
              options={{ title: tr('nav.langue'), headerShown: true }}
            />
            <Stack.Screen
              name="Appearance"
              component={AppearanceScreen}
              options={{ title: tr('nav.apparence'), headerShown: true }}
            />
            {/* Invoices */}
            <Stack.Screen name="Invoices" component={InvoicesScreen} options={{ headerShown: true, title: tr('nav.factures') }} />
            <Stack.Screen name="InvoiceDetail" component={InvoiceDetailScreen} options={{ headerShown: true, title: tr('nav.facture') }} />
            {/* Embedded web modules */}
            <Stack.Screen
              name="EmbeddedModule"
              component={EmbeddedModuleRoute}
              options={{ headerShown: true }}
            />
            {/*
              Espace société cliente — écrans natifs. Six modules `entreprise-client` étaient
              déclarés dans `config/parity.php` sans qu'aucun soit joignable depuis l'application.
              La porte d'entrée vit dans le profil, conditionnée à l'appartenance à une société
              cliente : voir `ProfileScreen`.
            */}
          </>
        ) : (
          <>
            <Stack.Screen name="Login" component={LoginScreen} />
            <Stack.Screen name="ForgotPassword" component={ForgotPasswordScreen} options={{ title: tr('nav.mot_de_passe_oublie'), headerShown: true }} />
            <Stack.Screen name="Legal" component={LegalScreen} options={({ route }) => ({ title: route.params.type === 'terms' ? "CGU" : tr('root_navigator.confidentialite'), headerShown: true })} />
          </>
        )}
              {/*
          LES SEPT ROUTES `Company*` ONT ETE RETIREES D'ICI.

          Elles etaient montees sur la pile PERSONNELLE, ou `resolveClientSpace` ne place
          jamais un membre de societe : quatre n'etaient visees par personne, et les trois
          autres ne le sont que depuis `CompanyOverviewScreen`, qui ne s'affiche que dans
          l'espace societe — ou elles sont desormais montees.

          Une route montee et joignable par personne est le contraire d'un filet : elle fait
          croire que le chemin existe.
        */}
        </Stack.Navigator>
    </View>
  );
}
