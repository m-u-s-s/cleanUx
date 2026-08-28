import React, { useState } from 'react';
import { View, FlatList, Text, TextInput, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface Creneau {
  id: number;
  user_id: number;
  user_name: string | null;
  starts_at: string | null;
  ends_at: string | null;
  status: string;
  is_published: boolean;
}

interface Absence {
  id: number;
  user_id: number;
  user_name: string | null;
  type: string;
  starts_on: string | null;
  ends_on: string | null;
  status: string;
  reason: string | null;
  blocks_planning: boolean;
}

/**
 * LE PLANNING (E19) ET LES ABSENCES (E21), SUR LE TÉLÉPHONE.
 *
 * C'est l'écran qu'on regarde DEBOUT : un chef d'équipe consulte le planning du lendemain dans la
 * camionnette, un exécutant pose son congé le soir depuis son canapé. Les deux moments ont en commun
 * de n'avoir aucun ordinateur à portée.
 *
 * UN BROUILLON NE REND PERSONNE ASSIGNABLE, et l'écran le dit : sans ce badge, quelqu'un compterait
 * sur un horaire que personne ne lui a communiqué. Publier est un geste séparé, jamais un effet de
 * bord de la création.
 *
 * ON NE VOIT LES ABSENCES DES AUTRES QU'AVEC `team.manage` — le filtrage est fait par l'API, pas
 * ici : une absence dit la maladie, la garde d'enfant, l'accompagnement d'un proche.
 */
export function CompanyPlanningScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const [debutConge, setDebutConge] = useState('');
  const [finConge, setFinConge] = useState('');

  const peutGerer = can(user, 'team.manage');

  const { data: creneaux, refetch, isRefetching } = useQuery<Creneau[]>({
    queryKey: ['company', 'shifts'],
    queryFn: async () => (await apiClient.get('/provider/company/shifts')).data.data ?? [],
  });

  const { data: absences } = useQuery<Absence[]>({
    queryKey: ['company', 'leaves'],
    queryFn: async () => (await apiClient.get('/provider/company/leaves')).data.data ?? [],
  });

  const publier = useMutation({
    mutationFn: async () => apiClient.post('/provider/company/shifts/publish', {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'shifts'] }),
    onError: (erreur: any) =>
      Alert.alert(
        'Publication refusée',
        erreur?.data?.message ?? 'Votre rôle ne permet pas de publier le planning.',
      ),
  });

  const poser = useMutation({
    mutationFn: async () =>
      apiClient.post('/provider/company/leaves', {
        starts_on: debutConge.trim(),
        ends_on: finConge.trim(),
      }),
    onSuccess: () => {
      setDebutConge('');
      setFinConge('');
      qc.invalidateQueries({ queryKey: ['company', 'leaves'] });
    },
    onError: (erreur: any) =>
      // Un refus du domaine — dates qui se chevauchent, fin avant début — est une réponse, pas une
      // panne : on affiche la règle plutôt qu'« une erreur est survenue ».
      Alert.alert('Demande refusée', erreur?.data?.message ?? 'Vérifiez les dates saisies.'),
  });

  const statuer = useMutation({
    mutationFn: async (params: { id: number; approve: boolean }) =>
      apiClient.post(`/provider/company/leaves/${params.id}/decision`, { approve: params.approve }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['company', 'leaves'] });
      qc.invalidateQueries({ queryKey: ['company', 'shifts'] });
    },
    onError: (erreur: any) =>
      Alert.alert('Décision refusée', erreur?.data?.message ?? 'Votre rôle ne permet pas cette action.'),
  });

  const enAttente = (absences ?? []).filter((a) => a.status === 'pending');

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_planning.planning_et_absences')}</Text>
      <Text style={styles.intro}>
        {tr('company_planning.qui_travaille_quand_et_qui')}
      </Text>

      {peutGerer && (
        <Button
          label={tr('company_planning.publier_la_semaine')}
          size="sm"
          fullWidth
          disabled={publier.isPending}
          onPress={() => publier.mutate()}
          testID="bouton-publier-semaine"
        />
      )}

      <FlatList
        data={creneaux ?? []}
        keyExtractor={(c) => String(c.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        style={styles.liste}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`creneau-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.user_name ?? 'Sans nom'}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {formaterCreneau(item.starts_at, item.ends_at)}
              </Text>
            </View>

            {/* Un brouillon ne rend PAS assignable : le dire évite qu'on compte dessus. */}
            <Badge
              label={item.is_published ? 'Publié' : 'Brouillon'}
              variant={item.is_published ? 'success' : 'neutral'}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('company_planning.aucun_creneau')}
            message="Tant qu'aucun planning n'est publié, la répartition fonctionne comme avant."
          />
        }
      />

      <View style={styles.formulaire}>
        <Text style={styles.sousTitre}>{tr('company_planning.poser_une_absence')}</Text>
        <TextInput
          value={debutConge}
          onChangeText={setDebutConge}
          placeholder={tr('company_planning.du_aaaa_mm_jj')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-debut-conge"
        />
        <TextInput
          value={finConge}
          onChangeText={setFinConge}
          placeholder={tr('company_planning.au_inclus_aaaa_mm_jj')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-fin-conge"
        />
        <Button
          label={tr('company_planning.envoyer_la_demande')}
          size="sm"
          fullWidth
          disabled={debutConge.trim().length === 0 || finConge.trim().length === 0 || poser.isPending}
          onPress={() => poser.mutate()}
          testID="bouton-poser-absence"
        />
      </View>

      {peutGerer && enAttente.length > 0 && (
        <View style={styles.aTrancher}>
          <Text style={styles.sousTitre}>{tr('company_planning.demandes_en_attente')}</Text>
          {enAttente.map((absence) => (
            <View key={absence.id} style={styles.ligne} testID={`absence-${absence.id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {absence.user_name ?? 'Sans nom'}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {absence.starts_on} → {absence.ends_on}
                </Text>
              </View>
              <Button
                label={tr('company_planning.approuver')}
                size="sm"
                onPress={() => statuer.mutate({ id: absence.id, approve: true })}
                testID={`approuver-absence-${absence.id}`}
              />
              <Button
                label={tr('company_planning.refuser')}
                size="sm"
                variant="ghost"
                onPress={() => statuer.mutate({ id: absence.id, approve: false })}
              />
            </View>
          ))}
        </View>
      )}
    </Screen>
  );
}

/** Une plage lisible d'un coup d'œil : le jour, puis les deux heures. */
function formaterCreneau(debut: string | null, fin: string | null): string {
  if (!debut) return 'Horaire non renseigné';

  const d = new Date(debut);
  const f = fin ? new Date(fin) : null;

  const jour = d.toLocaleDateString('fr-BE', { weekday: 'short', day: '2-digit', month: '2-digit' });
  const heure = (v: Date) =>
    v.toLocaleTimeString('fr-BE', { hour: '2-digit', minute: '2-digit' });

  return f ? `${jour} ${heure(d)} → ${heure(f)}` : `${jour} ${heure(d)}`;
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
    liste: { marginVertical: spacing.md },
    formulaire: { gap: spacing.xs, marginTop: spacing.md },
    aTrancher: { marginTop: spacing.md },
    champ: {
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
    },
    placeholder: { color: t.textMuted },
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
