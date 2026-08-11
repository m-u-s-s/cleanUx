import React, { useState } from 'react';
import { View, FlatList, Text, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Offre {
  id: number;
  reference: string;
  title: string;
  trade_name: string | null;
  status: string;
  applications_count: number;
}

interface Candidature {
  id: number;
  full_name: string;
  email: string;
  phone: string | null;
  status: string;
  invited: boolean;
}

/**
 * LE RECRUTEMENT (E25), DEPUIS LE TÉLÉPHONE.
 *
 * Le tri des candidatures se fait entre deux chantiers, dans la camionnette — c'est le moment où un
 * gérant de société de services a cinq minutes. Repousser cette décision au retour au bureau, c'est
 * la repousser d'une semaine, et le bon candidat aura signé ailleurs.
 *
 * EMBAUCHER ÉMET L'INVITATION — un même bouton, pas deux écrans. Séparer les deux produirait le
 * défaut exact qu'on répare : une candidature marquée « embauché » et personne dans l'organigramme.
 */
export function CompanyRecruitmentScreen() {
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const [offreOuverte, setOffreOuverte] = useState<number | null>(null);

  const peutGerer = can(user, 'recruitment.manage');

  const { data: offres, refetch, isRefetching } = useQuery<Offre[]>({
    queryKey: ['company', 'job-postings'],
    queryFn: async () => (await apiClient.get('/provider/company/job-postings')).data.data ?? [],
  });

  const { data: candidatures } = useQuery<Candidature[]>({
    queryKey: ['company', 'job-applications', offreOuverte],
    enabled: offreOuverte !== null,
    queryFn: async () =>
      (await apiClient.get(`/provider/company/job-postings/${offreOuverte}/applications`)).data
        .data ?? [],
  });

  const statuer = useMutation({
    mutationFn: async (params: { id: number; decision: 'shortlist' | 'hire' | 'reject' }) =>
      apiClient.post(`/provider/company/job-applications/${params.id}/decision`, {
        decision: params.decision,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['company', 'job-applications'] });
      qc.invalidateQueries({ queryKey: ['company', 'job-postings'] });
    },
    onError: (erreur: any) =>
      Alert.alert('Décision refusée', erreur?.data?.message ?? 'Votre rôle ne permet pas cette action.'),
  });

  return (
    <Screen>
      <Text style={styles.title}>Recrutement</Text>
      <Text style={styles.intro}>
        Vos offres et les candidatures reçues. Embaucher envoie l'invitation.
      </Text>

      <FlatList
        data={offres ?? []}
        keyExtractor={(o) => String(o.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`offre-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.title}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {item.trade_name ?? 'Tous métiers'} · {item.applications_count} candidature(s)
              </Text>
            </View>

            <Badge
              label={item.status === 'published' ? 'Publiée' : item.status === 'closed' ? 'Fermée' : 'Brouillon'}
              variant={item.status === 'published' ? 'success' : 'neutral'}
            />

            <Button
              label="Voir"
              size="sm"
              variant="ghost"
              onPress={() => setOffreOuverte(item.id)}
              testID={`ouvrir-offre-${item.id}`}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucune offre"
            message="Jusqu'ici, tout le recrutement se faisait hors de la plateforme."
          />
        }
      />

      {offreOuverte !== null && (
        <View style={styles.bloc}>
          <Text style={styles.sousTitre}>Candidatures</Text>

          {(candidatures ?? []).map((candidature) => (
            <View key={candidature.id} style={styles.ligne} testID={`candidature-${candidature.id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {candidature.full_name}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {candidature.email}
                </Text>
              </View>

              {candidature.invited ? (
                <Badge label="Invitation envoyée" variant="success" />
              ) : candidature.status === 'rejected' ? (
                <Badge label="Écartée" variant="neutral" />
              ) : (
                peutGerer && (
                  <>
                    <Button
                      label="Embaucher"
                      size="sm"
                      onPress={() => statuer.mutate({ id: candidature.id, decision: 'hire' })}
                      testID={`embaucher-${candidature.id}`}
                    />
                    <Button
                      label="Écarter"
                      size="sm"
                      variant="ghost"
                      onPress={() => statuer.mutate({ id: candidature.id, decision: 'reject' })}
                    />
                  </>
                )
              )}
            </View>
          ))}

          {(candidatures ?? []).length === 0 && (
            <Text style={styles.detail}>Aucune candidature pour cette offre.</Text>
          )}
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
