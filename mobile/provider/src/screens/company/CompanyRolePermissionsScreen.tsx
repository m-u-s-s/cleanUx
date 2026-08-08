import React, { useState } from 'react';
import { View, Text, ScrollView, StyleSheet, Pressable, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, EmptyState, Divider } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Matrice {
  permissions: string[];
  roles: Array<{ value: string; label: string }>;
  matrix: Record<string, Record<string, boolean>>;
}

/**
 * « CHEZ NOUS, LES CHEFS D'ÉQUIPE ASSIGNENT LES MISSIONS. »
 *
 * Pendant natif de l'écran web `RolePermissionsMatrix`. La table `organization_role_permissions`
 * était lue par le serveur et écrite par personne : décider d'une règle de maison réclamait un
 * déploiement.
 *
 * L'ÉCRAN NE CONNAÎT AUCUNE MATRICE PAR DÉFAUT, et c'est délibéré. Il affiche ce que le serveur
 * calcule — réglage de la société s'il existe, défaut du code sinon. Reconstituer les valeurs
 * d'usine côté client aurait créé une seconde vérité, qui aurait vieilli à la première permission
 * ajoutée.
 *
 * UN RÔLE À LA FOIS. Onze rôles fois trente-cinq clés ne tiennent pas sur un téléphone en tableau ;
 * on choisit un rôle, puis on parcourt ses clés. C'est le même contenu, plié dans le sens du
 * support.
 */
export function CompanyRolePermissionsScreen() {
  const styles = stylesFor(useThemeColors());
  const queryClient = useQueryClient();

  const [roleOuvert, setRoleOuvert] = useState<string | null>(null);

  const { data, isLoading } = useQuery<Matrice>({
    queryKey: ['company', 'role-permissions'],
    queryFn: async () => (await apiClient.get('/provider/company/role-permissions')).data.data,
  });

  const basculer = useMutation({
    mutationFn: (params: { role: string; permission: string; granted: boolean }) =>
      apiClient.put('/provider/company/role-permissions', params),
    /*
     * On RECHARGE plutôt que de retoucher le cache localement. Le serveur est seul juge de l'état
     * effectif — dérogations nominatives comprises —, et une optimisation optimiste afficherait ici
     * une vérité que `PermissionService` pourrait contredire.
     */
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['company', 'role-permissions'] }),
    onError: (erreur: any) =>
      Alert.alert(
        'Réglage impossible',
        erreur?.data?.message ?? "Ce réglage n'a pas pu être enregistré.",
      ),
  });

  if (!isLoading && !data) {
    return (
      <Screen>
        <EmptyState
          title="Réglages indisponibles"
          message="Seul le propriétaire de la société peut régler les rôles."
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <ScrollView>
        <Text style={styles.title}>Rôles et permissions</Text>
        <Text style={styles.intro}>
          Ce que chaque rôle peut faire chez vous. Un changement s'applique immédiatement à toutes
          les personnes portant ce rôle. Le rôle Propriétaire n'est pas réglable : il porte le droit
          d'ouvrir cet écran.
        </Text>

        {(data?.roles ?? []).map((role) => (
          <View key={role.value}>
            <Pressable
              style={styles.ligneRole}
              testID={`role-${role.value}`}
              accessibilityRole="button"
              onPress={() => setRoleOuvert(roleOuvert === role.value ? null : role.value)}
            >
              <Text style={styles.nomRole}>{role.label}</Text>
              <Text style={styles.chevron}>{roleOuvert === role.value ? '−' : '+'}</Text>
            </Pressable>

            {roleOuvert === role.value && (
              <View style={styles.cles} testID={`cles-${role.value}`}>
                {(data?.permissions ?? []).map((permission) => {
                  const accorde = data?.matrix?.[role.value]?.[permission] === true;

                  return (
                    <Pressable
                      key={permission}
                      style={styles.ligneCle}
                      testID={`cle-${role.value}-${permission}`}
                      accessibilityRole="switch"
                      accessibilityState={{ checked: accorde }}
                      onPress={() =>
                        basculer.mutate({ role: role.value, permission, granted: !accorde })
                      }
                    >
                      <Text style={styles.nomCle}>{permission}</Text>
                      <Text style={accorde ? styles.marqueOui : styles.marqueNon}>
                        {accorde ? '✓' : '·'}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            )}

            <Divider />
          </View>
        ))}
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
      marginBottom: spacing.xs,
    },
    intro: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginBottom: spacing.md,
    },
    ligneRole: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingVertical: spacing.sm,
    },
    nomRole: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    chevron: {
      fontSize: typography.fontSize.lg,
      color: t.textMuted,
    },
    cles: {
      paddingLeft: spacing.sm,
      borderLeftWidth: 2,
      borderLeftColor: t.border,
      marginBottom: spacing.sm,
    },
    ligneCle: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingVertical: spacing.xs,
    },
    nomCle: {
      flex: 1,
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
    /*
     * La marque « accordé » se distingue par le POIDS et un voile de fond, pas par une couleur
     * verte écrite en dur : il n'existe pas de jeton de TEXTE vert — `tint.success` est un fond —
     * et `__tests__/theme/noHardcodedColors.test.ts` refuse les littéraux, à raison.
     */
    marqueOui: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      backgroundColor: t.tint.success,
      paddingHorizontal: spacing.xs,
      borderRadius: radius.sm,
      overflow: 'hidden',
    },
    marqueNon: {
      fontSize: typography.fontSize.base,
      color: t.textMuted,
      paddingHorizontal: spacing.xs,
    },
  });
