import React from 'react';
import { Text, StyleSheet, Alert, Linking, TouchableOpacity } from 'react-native';
import { useLigneMasqueeClient, useLigneMasqueePrestataire } from '../cancellation/appelMasque';
import { spacing, typography, radius } from '../theme';
import { useThemeColors } from '../theme/useThemeColors';
import type { ThemeTokens } from '../theme/useThemeColors';
import { useTraduction } from '@/i18n';

/**
 * APPELER — par le numéro relais, jamais par celui de l'autre.
 *
 * ── POURQUOI IL NE DISPARAÎT PAS QUAND LA LIGNE EST FERMÉE ───────────────────────────────────
 *
 * Il reste visible et DIT pourquoi il ne peut pas servir : « aucun prestataire n'est encore
 * assigné », « la ligne masquée n'est pas active sur cet environnement ». Un bouton qui s'évapore
 * fait chercher, puis appeler le support — le message, lui, se comprend tout seul.
 *
 * ── ET IL GARDE LE CONTACT SUR LA PLATEFORME ─────────────────────────────────────────────────
 *
 * C'est un service rendu aux deux personnes, et c'est accessoirement la meilleure garde contre
 * l'arrangement hors plateforme : on ne propose pas de régler en liquide à quelqu'un dont on n'a
 * pas le numéro.
 */
export function BoutonAppelMasque({
  role,
  bookingId,
  missionId,
  style,
  testID = 'appeler',
}: {
  role: 'client' | 'provider';
  bookingId?: number | null;
  missionId?: number | null;
  style?: object;
  testID?: string;
}) {
  const { t: tr } = useTraduction();
  const t = useThemeColors();
  const styles = stylesFor(t);

  const client = useLigneMasqueeClient(role === 'client' ? (bookingId ?? null) : null);
  const prestataire = useLigneMasqueePrestataire(role === 'provider' ? (missionId ?? null) : null);

  const ligne = role === 'client' ? client.data : prestataire.data;

  const appeler = () => {
    if (!ligne?.available || !ligne.proxy_number) {
      Alert.alert(
        tr('bouton_appel_masque.appel_indisponible'),
        ligne?.message ?? 'La ligne n’est pas encore ouverte pour cette intervention.',
      );

      return;
    }

    void Linking.openURL(`tel:${ligne.proxy_number}`);
  };

  return (
    <TouchableOpacity
      onPress={appeler}
      accessibilityRole="button"
      accessibilityLabel={
        role === 'client' ? tr('bouton_appel_masque.appeler_le_prestataire') : tr('bouton_appel_masque.appeler_le_client')
      }
      accessibilityHint={tr('bouton_appel_masque.lappel_passe_par_un_numero')}
      style={[styles.bouton, style]}
      testID={testID}
    >
      <Text style={styles.texte}>{tr('bouton_appel_masque.appeler')}</Text>
    </TouchableOpacity>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  // La case entière est la cible tactile, gants aux mains.
  bouton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: t.card,
    paddingHorizontal: spacing.sm,
  },
  texte: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    textAlign: 'center',
  },
});
