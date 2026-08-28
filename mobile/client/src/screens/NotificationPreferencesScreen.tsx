import React, { useMemo, useState } from 'react';
import { View, Text, Switch, StyleSheet, Alert, ActivityIndicator } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Divider } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/**
 * Préférences de notification : la matrice canal × catégorie.
 *
 * Cet écran affichait une fiction. Il inventait ses propres catégories (`chat`, `system`, qui
 * n'existent nulle part côté serveur), initialisait TOUT à « activé » sans jamais lire les
 * préférences réelles — un utilisateur ayant coupé le marketing les revoyait cochées — et
 * sauvegardait sur `/notifications/preferences`, une adresse sans route : la requête partait en
 * 404 et l'écran annonçait pourtant « Vos préférences ont été mises à jour ».
 *
 * Tout vient désormais du serveur : les canaux, les catégories, l'état courant, et la liste des
 * combinaisons légalement obligatoires. Le vocabulaire n'est plus deviné.
 */

const CHANNEL_LABELS: Record<string, string> = {
  email: 'Email',
  sms: 'SMS',
  push: 'Push',
  inapp: "Dans l'application",
  webhook: 'Webhook',
};

// Hors composant : les deux textes portent leur CLE, l'ecran traduit au rendu.
const CATEGORY_LABELS: Record<string, { libelleCle: string; descriptionCle: string }> = {
  transactional: { libelleCle: 'notification_preferences.reservations_missions', descriptionCle: 'notification_preferences.confirmations_statuts_factures' },
  verification: { libelleCle: 'notification_preferences.verifications', descriptionCle: 'notification_preferences.codes_de_securite' },
  reminder: { libelleCle: 'notification_preferences.rappels', descriptionCle: 'notification_preferences.missions_a_venir' },
  marketing: { libelleCle: 'notification_preferences.offres', descriptionCle: 'notification_preferences.promotions_et_reductions' },
  support: { libelleCle: 'notification_preferences.support', descriptionCle: 'notification_preferences.reponses_a_vos_demandes' },
  security: { libelleCle: 'notification_preferences.securite', descriptionCle: 'notification_preferences.connexions_changements_mot_de_passe' },
  product: { libelleCle: 'notification_preferences.nouveautes', descriptionCle: 'notification_preferences.evolutions_de_lapplication' },
};

interface PreferencesPayload {
  channels: string[];
  categories: string[];
  forced_on: { channel: string; category: string }[];
  /** Matrice indexée [canal][catégorie], telle que la rend le serveur. */
  preferences: Record<string, Record<string, boolean>>;
}

export function NotificationPreferencesScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const queryClient = useQueryClient();
  const [edits, setEdits] = useState<Record<string, boolean>>({});

  const { data, isLoading, isError, refetch } = useQuery<PreferencesPayload>({
    queryKey: ['notification-preferences'],
    queryFn: async () => (await apiClient.get('/client/notifications/preferences')).data,
  });

  /** Les combinaisons imposées par la loi ou la sécurité ne se désactivent pas. */
  const forced = useMemo(
    () => new Set((data?.forced_on ?? []).map(f => `${f.channel}:${f.category}`)),
    [data?.forced_on],
  );

  const valueFor = (channel: string, category: string): boolean => {
    const key = `${channel}:${category}`;
    if (forced.has(key)) return true;
    if (key in edits) return edits[key]!;

    return data?.preferences?.[channel]?.[category] ?? true;
  };

  const save = useMutation({
    mutationFn: async () => {
      // Le serveur attend une LISTE plate de {channel, category, is_allowed}, pas une matrice.
      // Les combinaisons forcées sont exclues : il les refuse de toute façon, les envoyer ne
      // ferait qu'ajouter du bruit à la piste d'audit.
      const preferences = Object.entries(edits)
        .filter(([key]) => !forced.has(key))
        .map(([key, isAllowed]) => {
          const [channel, category] = key.split(':');

          return { channel, category, is_allowed: isAllowed };
        });

      if (preferences.length === 0) return;

      await apiClient.put('/client/notifications/preferences/bulk', { preferences });
    },
    onSuccess: async () => {
      setEdits({});
      await queryClient.invalidateQueries({ queryKey: ['notification-preferences'] });
      Alert.alert(tr('notification_preferences.enregistre'), tr('notification_preferences.vos_preferences_ont_ete_mises'));
    },
    onError: () => Alert.alert(tr('notification_preferences.erreur'), tr('notification_preferences.impossible_d_enregistrer_vos_preferences')),
  });

  if (isLoading) {
    return (
      <Screen>
        <View style={styles.centered} testID="preferences-loading">
          <ActivityIndicator color={colors.brand[500]} />
        </View>
      </Screen>
    );
  }

  // Une panne de chargement ne doit pas laisser un écran vide : elle ne doit surtout pas non plus
  // afficher une matrice inventée que l'utilisateur croirait être la sienne.
  if (isError || !data) {
    return (
      <Screen>
        <View style={styles.centered} testID="preferences-error">
          <Text style={styles.catDesc}>{tr('notification_preferences.impossible_de_charger_vos_preferences')}</Text>
          <Button label={tr('notification_preferences.reessayer')} onPress={() => refetch()} variant="secondary" />
        </View>
      </Screen>
    );
  }

  const hasChanges = Object.keys(edits).length > 0;

  return (
    <Screen scroll>
      <Text style={styles.title}>{tr('notification_preferences.preferences_de_notifications')}</Text>

      {data.categories.map(category => {
        const meta = CATEGORY_LABELS[category];

        return (
          <View key={category} style={styles.category} testID={`preference-category-${category}`}>
            {/* Une catégorie ajoutée côté serveur reste lisible : son code sert de libellé de
                repli plutôt que d'être omise de l'écran. */}
            <Text style={styles.catLabel}>{meta ? tr(meta.libelleCle) : category}</Text>
            {meta ? <Text style={styles.catDesc}>{tr(meta.descriptionCle)}</Text> : null}

            <View style={styles.channels}>
              {data.channels.map(channel => {
                const key = `${channel}:${category}`;
                const isForced = forced.has(key);

                return (
                  <View key={channel} style={styles.channelRow}>
                    <Text style={styles.channelLabel}>
                      {CHANNEL_LABELS[channel] ?? channel}
                      {isForced ? ' — obligatoire' : ''}
                    </Text>
                    <Switch
                      value={valueFor(channel, category)}
                      disabled={isForced}
                      onValueChange={v => setEdits(prev => ({ ...prev, [key]: v }))}
                      trackColor={{ true: colors.brand[500] }}
                      accessibilityLabel={`${CATEGORY_LABELS[category] ? tr(CATEGORY_LABELS[category].libelleCle) : category} par ${CHANNEL_LABELS[channel] ?? channel}`}
                      testID={`preference-switch-${channel}-${category}`}
                    />
                  </View>
                );
              })}
            </View>
            <Divider />
          </View>
        );
      })}

      <Button
        label={hasChanges ? 'Enregistrer' : 'Aucune modification'}
        onPress={() => save.mutate()}
        disabled={!hasChanges}
        fullWidth
        loading={save.isPending}
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.lg,
    marginTop: spacing.md,
  },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.md, padding: spacing.lg },
  category: { marginBottom: spacing.sm },
  catLabel: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  catDesc: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginBottom: spacing.sm,
  },
  channels: { gap: spacing.xs },
  channelRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.xs,
  },
  channelLabel: { fontSize: typography.fontSize.sm, color: t.text },
});
