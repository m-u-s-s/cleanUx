import React, { useState } from 'react';
import { View, Text, TextInput, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Alerte {
  id: number;
  level: 'check_in' | 'emergency';
  status: string;
  acknowledged_at: string | null;
  created_at: string | null;
  contact_notified: boolean;
}

/**
 * LE MODE SÉCURITÉ / SOS (E33).
 *
 * CET ÉCRAN EST LE SEUL DE TOUTE L'APPLICATION dont l'indisponibilité se compte en intégrité
 * physique et non en chiffre d'affaires. Quelqu'un seul chez un inconnu appuie sur un bouton : ce
 * qui compte est que la ligne s'écrive, tout de suite, et que quelqu'un la voie.
 *
 * AUCUNE CONFIRMATION AVANT DE DÉCLENCHER. Une boîte de dialogue « êtes-vous sûr ? » ajoute un geste
 * au moment où les mains tremblent, et une alerte de trop coûte une vérification là où une alerte
 * manquante coûte autre chose. C'est le seul bouton de ce dépôt qui agit au premier appui.
 *
 * DEUX NIVEAUX, PAS SIX. « Gardez un œil » et « venez ». En inventer davantage ferait hésiter au
 * moment de choisir, c'est-à-dire au pire moment.
 *
 * ET L'ACCUSÉ DE RÉCEPTION EST MIS EN AVANT. Savoir que quelqu'un a VU l'alerte est ce que la
 * personne sur place attend en premier — plus que la résolution. Savoir qu'on est seul est ce qui
 * rend une situation effrayante.
 */
export function SafetyScreen() {
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const [message, setMessage] = useState('');

  const { data: alerte } = useQuery<Alerte | null>({
    queryKey: ['provider', 'safety'],
    queryFn: async () => (await apiClient.get('/provider/safety/current')).data.data ?? null,
    // L'accusé de réception est ce qu'on attend : on le regarde souvent.
    refetchInterval: 15000,
  });

  const declencher = useMutation({
    mutationFn: async (niveau: 'check_in' | 'emergency') =>
      apiClient.post('/provider/safety/alerts', {
        level: niveau,
        message: message.trim() || null,
      }),
    onSuccess: () => {
      setMessage('');
      qc.invalidateQueries({ queryKey: ['provider', 'safety'] });
    },
    onError: () =>
      Alert.alert(
        'Alerte non transmise',
        'Appelez directement les secours si vous êtes en danger.',
      ),
  });

  const fermer = useMutation({
    mutationFn: async (id: number) => apiClient.post(`/provider/safety/alerts/${id}/close`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['provider', 'safety'] }),
  });

  return (
    <Screen>
      <Text style={styles.title}>Sécurité</Text>
      <Text style={styles.intro}>
        Si quelque chose ne va pas, dites-le. Nous saurons où vous êtes.
      </Text>

      {alerte ? (
        <View style={styles.encours} testID="alerte-en-cours">
          <Badge
            label={alerte.level === 'emergency' ? 'Urgence en cours' : 'Veille en cours'}
            variant={alerte.level === 'emergency' ? 'danger' : 'warning'}
          />

          <Text style={styles.etat}>
            {alerte.acknowledged_at
              ? // Ce que la personne sur place attend de savoir en premier.
                'Un membre de l’équipe sécurité suit votre situation.'
              : 'Alerte transmise. Nous cherchons quelqu’un pour la prendre en charge.'}
          </Text>

          {alerte.contact_notified && (
            <Text style={styles.detail}>Votre contact d’urgence a été prévenu.</Text>
          )}

          <Button
            label="Tout va bien, fermer l’alerte"
            size="sm"
            fullWidth
            variant="ghost"
            onPress={() => fermer.mutate(alerte.id)}
            testID="fermer-alerte"
          />
        </View>
      ) : (
        <View style={styles.actions}>
          <TextInput
            value={message}
            onChangeText={setMessage}
            placeholder="Que se passe-t-il ? (facultatif)"
            placeholderTextColor={styles.placeholder.color}
            style={styles.champ}
            testID="champ-message-sos"
          />

          {/*
            AUCUNE CONFIRMATION. Une boîte de dialogue « êtes-vous sûr ? » ajoute un geste au
            moment où les mains tremblent.
          */}
          <Button
            label="URGENCE — envoyer l’alerte"
            variant="danger"
            fullWidth
            disabled={declencher.isPending}
            onPress={() => declencher.mutate('emergency')}
            testID="bouton-urgence"
          />

          <Button
            label="Je ne suis pas à l’aise — gardez un œil"
            variant="secondary"
            fullWidth
            disabled={declencher.isPending}
            onPress={() => declencher.mutate('check_in')}
            testID="bouton-veille"
          />

          <Text style={styles.note}>
            En cas de danger immédiat, appelez d’abord les secours. Cette alerte prévient l’équipe
            de la plateforme et, en cas d’urgence, votre contact d’urgence.
          </Text>
        </View>
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
    },
    intro: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginBottom: spacing.md,
    },
    actions: { gap: spacing.sm },
    encours: { gap: spacing.sm, alignItems: 'flex-start' },
    etat: { fontSize: typography.fontSize.base, color: t.text },
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted },
    champ: {
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
    },
    placeholder: { color: t.textMuted },
    note: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: spacing.xs },
  });
