import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Membre {
  id: number;
  user_id: number;
  name: string | null;
  email: string | null;
  role: string;
  status: string;
}

const LIBELLES_ROLE: Record<string, string> = {
  owner: 'Propriétaire',
  operations_manager: 'Responsable exploitation',
  dispatcher: 'Répartiteur',
  team_lead: "Chef d'équipe",
  quality_manager: 'Qualité',
  finance: 'Finance',
  worker: 'Employé',
  viewer: 'Lecture seule',
};

/**
 * Les membres de la société, en natif.
 *
 * Cet écran remplace un module servi en WebView : l'API `/provider/company/members` n'existait pas
 * avant, ce qui était la vraie raison de l'embarquement web.
 */
export function CompanyMembersScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: membres, refetch, isRefetching } = useQuery<Membre[]>({
    queryKey: ['company', 'members'],
    queryFn: async () => (await apiClient.get('/provider/company/members')).data.data ?? [],
  });

  return (
    <Screen>
      <Text style={styles.title}>Équipe</Text>

      <FlatList
        data={membres ?? []}
        keyExtractor={(m) => String(m.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`membre-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.name ?? 'Utilisateur supprimé'}
              </Text>
              <Text style={styles.email} numberOfLines={1}>
                {item.email ?? '—'}
              </Text>
            </View>

            <Badge
              label={LIBELLES_ROLE[item.role] ?? item.role}
              variant={item.status === 'active' ? 'brand' : 'neutral'}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun membre"
            message="Invitez vos collaborateurs depuis l'espace société."
          />
        }
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginBottom: spacing.md,
    },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    identite: {
      flex: 1,
      minWidth: 0,
    },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    email: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
  });
