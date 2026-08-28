import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { rejoindre } from '@/company/liveKitRoom';
import type { SalleRejointe } from '@/company/liveKitRoom';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

/**
 * L'ÉCRAN D'APPEL — audio d'abord, vidéo en bascule.
 *
 * Sur un chantier, la vidéo consomme une bande passante qu'on n'a pas toujours et une batterie
 * qu'on ne peut pas recharger. Elle sert à MONTRER une fuite, pas à tenir une conversation : d'où
 * l'audio par défaut, et la caméra sur demande.
 *
 * LE JETON SE DEMANDE, IL NE SE REÇOIT PAS. La bannière diffusée sur `channel.{id}` ne porte que
 * l'identifiant de l'appel : diffuser un jeton donnerait à tous les membres le droit d'entrer dans
 * la salle sans avoir décroché. Demander le sien EST l'acte de décrocher, côté serveur.
 */
export function CallScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'Call'>>();

  const callId = route.params?.callId ?? null;
  const avecVideo = route.params?.video ?? false;

  const [salle, setSalle] = useState<SalleRejointe | null>(null);
  const [etat, setEtat] = useState<'connexion' | 'en_cours' | 'indisponible'>('connexion');

  const raccrocher = useMutation({
    mutationFn: async () => apiClient.post(`/provider/company/calls/${callId}/end`),
    onSettled: () => navigation.goBack(),
  });

  useEffect(() => {
    let annule = false;
    let salleLocale: SalleRejointe | null = null;

    async function entrer() {
      try {
        const reponse = await apiClient.post(`/provider/company/calls/${callId}/token`);
        const donnees = reponse.data.data;

        const rejointe = await rejoindre({
          url: donnees.url,
          token: donnees.token,
          video: avecVideo,
        });

        if (annule) {
          await rejointe?.quitter();

          return;
        }

        if (rejointe === null) {
          /*
           * Le natif LiveKit n'est pas dans ce dev-client, ou la connexion a échoué. On le DIT
           * plutôt que de laisser un écran figé sur « connexion » : un appel qui ne se connecte
           * jamais sans explication fait douter de tout le produit.
           */
          setEtat('indisponible');

          return;
        }

        salleLocale = rejointe;
        setSalle(rejointe);
        setEtat('en_cours');
      } catch {
        if (!annule) {
          setEtat('indisponible');
        }
      }
    }

    if (callId !== null) {
      void entrer();
    }

    // Quitter la salle au démontage : elle garde le micro tant qu'on ne la ferme pas.
    return () => {
      annule = true;
      void salleLocale?.quitter();
    };
  }, [callId, avecVideo]);

  if (callId === null) {
    return (
      <Screen>
        <EmptyState title={tr('call.appel_introuvable')} message="Cet appel n'existe plus." />
      </Screen>
    );
  }

  return (
    <Screen>
      <View style={styles.centre} testID="ecran-appel">
        <Text style={styles.etat}>
          {etat === 'connexion' && 'Connexion…'}
          {etat === 'en_cours' && (avecVideo ? tr('call.appel_video_en_cours') : tr('call.appel_en_cours'))}
          {etat === 'indisponible' && 'Appel indisponible sur cet appareil'}
        </Text>

        {etat === 'indisponible' && (
          <Text style={styles.aide}>
            La fonction d'appel demande une version de l'application reconstruite avec le module
            audio. Utilisez une note vocale en attendant.
          </Text>
        )}

        <View style={styles.actions}>
          <Button
            label={tr('call.raccrocher')}
            variant="danger"
            fullWidth
            onPress={() => {
              void salle?.quitter();
              raccrocher.mutate();
            }}
          />
        </View>
      </View>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    centre: {
      flex: 1,
      alignItems: 'center',
      justifyContent: 'center',
      gap: spacing.md,
    },
    etat: {
      fontSize: typography.fontSize.lg,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      textAlign: 'center',
    },
    aide: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      textAlign: 'center',
      paddingHorizontal: spacing.lg,
    },
    actions: {
      width: '100%',
      paddingHorizontal: spacing.lg,
    },
  });
