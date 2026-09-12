import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { MembreSociete } from './types';
import { useTraduction, traduireMaintenant } from '@/i18n';

/**
 * Les rôles tels que l'organisation les nomme, traduits pour l'écran.
 *
 * Le serveur renvoie la valeur de l'enum, pas un libellé : afficher `site_manager` à un
 * gestionnaire serait lui montrer une clé de base de données. Un rôle inconnu retombe sur sa
 * valeur brute plutôt que de disparaître — un membre sans rôle affiché serait plus trompeur.
 */
/* Des CLES, pas des libelles : traduits ici, ils garderaient la langue du demarrage. */
const LIBELLE_ROLE: Record<string, string> = {
  owner: 'company_members.proprietaire',
  manager: 'company_members.role_gestionnaire',
  site_manager: 'company_members.responsable_de_site',
  finance: 'company_members.role_finance',
  requester: 'company_members.role_demandeur',
  operations_manager: 'company_members.role_responsable_exploitation',
  worker: 'company_members.role_intervenant',
  viewer: 'company_members.role_lecture_seule',
};

export function CompanyMembersScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { data: membres, refetch, isRefetching, isError } = useQuery<MembreSociete[]>({
    queryKey: ['client-company', 'members'],
    queryFn: async () => (await apiClient.get('/client/company/members')).data.data ?? [],
  });

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title={tr('company_members.membres_indisponibles')}
          message={tr('company_members.impossible_de_charger_l_equipe_de_votre')}
          actionLabel={tr('company_members.reessayer')}
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_members.membres')}</Text>

      <FlatList
        data={membres ?? []}
        keyExtractor={(m) => String(m.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`membre-${item.id}`}>
            <View style={styles.identite}>
              {/*
                Un compte supprimé laisse son adhésion derrière lui : la relation est alors nulle,
                et une ligne sans nom passerait pour un bogue d'affichage.
              */}
              <Text style={styles.nom} numberOfLines={1}>
                {item.name ?? tr('company_members.compte_supprime')}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {item.email ?? '—'} · {tr(LIBELLE_ROLE[item.role] ?? item.role)}
              </Text>
            </View>

            <Badge
              label={item.status === 'active' ? 'Actif' : item.status}
              variant={item.status === 'active' ? 'success' : 'neutral'}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('company_members.aucun_membre')}
            message={tr('company_members.invitez_vos_collegues_depuis_l_espace_entreprise')}
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
    detail: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
  });
