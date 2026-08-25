/*
 * L'application se DÉCLARE avant tout le reste.
 *
 * Le serveur refuse un compte prestataire dans l'application cliente et l'inverse ; il ne
 * peut le faire que s'il sait à qui il parle. En l'absence de déclaration il laisse passer,
 * pour ne pas déconnecter le parc déjà installé — un oubli ici serait donc SILENCIEUX.
 *
 * Avant les fournisseurs : la reprise de session part dès le montage d'`AuthProvider`, et
 * elle doit déjà porter l'en-tête.
 */
import { setAppAudience } from '@/api';
setAppAudience('client');

import '@/sentry/init';
import { setupForegroundNotifications } from '@/push';
setupForegroundNotifications();
import React, { useState, useEffect } from 'react';
import { View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider, initialWindowMetrics } from 'react-native-safe-area-context';
import { NightShell, useThemeDeNavigation } from '@/ui/NightShell';
import { OfflineBanner } from '@/ui';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StripeProvider } from '@stripe/stripe-react-native';
import {
  useFonts,
  Figtree_400Regular,
  Figtree_500Medium,
  Figtree_600SemiBold,
  Figtree_700Bold,
} from '@expo-google-fonts/figtree';
import { Allura_400Regular } from '@expo-google-fonts/allura';
import {
  Sora_400Regular,
  Sora_500Medium,
  Sora_600SemiBold,
  Sora_700Bold,
  Sora_800ExtraBold,
} from '@expo-google-fonts/sora';
import { AuthProvider } from '@/auth';
import { RealtimeProvider } from '@/realtime';
import { RootNavigator, linking } from '@/navigation';
import { useRegisterPushToken, useNotificationRouting } from '@/push';
import { env } from '@/config/env';
import { ErrorBoundary } from '@/ErrorBoundary';
import { OnboardingScreen, hasCompletedOnboarding } from '@/screens/OnboardingScreen';
import { useOfflineSync, bindAppStateToQueryFocus } from '@/api';

// Relie React Query au cycle de vie de l'application : sans ce pont, aucune requête n'est
// rejouée au retour au premier plan et l'écran reste figé sur des données périmées.
bindAppStateToQueryFocus();

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 2, staleTime: 60_000 } },
});

/**
 * Hooks that call useNavigation() must run *inside* <NavigationContainer>.
 * AppInner is the component that renders the container, so anything it calls
 * directly executes outside of it — useNotificationRouting() there threw
 * "Couldn't find a navigation object. Is your component inside
 * NavigationContainer?" and took the whole app down with a render error.
 */
function NavigationEffects(): null {
  useNotificationRouting();
  return null;
}

function AppInner() {
  useRegisterPushToken();
  useOfflineSync();
  const themeNavigation = useThemeDeNavigation();
  const [showOnboarding, setShowOnboarding] = useState<boolean | null>(null);

  useEffect(() => {
    hasCompletedOnboarding().then(completed => setShowOnboarding(!completed));
  }, []);

  if (showOnboarding === null) {
    return <View style={{ flex: 1 }} />;
  }

  if (showOnboarding) {
    return (
      <SafeAreaProvider initialMetrics={initialWindowMetrics}>
        <OnboardingScreen onComplete={() => setShowOnboarding(false)} />
        <StatusBar style="light" />
      </SafeAreaProvider>
    );
  }

  return (
    <SafeAreaProvider initialMetrics={initialWindowMetrics}>
      {/*
        `initialMetrics` N'EST PAS UNE OPTIMISATION : C'EST CE QUI EMPECHE UN PREMIER RENDU FAUX.

        VU EN VRAI sur l'emulateur : le titre du tableau de bord prestataire, « Bonjour, B. »,
        rendu PAR-DESSUS l'horloge systeme. L'ecran passe pourtant par `Screen`, qui pose une
        marge haute tiree de `SafeAreaView`. Sonde posee dans le composant : l'encart haut valait
        bien 53 px une fois etabli. Il valait donc zero au moment du rendu fautif.

        La raison tient au montage juste au-dessus. Le drapeau du walkthrough se lit de facon
        ASYNCHRONE ; tant qu'il vaut `null` on rend une `View` nue, SANS fournisseur. Le
        `SafeAreaProvider` ne se monte donc qu'apres cette lecture — et un fournisseur qui vient
        de se monter n'a pas encore recu l'evenement natif qui lui apprend les encarts. Ses
        enfants rendent une premiere passe a zero, puis se recalent. D'ordinaire cela dure une
        image et personne ne le voit ; sous charge — bundle de developpement, carte, requete de
        connexion — la fausse mise en page tient assez longtemps pour etre vue, et la carte, qui
        mesure une fois, garde la mauvaise hauteur.

        `initialWindowMetrics` est lu SYNCHRONEMENT depuis les constantes du module natif, au
        demarrage du JS. Le fournisseur connait donc les encarts des sa toute premiere passe : il
        n'existe plus d'image a zero, quel que soit le moment ou il se monte.

        C'est aussi pour cela que le correctif est ici et non dans `Screen` : forcer une marge
        minimale la-bas aurait masque le symptome sur un ecran en laissant les autres exposes, et
        aurait fait double marge le jour ou l'encart arrive.
      */}
      <NightShell>
        <NavigationContainer linking={linking} theme={themeNavigation}>
          <NavigationEffects />
          <RootNavigator />
        </NavigationContainer>
      </NightShell>
      {/*
        « PAS DE CONNEXION INTERNET » — le bandeau existait, complet et anime, monte NULLE PART.

        Sans lui, une personne dans une cave voit ses gestes ne rien faire et recommence : elle
        n'a aucun moyen de distinguer une application en panne d'un reseau absent. Au-dessus de
        la navigation, parce qu'il doit se voir quel que soit l'ecran ouvert.
      */}
      <OfflineBanner />
      <StatusBar style="auto" />
    </SafeAreaProvider>
  );
}

export default function App() {
  /*
   * ALLURA PORTE LES TITRES, SORA L'INTERFACE — les memes que le web.
   *
   * Figtree reste chargee : des ecrans la nomment encore directement, et la retirer
   * les ferait tomber sur la police systeme sans que rien ne le signale.
   */
  const [fontsLoaded] = useFonts({
    Allura_400Regular,
    Sora_400Regular,
    Sora_500Medium,
    Sora_600SemiBold,
    Sora_700Bold,
    Sora_800ExtraBold,
    Figtree_400Regular,
    Figtree_500Medium,
    Figtree_600SemiBold,
    Figtree_700Bold,
  });

  if (!fontsLoaded) {
    return <View />;
  }

  return (
    // react-native-gesture-handler exige que TOUTE l'application descende de cette vue, sinon
    // ses détecteurs de geste lèvent au montage : « GestureDetector must be used as a descendant
    // of GestureHandlerRootView ». L'application prestataire l'a depuis toujours ; celle-ci ne
    // l'avait pas, faute d'écran gestuel — la feuille d'actions de l'accueil en introduit un.
    <GestureHandlerRootView style={{ flex: 1 }}>
      <ErrorBoundary>
        <StripeProvider publishableKey={env.stripePublishableKey} merchantIdentifier="merchant.com.brio.client">
          <QueryClientProvider client={queryClient}>
            <AuthProvider>
              <RealtimeProvider>
                <AppInner />
              </RealtimeProvider>
            </AuthProvider>
          </QueryClientProvider>
        </StripeProvider>
      </ErrorBoundary>
    </GestureHandlerRootView>
  );
}
