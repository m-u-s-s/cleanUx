import React, { useState } from 'react';
import { View, FlatList, Text, StyleSheet, Pressable, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Badge, EmptyState, Button, Divider } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Referent {
  id: number;
  name: string | null;
  role: string;
}

interface SiteDesservi {
  id: number;
  name: string;
  city: string | null;
  postal_code: string | null;
  address: string | null;
  referents: Referent[];
}

/**
 * LES SITES CLIENTS QUE LA SOCIÉTÉ DESSERT.
 *
 * En lecture sur mobile : désigner un référent est un geste d'organisation qu'on pose au bureau,
 * depuis l'écran web, pas entre deux interventions. Ce qu'on veut ici c'est se souvenir de QUI
 * connaît ce bâtiment avant d'y envoyer quelqu'un.
 *
 * Les sites se DÉDUISENT des missions et des contrats-cadres — un prestataire ne possède pas les
 * locaux de ses clients. Et les référents affichés sont ceux de NOTRE société : deux prestataires
 * peuvent desservir le même immeuble.
 */
export function CompanySitesScreen() {
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  /*
   * NOMMER UN RÉFÉRENT DEPUIS LE TERRAIN, ET NON PLUS SEULEMENT AU BUREAU.
   *
   * L'écran était en lecture, au motif que la désignation se pose au bureau. Mais c'est SUR PLACE
   * qu'on apprend qui connaît le bâtiment — celui qui a le code de la porte, l'étage à ne pas
   * déranger. Le noter en rentrant, c'est ne jamais le noter.
   */
  const peutDesigner = can(user, 'sites.assign_members');
  const [siteOuvert, setSiteOuvert] = useState<number | null>(null);

  const { data: collegues } = useQuery<Array<{ user_id: number; name: string | null; status: string }>>({
    queryKey: ['company', 'members'],
    queryFn: async () => (await apiClient.get('/provider/company/members')).data.data ?? [],
    enabled: siteOuvert !== null && peutDesigner,
  });

  const designer = useMutation({
    mutationFn: async (params: { siteId: number; userId: number; retirer: boolean }) =>
      params.retirer
        ? apiClient.delete(`/provider/company/sites/${params.siteId}/referents/${params.userId}`)
        : apiClient.post(`/provider/company/sites/${params.siteId}/referents`, {
            user_id: params.userId,
            role: 'lead',
          }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'sites'] }),
    onError: (erreur: any) =>
      Alert.alert(
        'Action refusée',
        erreur?.data?.message ?? 'Votre rôle ne permet pas de désigner un référent.',
      ),
  });

  const { data: sites, refetch, isRefetching, isError } = useQuery<SiteDesservi[]>({
    queryKey: ['company', 'sites'],
    queryFn: async () => (await apiClient.get('/provider/company/sites')).data.data ?? [],
  });

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title="Sites indisponibles"
          message="Impossible de charger les sites desservis par votre société."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.title}>Sites desservis</Text>

      <FlatList
        data={sites ?? []}
        keyExtractor={(s) => String(s.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View>
            <Pressable
              style={styles.ligne}
              testID={`site-${item.id}`}
              accessibilityRole={peutDesigner ? 'button' : undefined}
              disabled={!peutDesigner}
              onPress={() => setSiteOuvert(siteOuvert === item.id ? null : item.id)}
            >
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {[item.city, item.postal_code].filter(Boolean).join(' ') || 'Adresse non renseignée'}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {item.referents.length > 0
                    ? item.referents.map((r) => r.name ?? 'Compte supprimé').join(', ')
                    : 'Aucun référent désigné'}
                </Text>
              </View>

              {item.referents.length > 0 ? (
                <Badge label="Référent" variant="success" />
              ) : (
                <Badge label="À couvrir" variant="neutral" />
              )}
            </Pressable>

            {siteOuvert === item.id && peutDesigner && (
              <View style={styles.referents} testID={`referents-${item.id}`}>
                <Text style={styles.section}>Référents</Text>

                {item.referents.map((referent) => (
                  <View key={referent.id} style={styles.ligneReferent}>
                    <Text style={styles.nomReferent} numberOfLines={1}>
                      {referent.name ?? 'Compte supprimé'} · {referent.role}
                    </Text>
                    <Button
                      label="Retirer"
                      size="sm"
                      variant="ghost"
                      onPress={() =>
                        designer.mutate({ siteId: item.id, userId: referent.id, retirer: true })
                      }
                    />
                  </View>
                ))}

                <Divider />
                <Text style={styles.section}>Désigner un habitué</Text>

                {(collegues ?? [])
                  .filter((c) => c.status === 'active')
                  .map((collegue) => (
                    <View key={collegue.user_id} style={styles.ligneReferent}>
                      <Text style={styles.nomReferent} numberOfLines={1}>
                        {collegue.name ?? '—'}
                      </Text>
                      <Button
                        label="Désigner"
                        size="sm"
                        variant="secondary"
                        onPress={() =>
                          designer.mutate({
                            siteId: item.id,
                            userId: collegue.user_id,
                            retirer: false,
                          })
                        }
                      />
                    </View>
                  ))}
              </View>
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun site desservi"
            message="Les sites apparaîtront dès votre première mission ou votre premier contrat-cadre."
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
    referents: {
      paddingLeft: spacing.sm,
      paddingBottom: spacing.sm,
      borderLeftWidth: 2,
      borderLeftColor: t.border,
    },
    section: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginTop: spacing.sm,
      marginBottom: spacing.xs,
    },
    ligneReferent: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: spacing.sm,
      paddingVertical: spacing.xs,
    },
    nomReferent: {
      flex: 1,
      fontSize: typography.fontSize.sm,
      color: t.text,
    },
    identite: { flex: 1, minWidth: 0 },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted },
  });
