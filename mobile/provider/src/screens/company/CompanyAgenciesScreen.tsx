import React, { useState } from 'react';
import { View, FlatList, Text, TextInput, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Agence {
  id: number;
  name: string;
  city: string | null;
  address: string | null;
  status: string;
  service_zone_id: number | null;
}

/**
 * LES IMPLANTATIONS DE LA SOCIÉTÉ — le dépôt de Bruxelles, l'antenne d'Anvers.
 *
 * À NE PAS CONFONDRE AVEC L'ÉCRAN « SITES DESSERVIS », qui liste les locaux du CLIENT : un
 * prestataire ne possède pas les immeubles où il intervient. Les deux se ressemblent — une adresse,
 * une ville — et n'ont rien à voir dans le domaine ; les confondre donnerait à une société un droit
 * sur les locaux de ses clients.
 *
 * Une société qui n'a qu'une implantation n'en déclare aucune, et rien ne change pour elle : le
 * rattachement reste vide, et le moteur de répartition n'accorde alors aucun point d'agence.
 */
export function CompanyAgenciesScreen() {
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const [nom, setNom] = useState('');
  const [ville, setVille] = useState('');

  const peutGerer = can(user, 'agencies.manage');

  const { data: agences, refetch, isRefetching } = useQuery<Agence[]>({
    queryKey: ['company', 'agencies'],
    queryFn: async () => (await apiClient.get('/provider/company/agencies')).data.data ?? [],
  });

  const creer = useMutation({
    mutationFn: async () =>
      apiClient.post('/provider/company/agencies', { name: nom.trim(), city: ville.trim() || null }),
    onSuccess: () => {
      setNom('');
      setVille('');
      qc.invalidateQueries({ queryKey: ['company', 'agencies'] });
    },
    onError: (erreur: any) =>
      Alert.alert(
        'Création refusée',
        erreur?.data?.message ?? "Votre rôle ne permet pas d'ouvrir une implantation.",
      ),
  });

  const archiver = useMutation({
    mutationFn: async (id: number) =>
      apiClient.patch(`/provider/company/agencies/${id}`, { status: 'archived' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'agencies'] }),
    onError: () => Alert.alert('Action refusée', 'Votre rôle ne permet pas cette action.'),
  });

  return (
    <Screen>
      <Text style={styles.title}>Implantations</Text>
      <Text style={styles.intro}>
        Vos propres dépôts et antennes — à distinguer des sites de vos clients, où vous intervenez.
      </Text>

      {peutGerer && (
        <View style={styles.formulaire}>
          <TextInput
            value={nom}
            onChangeText={setNom}
            placeholder="Nom de l'implantation"
            placeholderTextColor={styles.placeholder.color}
            style={styles.champ}
            testID="champ-nom-agence"
          />
          <TextInput
            value={ville}
            onChangeText={setVille}
            placeholder="Ville"
            placeholderTextColor={styles.placeholder.color}
            style={styles.champ}
            testID="champ-ville-agence"
          />
          <Button
            label="Créer"
            size="sm"
            fullWidth
            disabled={nom.trim().length === 0 || creer.isPending}
            onPress={() => creer.mutate()}
          />
        </View>
      )}

      <FlatList
        data={agences ?? []}
        keyExtractor={(a) => String(a.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`agence-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.name}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {[item.city, item.address].filter(Boolean).join(' · ') || 'Adresse non renseignée'}
              </Text>
            </View>

            {item.status === 'archived' ? (
              <Badge label="Archivée" variant="neutral" />
            ) : (
              peutGerer && (
                <Button
                  label="Archiver"
                  size="sm"
                  variant="ghost"
                  onPress={() => archiver.mutate(item.id)}
                />
              )
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucune implantation"
            message="Une société mono-site n'en a pas besoin : la répartition fonctionne sans."
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
    },
    intro: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginBottom: spacing.md,
    },
    formulaire: {
      gap: spacing.xs,
      marginBottom: spacing.md,
    },
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
