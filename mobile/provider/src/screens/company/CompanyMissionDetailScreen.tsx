import React, { useState } from 'react';
import { View, Text, ScrollView, StyleSheet, Pressable, Alert, TextInput } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import { Screen, Button, Badge, Divider, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
/*
 * Chemin direct plutot que le baril `@/ui` : trente-six suites le mockent a la main,
 * tout export neuf y manque, et le composant arrive `undefined` AU RENDU — la ou `tsc`
 * reste vert.
 */
import { GrilleDeCases } from '@/ui/GrilleDeCases';
import { useLiveMissionUpdates } from '@/missions';
/*
 * `missionStatusLabel` par son CHEMIN DIRECT, pour la meme raison que la grille : la
 * suite de cet ecran mocke `@/missions` a la main pour n'en fournir que le crochet
 * temps reel. Passe par le baril, le libelle arrive `undefined` et le rendu tombe.
 */
import { missionStatusLabel } from '@/missions/labels';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

interface Disponibilite {
  user_id: number;
  name: string | null;
  is_free: boolean;
}

/**
 * L'ETAT D'UNE MISSION, TRADUIT EN COULEUR.
 *
 * Une mission annulee et une mission terminee ne se lisent pas pareil : l'une demande une
 * reaction, l'autre non. Le repli neutre est volontaire — un statut que le serveur
 * ajouterait resterait lisible plutot que de prendre une couleur qui ment.
 */
function tonDuStatut(statut: string): 'neutre' | 'accent' | 'bon' | 'attention' | 'alerte' {
  if (statut === 'cancelled') return 'alerte';
  if (statut === 'completed') return 'bon';
  if (statut === 'paused') return 'attention';
  if (statut === 'started' || statut === 'en_route' || statut === 'arrived') return 'accent';

  return 'neutre';
}

interface MissionSociete {
  id: number;
  status: string;
  planned_start_at: string | null;
  site: string | null;
  city: string | null;
  lead: string | null;
  lead_user_id: number | null;
}

/**
 * LE DÉTAIL D'UNE MISSION DE SOCIÉTÉ — choisir en sachant qui est libre.
 *
 * Ce qui existait tenait dans un `Alert.alert` limité à dix noms, SANS indicateur de disponibilité :
 * le répartiteur choisissait à l'aveugle depuis son téléphone, là où l'écran web le renseignait
 * déjà. Un nom de plus que dix, et la personne n'était tout simplement pas proposable.
 *
 * LA DISPONIBILITÉ VIENT DU SERVEUR, jamais d'un calcul local. Elle repose sur le chevauchement des
 * missions de toute la société — une donnée que le téléphone n'a pas, et ne doit pas avoir.
 *
 * `useLiveMissionUpdates` était défini depuis longtemps et JAMAIS APPELÉ. Deux répartiteurs sur la
 * même mission s'écrasaient sans le voir ; ici l'écran se rafraîchit quand la mission bouge
 * ailleurs.
 */
export function CompanyMissionDetailScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const route = useRoute<RouteProp<RootStackParamList, 'CompanyMissionDetail'>>();
  const missionId = route.params?.missionId ?? null;

  const peutRepartir = can(user, 'missions.assign') || can(user, 'missions.dispatch');

  const { data: missions } = useQuery<MissionSociete[]>({
    queryKey: ['company', 'missions'],
    queryFn: async () => (await apiClient.get('/provider/company/missions')).data.data ?? [],
  });

  const mission = (missions ?? []).find((m) => m.id === missionId) ?? null;

  const { data: dispos, refetch: rechargerDispos } = useQuery<Disponibilite[]>({
    queryKey: ['company', 'availability', missionId],
    queryFn: async () =>
      (await apiClient.get(`/provider/company/availability?mission_id=${missionId}`)).data.data
        ?.workers ?? [],
    enabled: missionId !== null && peutRepartir,
  });

  // Enfin branché : la mission peut changer sous les yeux d'un autre répartiteur.
  useLiveMissionUpdates(missionId, () => {
    qc.invalidateQueries({ queryKey: ['company', 'missions'] });
    rechargerDispos();
  });

  const assigner = useMutation({
    mutationFn: async (userId: number) =>
      apiClient.post(`/provider/company/missions/${missionId}/assign`, { user_id: userId }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['company', 'missions'] });
      rechargerDispos();
    },
    onError: (erreur: any) =>
      Alert.alert(
        tr('company_mission_detail.assignation_refusee'),
        erreur?.data?.message ?? 'Votre rôle ne permet pas de répartir cette mission.',
      ),
  });

  /*
   * DÉPLACER — date, heure et LIEU.
   *
   * Une société qui devait décaler d'une heure appelait le client pour qu'il le fasse lui-même : le
   * service de reprogrammation était strictement client/admin. Sous 24 h de l'échéance, le serveur
   * exige un motif et un rôle élevé — on ne réimplémente pas cette règle ici, on affiche ce qu'il
   * répond.
   */
  const peutDeplacer = can(user, 'missions.reschedule');
  const [formulaireOuvert, setFormulaireOuvert] = useState(false);
  const [nouvelleDate, setNouvelleDate] = useState('');
  const [nouvelleHeure, setNouvelleHeure] = useState('');
  const [motif, setMotif] = useState('');

  const deplacer = useMutation({
    mutationFn: async () =>
      apiClient.post(`/provider/company/missions/${missionId}/reschedule`, {
        date: nouvelleDate,
        heure: nouvelleHeure || null,
        motif: motif || null,
      }),
    onSuccess: () => {
      setFormulaireOuvert(false);
      setMotif('');
      qc.invalidateQueries({ queryKey: ['company', 'missions'] });
    },
    // Le serveur distingue le refus d'autorisation de la fenêtre de gel, et rend un message
    // lisible : le réécrire ici produirait deux formulations de la même règle.
    onError: (erreur: any) =>
      Alert.alert(
        tr('company_mission_detail.deplacement_refuse'),
        erreur?.data?.message ?? "L'intervention n'a pas pu être déplacée.",
      ),
  });

  const renfort = useMutation({
    mutationFn: async (params: { userId: number; retirer: boolean }) =>
      apiClient.post(`/provider/company/missions/${missionId}/helpers`, {
        user_id: params.userId,
        remove: params.retirer,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['company', 'missions'] });
      rechargerDispos();
    },
    onError: (erreur: any) =>
      Alert.alert(tr('company_mission_detail.action_refusee'), erreur?.data?.message ?? "L'action n'a pas pu être effectuée."),
  });

  if (mission === null) {
    return (
      <Screen>
        <EmptyState
          title={tr('company_mission_detail.mission_introuvable')}
          message="Elle a peut-être été annulée ou confiée à une autre société."
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <ScrollView>
        <Text style={styles.title}>{mission.site ?? `Mission #${mission.id}`}</Text>
        {/*
          TOUT CE QUI DECIDE, VISIBLE D'UN COUP.

          Ces quatre informations tenaient dans une phrase deroulante et un badge : la ville et
          l'horaire colles par un point median, le responsable a cote. Le repartiteur les lisait
          en les cherchant. L'ETAT, lui, ne s'affichait NULLE PART — `mission.status` etait recu
          et jamais rendu : rien ne distinguait une mission planifiee d'une mission deja finie.

          Le compte des personnes libres est la cinquieme : c'est ce qui dit s'il y a une
          decision a prendre ici, avant meme de derouler la liste.
        */}
        <GrilleDeCases
          colonnes={2}
          cases={[
            {
              libelle: 'Etat',
              valeur: missionStatusLabel(mission.status),
              ton: tonDuStatut(mission.status),
            },
            {
              libelle: 'Creneau',
              valeur: mission.planned_start_at
                ? new Date(mission.planned_start_at).toLocaleString('fr-FR', {
                    weekday: 'short',
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                  })
                : 'Non planifiee',
              ton: mission.planned_start_at ? 'accent' : 'attention',
            },
            { libelle: 'Lieu', valeur: mission.city ?? '—' },
            {
              libelle: 'Responsable',
              valeur: mission.lead ?? 'Non assignee',
              ton: mission.lead ? 'bon' : 'attention',
            },
            ...(peutRepartir
              ? [
                  {
                    libelle: 'Libres',
                    valeur: (dispos ?? []).filter((x) => x.is_free).length,
                    note: `sur ${(dispos ?? []).length} propose(s)`,
                    ton: (dispos ?? []).some((x) => x.is_free) ? ('bon' as const) : ('alerte' as const),
                  },
                ]
              : []),
          ]}
        />

        {!peutRepartir && (
          <Text style={styles.info}>
            {tr('company_mission_detail.vous_consultez_cette_mission_sa')}
          </Text>
        )}

        {peutDeplacer && (
          <>
            <Divider />
            <Pressable
              accessibilityRole="button"
              testID="ouvrir-deplacement"
              onPress={() => setFormulaireOuvert(!formulaireOuvert)}
            >
              <Text style={styles.section}>
                {formulaireOuvert ? '− Déplacer l’intervention' : '+ Déplacer l’intervention'}
              </Text>
            </Pressable>

            {formulaireOuvert && (
              <View testID="formulaire-deplacement">
                <TextInput
                  value={nouvelleDate}
                  onChangeText={setNouvelleDate}
                  placeholder={tr('company_mission_detail.nouvelle_date_aaaa_mm_jj')}
                  placeholderTextColor={styles.placeholder.color}
                  style={styles.champ}
                  testID="champ-date"
                />
                <TextInput
                  value={nouvelleHeure}
                  onChangeText={setNouvelleHeure}
                  placeholder={tr('company_mission_detail.nouvelle_heure_hh_mm')}
                  placeholderTextColor={styles.placeholder.color}
                  style={styles.champ}
                  testID="champ-heure"
                />
                <TextInput
                  value={motif}
                  onChangeText={setMotif}
                  placeholder={tr('company_mission_detail.motif_obligatoire_a_moins_de')}
                  placeholderTextColor={styles.placeholder.color}
                  style={styles.champ}
                  testID="champ-motif"
                />
                <Button
                  label={tr('company_mission_detail.deplacer')}
                  size="sm"
                  fullWidth
                  disabled={nouvelleDate.trim() === '' || deplacer.isPending}
                  onPress={() => deplacer.mutate()}
                />
                <Text style={styles.info}>
                  {tr('company_mission_detail.le_client_et_le_collaborateur')}
                </Text>
              </View>
            )}
          </>
        )}

        {peutRepartir && (
          <>
            <Divider />
            <Text style={styles.section}>{tr('company_mission_detail.qui_est_libre_sur_ce')}</Text>

            {(dispos ?? []).length === 0 && (
              <Text style={styles.info}>
                {tr('company_mission_detail.aucun_collaborateur_a_proposer_la')}
              </Text>
            )}

            {(dispos ?? []).map((personne) => (
              <View key={personne.user_id} style={styles.lignePersonne}>
                <View style={styles.identite}>
                  <Text style={styles.nom} numberOfLines={1}>
                    {personne.name ?? `#${personne.user_id}`}
                  </Text>
                  {/*
                    LE BADGE DIT, IL N'INTERDIT PAS. Un répartiteur qui connaît son équipe passe
                    outre pour de bonnes raisons — un échange entre collègues, une heure
                    supplémentaire consentie. L'outil l'informe ; il ne décide pas à sa place.
                  */}
                  <Text style={personne.is_free ? styles.libre : styles.pris}>
                    {personne.is_free ? 'libre' : 'déjà pris'}
                  </Text>
                </View>

                <View style={styles.actions}>
                  <Button
                    label={mission.lead_user_id === personne.user_id ? 'Responsable' : 'Assigner'}
                    size="sm"
                    variant={mission.lead_user_id === personne.user_id ? 'ghost' : 'secondary'}
                    disabled={mission.lead_user_id === personne.user_id}
                    onPress={() => assigner.mutate(personne.user_id)}
                  />
                  <Pressable
                    accessibilityRole="button"
                    onPress={() => renfort.mutate({ userId: personne.user_id, retirer: false })}
                  >
                    <Text style={styles.lienRenfort}>+ renfort</Text>
                  </Pressable>
                </View>
              </View>
            ))}
          </>
        )}
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
    sousTitre: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginTop: spacing.xs,
    },
    badge: {
      flexDirection: 'row',
      marginTop: spacing.sm,
      marginBottom: spacing.sm,
    },
    section: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginTop: spacing.sm,
      marginBottom: spacing.xs,
    },
    info: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginTop: spacing.sm,
    },
    champ: {
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
      marginBottom: spacing.xs,
    },
    placeholder: {
      color: t.textMuted,
    },
    lignePersonne: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
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
      color: t.text,
    },
    libre: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
    },
    pris: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
    actions: {
      alignItems: 'flex-end',
      gap: spacing.xs,
    },
    lienRenfort: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
      textDecorationLine: 'underline',
    },
  });
