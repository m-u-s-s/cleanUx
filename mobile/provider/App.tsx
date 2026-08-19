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
setAppAudience('provider');

import { setupForegroundNotifications, useNotificationRouting, useRegisterPushToken } from '@/push';
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
import {
  useFonts,
  Figtree_400Regular,
  Figtree_500Medium,
  Figtree_600SemiBold,
  Figtree_700Bold,
} from '@expo-google-fonts/figtree';
import { SpaceGrotesk_700Bold } from '@expo-google-fonts/space-grotesk';
import { AuthProvider } from '@/auth';
import { RealtimeProvider } from '@/realtime';
import { RootNavigator, linking } from '@/navigation';
import { ErrorBoundary } from '@/ErrorBoundary';
import { WalkthroughScreen, hasCompletedWalkthrough } from '@/screens/WalkthroughScreen';
import '@/sentry/init';
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
  /*
   * SANS CET APPEL, AUCUNE OFFRE NE FAIT VIBRER LE TÉLÉPHONE.
   *
   * Le serveur n'envoie une notification qu'aux appareils enregistrés. L'application cliente
   * montait ce hook depuis le début ; l'application prestataire, non — celle qui en a le plus
   * besoin, puisque c'est elle qui reçoit les courses à vingt secondes d'échéance. Le relevé de
   * poste ne pouvait pas le dire : le canal de repli par sondage cachait le trou en développement,
   * et sur un téléphone verrouillé il n'y a pas de sondage.
   */
  useRegisterPushToken();
  useOfflineSync();
  const themeNavigation = useThemeDeNavigation();
  const [showWalkthrough, setShowWalkthrough] = useState<boolean | null>(null);

  useEffect(() => {
    hasCompletedWalkthrough().then(completed => setShowWalkthrough(!completed));
  }, []);

  if (showWalkthrough === null) {
    return <View style={{ flex: 1 }} />;
  }

  if (showWalkthrough) {
    return (
      <SafeAreaProvider initialMetrics={initialWindowMetrics}>
        <WalkthroughScreen onComplete={() => setShowWalkthrough(false)} />
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
  const [fontsLoaded] = useFonts({
    Figtree_400Regular,
    Figtree_500Medium,
    Figtree_600SemiBold,
    Figtree_700Bold,
    SpaceGrotesk_700Bold,
  });

  if (!fontsLoaded) {
    return <View />;
  }

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <ErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <AuthProvider>
            <RealtimeProvider>
              <AppInner />
            </RealtimeProvider>
          </AuthProvider>
        </QueryClientProvider>
      </ErrorBoundary>
    </GestureHandlerRootView>
  );
}
