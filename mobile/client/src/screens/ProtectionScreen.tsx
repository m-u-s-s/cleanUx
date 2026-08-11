import React from 'react';
import { View, ScrollView, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Police {
  id: number;
  policy_number: string | null;
  coverage_amount_cents: number;
  effective_until: string | null;
  booking_reference: string | null;
}

interface Devis {
  booking_id: number;
  booking_reference: string | null;
  scheduled_at: string | null;
  hours_before: number;
  policy: Record<string, unknown> | null;
}

interface Dossier {
  id: number;
  reference: string | null;
  subject: string | null;
  status: string;
  opened_at: string | null;
}

interface Protection {
  insurance: { active_count: number; total_coverage_cents: number; policies: Police[] };
  cancellation: { upcoming_count: number; quotes: Devis[] };
  disputes: { open_count: number; cases: Dossier[] };
}

/**
 * « MA PROTECTION » (E6).
 *
 * TOUTES LES BRIQUES EXISTENT : Insurance, Cancellation v2, Disputes. Chacune a son écran, sa
 * logique, ses tests. Et aucun client ne sait ce qu'il a. Il découvre son assurance au moment du
 * sinistre — trop tard pour la souscrire —, ses frais d'annulation en annulant, et l'existence des
 * litiges en cherchant un numéro de téléphone.
 *
 * SUR MOBILE PLUS QU'AILLEURS. Cette page se consulte au pire moment : quand quelque chose vient de
 * se casser, et qu'on n'est pas devant un ordinateur.
 *
 * CET ÉCRAN N'AJOUTE AUCUNE RÈGLE. Il lit les trois modules et les met côte à côte : une protection
 * qu'on ne peut pas énoncer AVANT d'en avoir besoin n'en est pas une.
 */
export function ProtectionScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: protection } = useQuery<Protection>({
    queryKey: ['client', 'protection'],
    queryFn: async () => (await apiClient.get('/client/protection')).data.data,
  });

  const frais = (devis: Devis): number | null => {
    const brut = (devis.policy?.fee_cents ?? devis.policy?.fee) as number | undefined;

    return typeof brut === 'number' && brut > 0 ? brut : null;
  };

  return (
    <Screen>
      <ScrollView>
        <Text style={styles.title}>Ma protection</Text>
        <Text style={styles.intro}>
          Ce qui vous couvre, ce que coûterait une annulation, et où en sont vos réclamations.
        </Text>

        <View style={styles.bloc} testID="assurance">
          <Text style={styles.sousTitre}>Assurance</Text>

          {(protection?.insurance.policies ?? []).map((police) => (
            <View key={police.id} style={styles.ligne} testID={`police-${police.id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  Police {police.policy_number ?? '—'}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {police.booking_reference ?? 'Intervention'}
                  {police.effective_until ? ` · jusqu'au ${police.effective_until}` : ''}
                </Text>
              </View>
              <Badge label={`${(police.coverage_amount_cents / 100).toFixed(0)} €`} variant="success" />
            </View>
          ))}

          {(protection?.insurance.active_count ?? 0) === 0 && (
            // On le DIT : découvrir qu'on n'était pas couvert au moment du sinistre est exactement
            // ce que cette page doit éviter.
            <Text style={styles.detail}>
              Aucune intervention assurée en cours. L'assurance se souscrit au moment de la
              réservation.
            </Text>
          )}
        </View>

        <View style={styles.bloc} testID="annulation">
          <Text style={styles.sousTitre}>Si vous annuliez maintenant</Text>

          {(protection?.cancellation.quotes ?? []).map((devis) => (
            <View key={devis.booking_id} style={styles.ligne} testID={`annulation-${devis.booking_id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {devis.booking_reference ?? 'Intervention'}
                </Text>
                <Text style={styles.detail}>dans {devis.hours_before} h</Text>
              </View>
              <Badge
                label={frais(devis) ? `${((frais(devis) ?? 0) / 100).toFixed(2)} €` : 'Sans frais'}
                variant={frais(devis) ? 'warning' : 'success'}
              />
            </View>
          ))}

          {(protection?.cancellation.quotes ?? []).length === 0 && (
            <Text style={styles.detail}>Aucune intervention à venir.</Text>
          )}
        </View>

        <View style={styles.bloc} testID="reclamations">
          <Text style={styles.sousTitre}>Mes réclamations</Text>

          {(protection?.disputes.cases ?? []).map((dossier) => (
            <View key={dossier.id} style={styles.ligne} testID={`dossier-${dossier.id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {dossier.subject ?? dossier.reference}
                </Text>
                <Text style={styles.detail}>Ouverte le {dossier.opened_at}</Text>
              </View>
              <Badge label={dossier.status} variant="neutral" />
            </View>
          ))}

          {(protection?.disputes.cases ?? []).length === 0 && (
            <Text style={styles.detail}>
              Aucune réclamation. Vous pouvez en ouvrir une depuis une intervention terminée.
            </Text>
          )}
        </View>
      </ScrollView>
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
    sousTitre: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginBottom: spacing.xs,
    },
    bloc: { marginTop: spacing.md },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    identite: { flex: 1, minWidth: 0 },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted },
  });
