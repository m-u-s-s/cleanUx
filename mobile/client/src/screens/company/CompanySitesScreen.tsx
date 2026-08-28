import React from 'react';
import { View, FlatList, Text, TextInput, Alert, StyleSheet } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { LocalSociete } from './types';
import { useTraduction } from '@/i18n';

/**
 * LES LOCAUX DE LA SOCIÉTÉ.
 *
 * C'est la fonction la plus construite de l'espace société côté web — `OrganizationSite` porte le
 * prestataire préféré, la fréquence, la surface, les accès, le contact — et celle qu'un
 * responsable de sites a le plus besoin d'atteindre depuis le terrain.
 *
 * La création est gardée par `sites.create` côté serveur. On l'affiche à tous et on explique le
 * refus : masquer le formulaire selon un rôle deviné côté client ferait diverger deux règles qui
 * doivent rester une seule, et le serveur est celui qui tranche.
 */
export function CompanySitesScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const [nom, setNom] = React.useState('');
  const [ville, setVille] = React.useState('');

  const { data: locaux, refetch, isRefetching, isError } = useQuery<LocalSociete[]>({
    queryKey: ['client-company', 'sites'],
    queryFn: async () => (await apiClient.get('/client/company/sites')).data.data ?? [],
  });

  const creer = useMutation({
    mutationFn: async () => {
      await apiClient.post('/client/company/sites', {
        name: nom.trim(),
        city: ville.trim() || null,
      });
    },
    onSuccess: () => {
      setNom('');
      setVille('');
      qc.invalidateQueries({ queryKey: ['client-company', 'sites'] });
      qc.invalidateQueries({ queryKey: ['client-company', 'overview'] });
    },
    onError: () =>
      Alert.alert(tr('company_sites.creation_refusee'), tr('company_sites.votre_role_ne_permet_pas')),
  });

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title={tr('company_sites.locaux_indisponibles')}
          message="Votre rôle ne permet peut-être pas de consulter le parc, ou le service est momentanément injoignable."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_sites.mes_locaux')}</Text>

      <View style={styles.formulaire}>
        <TextInput
          value={nom}
          onChangeText={setNom}
          placeholder={tr('company_sites.nom_du_local')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-nom-local"
        />
        <TextInput
          value={ville}
          onChangeText={setVille}
          placeholder={tr('company_sites.ville')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champVille}
          testID="champ-ville-local"
        />
        <Button
          label={tr('company_sites.ajouter')}
          size="sm"
          onPress={() => nom.trim() && creer.mutate()}
          disabled={creer.isPending || nom.trim().length === 0}
        />
      </View>

      <FlatList
        data={locaux ?? []}
        keyExtractor={(l) => String(l.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`local-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.name}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {[item.city, item.postal_code].filter(Boolean).join(' ') || 'Adresse non renseignée'}
                {item.surface_m2 ? ` · ${item.surface_m2} m²` : ''}
                {item.active_bookings_count > 0
                  ? ` · ${item.active_bookings_count} en cours`
                  : ''}
              </Text>
            </View>

            {item.status === 'active' ? (
              <Badge label={tr('company_sites.actif')} variant="success" />
            ) : (
              <Badge label={item.status} variant="neutral" />
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('company_sites.aucun_local')}
            message="Ajoutez vos sites pour rattacher chaque intervention à une adresse."
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
    formulaire: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      marginBottom: spacing.md,
    },
    champ: {
      flex: 2,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
    },
    champVille: {
      flex: 1,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
    },
    placeholder: {
      color: t.textMuted,
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
