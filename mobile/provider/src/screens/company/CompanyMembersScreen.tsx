import React, { useState } from 'react';
import { View, FlatList, Text, StyleSheet, Pressable, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Badge, EmptyState, Button, Divider } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

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
  manager: 'Gestionnaire',
  dispatcher: 'Répartiteur',
  site_manager: 'Responsable de site',
  team_lead: "Chef d'équipe",
  quality_manager: 'Qualité',
  finance: 'Finance',
  requester: 'Demandeur',
  worker: 'Employé',
  viewer: 'Lecture seule',
};

/**
 * LES RÔLES ATTRIBUABLES, DANS L'ORDRE DE LA HIÉRARCHIE.
 *
 * Le propriétaire n'y figure pas : on ne promeut personne à son propre rang ou au-dessus, et le
 * serveur refuse de toute façon. L'exclure ici évite de proposer un geste voué au 403 — mais ce
 * n'est PAS la garde : elle est côté serveur, dans `OrganizationMemberAdministration`.
 */
const ROLES_ATTRIBUABLES = [
  'operations_manager',
  'manager',
  'dispatcher',
  'site_manager',
  'quality_manager',
  'finance',
  'team_lead',
  'requester',
  'worker',
  'viewer',
] as const;

/**
 * Les membres de la société, en natif — et désormais en écriture.
 *
 * L'écran était en LECTURE SEULE : changer un sous-rôle supposait un poste de travail, alors que
 * l'exigence dit « y compris depuis le mobile ». Les gestes appellent
 * `/provider/company/members/*`, qui partage ses six règles avec l'écran web.
 *
 * LES BOUTONS SUIVENT LES CLÉS DU SERVEUR, jamais le rôle. Une société peut régler sa propre
 * matrice : un écran qui testerait `role === 'owner'` ignorerait ce réglage.
 */
export function CompanyMembersScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const queryClient = useQueryClient();

  /*
   * LES ACTIONS SE DÉPLIENT SOUS LA LIGNE, elles n'ouvrent pas de feuille.
   *
   * Le `BottomSheet` du dépôt est piloté par `ref` et `snapPoints` (Gorhom) : le monter ici aurait
   * demandé de bouchonner un portail dans les tests, alors que la consigne de ce chantier est de
   * PRESSER les boutons pour de vrai. Un panneau en ligne est dans l'arbre dès qu'il est ouvert.
   */
  const [membreOuvertId, setMembreOuvertId] = useState<number | null>(null);

  const peutChangerLeRole = can(user, 'members.edit_role');
  const peutSuspendre = can(user, 'members.suspend');
  const peutRetirer = can(user, 'members.remove');
  const peutAgir = peutChangerLeRole || peutSuspendre || peutRetirer;

  const { data: membres, refetch, isRefetching } = useQuery<Membre[]>({
    queryKey: ['company', 'members'],
    queryFn: async () => (await apiClient.get('/provider/company/members')).data.data ?? [],
  });

  /**
   * UN SEUL POINT D'ÉCHEC POUR LES QUATRE GESTES.
   *
   * Le serveur distingue un refus d'autorisation (403) d'une règle de gestion (422) et renvoie un
   * `message` prêt à lire — « la société doit conserver au moins un propriétaire actif ». Le
   * réécrire ici produirait deux formulations de la même règle, dont l'une vieillirait.
   */
  const executer = useMutation({
    mutationFn: async (action: () => Promise<unknown>) => action(),
    onSuccess: () => {
      setMembreOuvertId(null);
      queryClient.invalidateQueries({ queryKey: ['company', 'members'] });
    },
    onError: (erreur: any) => {
      Alert.alert(
        tr('company_members.action_impossible'),
        erreur?.data?.message ?? erreur?.message ?? "L'action n'a pas pu être effectuée.",
      );
    },
  });

  const changerLeRole = (membre: Membre, role: string) =>
    executer.mutate(() =>
      apiClient.patch(`/provider/company/members/${membre.id}/role`, { role }),
    );

  const basculerLaSuspension = (membre: Membre) =>
    executer.mutate(() =>
      apiClient.post(
        `/provider/company/members/${membre.id}/${membre.status === 'suspended' ? 'reactivate' : 'suspend'}`,
      ),
    );

  /*
   * Le retrait libère aussi les missions à venir et les canaux : c'est irréversible depuis cet
   * écran, donc il se confirme. La confirmation est native — pas un `confirm()` qui bloquerait.
   */
  const retirer = (membre: Membre) =>
    Alert.alert(
      tr('company_members.retirer_ce_membre'),
      `${membre.name ?? 'Ce compte'} perdra l'accès, ses missions à venir retourneront à la répartition et il quittera les canaux de la société. Son historique est conservé.`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Retirer',
          style: 'destructive',
          onPress: () =>
            executer.mutate(() => apiClient.delete(`/provider/company/members/${membre.id}`)),
        },
      ],
    );

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_members.equipe')}</Text>

      <FlatList
        data={membres ?? []}
        keyExtractor={(m) => String(m.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View>
            <Pressable
              style={styles.ligne}
              testID={`membre-${item.id}`}
              accessibilityRole={peutAgir ? 'button' : undefined}
              disabled={!peutAgir}
              onPress={() => setMembreOuvertId(membreOuvertId === item.id ? null : item.id)}
            >
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
            </Pressable>

            {membreOuvertId === item.id && peutAgir && (
              <View style={styles.actions} testID={`actions-membre-${item.id}`}>
                {peutChangerLeRole && (
                  <>
                    <Text style={styles.section}>{tr('company_members.changer_le_role')}</Text>
                    {ROLES_ATTRIBUABLES.filter((role) => role !== item.role).map((role) => (
                      <View key={role} style={styles.action}>
                        <Button
                          label={LIBELLES_ROLE[role] ?? role}
                          variant="secondary"
                          fullWidth
                          onPress={() => changerLeRole(item, role)}
                        />
                      </View>
                    ))}
                    <Divider />
                  </>
                )}

                {peutSuspendre && (
                  <View style={styles.action}>
                    <Button
                      label={item.status === 'suspended' ? 'Réactiver' : 'Suspendre'}
                      variant="secondary"
                      fullWidth
                      onPress={() => basculerLaSuspension(item)}
                    />
                  </View>
                )}

                {peutRetirer && (
                  <View style={styles.action}>
                    <Button
                      label={tr('company_members.retirer_de_la_societe')}
                      variant="danger"
                      fullWidth
                      onPress={() => retirer(item)}
                    />
                  </View>
                )}
              </View>
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('company_members.aucun_membre')}
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
    actions: {
      paddingVertical: spacing.sm,
      paddingLeft: spacing.sm,
      borderLeftWidth: 2,
      borderLeftColor: t.border,
      marginBottom: spacing.sm,
    },
    section: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginTop: spacing.sm,
      marginBottom: spacing.xs,
    },
    action: {
      marginTop: spacing.xs,
    },
  });
