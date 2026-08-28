import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface ScoreLigne {
  user_id: number;
  name: string | null;
  missions_count: number;
  has_enough_data: boolean;
  inspection_score: number | null;
  satisfaction_score: number | null;
  punctuality_score: number | null;
  score: number | null;
}

interface Vehicule {
  id: number;
  plate: string | null;
  brand: string | null;
  model: string | null;
  status: string;
  current_provider_name: string | null;
}

interface Echeance {
  id: number;
  certification_type: string;
  subject_type: string;
  subject_id: number;
  expires_at: string | null;
}

/**
 * LE SCORE QUALITÉ INTERNE (E26) ET LA FLOTTE (E27).
 *
 * DEUX MODULES SUR UN ÉCRAN PARCE QU'ILS RÉPONDENT À LA MÊME QUESTION : qui peut travailler demain,
 * et avec quoi. Une certification expirée refuse une assignation ; un score de ponctualité en chute
 * annonce le contrat qu'on va perdre.
 *
 * L'ÉCHÉANCE PASSE EN TÊTE, et c'est la seule lecture qui change quelque chose. Le reste est un
 * inventaire ; celle-ci évite qu'une assignation soit refusée un matin sans que personne ne sache
 * pourquoi.
 *
 * UN SCORE SANS MATIÈRE NE S'AFFICHE PAS. Sous le seuil, l'écran dit « pas assez de données »
 * plutôt qu'un nombre qui serait lu comme un jugement.
 */
export function CompanyQualityFleetScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();

  const peutVoirLaQualite = can(user, 'missions.quality');
  const peutVoirLaFlotte = can(user, 'fleet.view');

  const { data: scores, refetch, isRefetching } = useQuery<{ data: ScoreLigne[] }>({
    queryKey: ['company', 'quality-scores'],
    // Demander une donnée qu'on n'a pas le droit de lire produirait un 403 à chaque ouverture.
    enabled: peutVoirLaQualite,
    queryFn: async () => (await apiClient.get('/provider/company/quality-scores')).data,
  });

  const { data: flotte } = useQuery<{
    vehicles: Vehicule[];
    expiring: Echeance[];
  }>({
    queryKey: ['company', 'fleet'],
    enabled: peutVoirLaFlotte,
    queryFn: async () => (await apiClient.get('/provider/company/fleet')).data,
  });

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_quality_fleet.qualite_et_materiel')}</Text>
      <Text style={styles.intro}>{tr('company_quality_fleet.qui_peut_travailler_demain_et')}</Text>

      {peutVoirLaFlotte && (flotte?.expiring?.length ?? 0) > 0 && (
        // Découvrir l'expiration quand le moteur refuse l'assignation, c'est la découvrir trop tard.
        <View style={styles.alerte} testID="echeances">
          <Text style={styles.alerteTitre}>{tr('company_quality_fleet.echeances')}</Text>
          {(flotte?.expiring ?? []).map((echeance) => (
            <Text key={echeance.id} style={styles.alerteTexte}>
              {echeance.certification_type} — expire le {echeance.expires_at ?? 'date inconnue'}
            </Text>
          ))}
        </View>
      )}

      {peutVoirLaQualite && (
        <FlatList
          data={scores?.data ?? []}
          keyExtractor={(l) => String(l.user_id)}
          onRefresh={refetch}
          refreshing={isRefetching}
          style={styles.liste}
          renderItem={({ item }) => (
            <View style={styles.ligne} testID={`score-${item.user_id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {item.name ?? 'Sans nom'}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {item.missions_count} mission(s)
                  {item.punctuality_score !== null ? ` · ponctualité ${item.punctuality_score} %` : ''}
                </Text>
              </View>

              {item.has_enough_data && item.score !== null ? (
                <Badge
                  label={`${item.score} %`}
                  variant={item.score >= 80 ? 'success' : item.score >= 60 ? 'warning' : 'danger'}
                />
              ) : (
                <Text style={styles.detail}>{tr('company_quality_fleet.pas_assez_de_donnees')}</Text>
              )}
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              title={tr('company_quality_fleet.aucun_score')}
              message="Trois sources existantes, aucune nouvelle collecte. Ce score ne sort pas de votre société."
            />
          }
        />
      )}

      {peutVoirLaFlotte && (
        <View style={styles.bloc}>
          <Text style={styles.sousTitre}>{tr('company_quality_fleet.vehicules')}</Text>

          {(flotte?.vehicles ?? []).map((vehicule) => (
            <View key={vehicule.id} style={styles.ligne} testID={`vehicule-${vehicule.id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {vehicule.plate ?? `Véhicule ${vehicule.id}`}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {[vehicule.brand, vehicule.model].filter(Boolean).join(' ') || 'Modèle non renseigné'}
                  {vehicule.current_provider_name ? ` · ${vehicule.current_provider_name}` : ''}
                </Text>
              </View>
              <Badge
                label={vehicule.status}
                variant={vehicule.status === 'available' ? 'success' : 'neutral'}
              />
            </View>
          ))}

          {(flotte?.vehicles ?? []).length === 0 && (
            <Text style={styles.detail}>
              {tr('company_quality_fleet.aucun_vehicule_declare_jusqu_ici')}
            </Text>
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
    liste: { marginVertical: spacing.md },
    alerte: {
      backgroundColor: t.card,
      borderRadius: 12,
      padding: spacing.sm,
      marginBottom: spacing.sm,
    },
    alerteTitre: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginBottom: spacing.xs / 2,
    },
    alerteTexte: { fontSize: typography.fontSize.sm, color: t.textMuted },
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
