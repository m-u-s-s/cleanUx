import React, { useState } from 'react';
import { FlatList, View, Text, StyleSheet, Pressable, Alert, Image } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Badge, Skeleton, EmptyState, ErrorState, Button, TextInput } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/**
 * Le serveur plafonne a cinq (`PreuvesDeLitige::NOMBRE_MAX`) et n'accepte que des images.
 * On le dit ici plutot que de laisser partir un envoi voue au 422.
 */
const MAX_PREUVES = 5;

const CATEGORIES: { value: string; label: string }[] = [
  { value: 'quality', label: 'Qualité' },
  { value: 'no_show', label: 'Absence' },
  { value: 'payment', label: 'Paiement' },
  { value: 'damage', label: 'Dommage' },
  { value: 'safety', label: 'Sécurité' },
  { value: 'communication', label: 'Communication' },
  { value: 'other', label: 'Autre' },
];

export function DisputesScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const queryClient = useQueryClient();
  const { data, isLoading, isError, refetch, isRefetching } = useQuery({
    queryKey: ['disputes'],
    queryFn: async () => {
      const res = await apiClient.get('/client/disputes');
      return res.data.data ?? [];
    },
  });

  const [showForm, setShowForm] = useState(false);
  const [subject, setSubject] = useState('');
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState<string | null>(null);
  const [preuves, setPreuves] = useState<string[]>([]);

  const ajouterUnePreuve = async () => {
    if (preuves.length >= MAX_PREUVES) {
      Alert.alert(tr('disputes.cinq_photos_au_maximum'), tr('disputes.retirez_en_une_pour_en'));
      return;
    }

    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permission.granted) {
      Alert.alert(tr('disputes.permission_requise'), tr('disputes.autorisez_lacces_aux_photos_pour'));
      return;
    }

    const choix = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.8 });
    const asset = !choix.canceled ? choix.assets[0] : null;

    if (asset) {
      setPreuves(actuelles => [...actuelles, asset.uri]);
    }
  };

  const retirerUnePreuve = (uri: string) =>
    setPreuves(actuelles => actuelles.filter(autre => autre !== uri));

  const create = useMutation({
    mutationFn: async () => {
      // SANS PREUVE, PAS DE MULTIPART : le corps JSON reste la forme normale de cet appel.
      if (preuves.length === 0) {
        return (await apiClient.post('/client/disputes', { subject, description, category })).data;
      }

      const corps = new FormData();
      corps.append('subject', subject);
      corps.append('description', description);
      corps.append('category', category ?? '');

      preuves.forEach((uri, index) => {
        // La forme { uri, name, type } est celle qu'attend FormData en React Native pour un
        // fichier local ; un Blob ne fonctionne pas ici.
        corps.append('attachments[]', { uri, name: `preuve-${index + 1}.jpg`, type: 'image/jpeg' } as never);
      });

      return (
        await apiClient.post('/client/disputes', corps, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      ).data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['disputes'] });
      setShowForm(false);
      setSubject('');
      setDescription('');
      setCategory(null);
      setPreuves([]);
      Alert.alert(tr('disputes.litige_ouvert'), tr('disputes.notre_equipe_va_le_traiter'));
    },
    onError: () => Alert.alert(tr('disputes.echec'), tr('disputes.le_litige_n_a_pas')),
  });

  const canSubmit = subject.trim().length >= 3 && description.trim().length >= 10 && !!category && !create.isPending;

  if (isError) return <Screen><ErrorState message="Impossible de charger vos litiges." onRetry={refetch} /></Screen>;

  return (
    <Screen>
      <View style={styles.headerRow}>
        <Text style={styles.title}>{tr('disputes.mes_litiges')}</Text>
        <Button label={showForm ? 'Fermer' : 'Ouvrir un litige'} size="sm" onPress={() => setShowForm(v => !v)} />
      </View>

      {showForm && (
        <View style={styles.form}>
          <TextInput label={tr('disputes.sujet')} placeholder={tr('disputes.sujet')} value={subject} onChangeText={setSubject} />
          <TextInput
            label={tr('disputes.description')}
            placeholder={tr('disputes.decrivez_le_probleme')}
            value={description}
            onChangeText={setDescription}
            multiline
          />
          <Text style={styles.formLabel}>{tr('disputes.categorie')}</Text>
          <View style={styles.chips}>
            {CATEGORIES.map(cat => (
              <Pressable
                key={cat.value}
                onPress={() => setCategory(cat.value)}
                style={[styles.chip, category === cat.value && styles.chipActive]}
              >
                <Text style={[styles.chipText, category === cat.value && styles.chipTextActive]}>{cat.label}</Text>
              </Pressable>
            ))}
          </View>
          <Text style={styles.formLabel}>{tr('disputes.photos_facultatif')}</Text>
          <View style={styles.preuves}>
            {preuves.map(uri => (
              <Pressable
                key={uri}
                onPress={() => retirerUnePreuve(uri)}
                accessibilityLabel={tr('disputes.retirer_cette_photo')}
                style={styles.preuve}
              >
                <Image source={{ uri }} style={styles.preuveImage} />
              </Pressable>
            ))}

            {preuves.length < MAX_PREUVES && (
              <Button label={tr('disputes.ajouter_une_photo')} size="sm" variant="outline" onPress={ajouterUnePreuve} />
            )}
          </View>

          <Button label={tr('disputes.envoyer')} onPress={() => create.mutate()} disabled={!canSubmit} loading={create.isPending} />
        </View>
      )}

      {isLoading ? (
        <Skeleton width="100%" height={80} />
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={(item: any) => String(item.id)}
          renderItem={({ item }: { item: any }) => (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.cardTitle}>Litige #{item.id}</Text>
                <Badge
                  label={item.status}
                  variant={item.status === 'resolved' ? 'success' : 'warning'}
                />
              </View>
              <Text style={styles.cardDesc}>{item.reason ?? item.description ?? ''}</Text>
            </View>
          )}
          onRefresh={refetch}
          refreshing={isRefetching}
          ListEmptyComponent={<EmptyState title={tr('disputes.aucun_litige')} message="Vous n'avez aucun litige en cours." icon="shield-outline" />}
        />
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  form: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.md,
    gap: spacing.sm,
  },
  formLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  preuves: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, alignItems: 'center' },
  preuve: { borderRadius: radius.md, overflow: 'hidden' },
  preuveImage: { width: 64, height: 64 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  chip: {
    paddingVertical: spacing.xs,
    paddingHorizontal: spacing.sm,
    // `radius.full` n'existe pas dans l'échelle : elle s'arrête à `pill`. La valeur était donc
    // `undefined` et les puces s'affichaient à angles droits, sans que rien ne le signale.
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: t.border,
  },
  chipActive: { backgroundColor: colors.brand[500], borderColor: colors.brand[500] },
  chipText: { fontSize: typography.fontSize.xs, color: t.text },
  chipTextActive: { color: t.card, fontWeight: typography.fontWeight.semibold },
  card: {
    padding: spacing.md,
    backgroundColor: t.card,
    borderRadius: radius.md,
    marginBottom: spacing.sm,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.xs,
  },
  cardTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  cardDesc: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: spacing.xs },
  empty: { color: t.textMuted, textAlign: 'center', marginTop: spacing.xl },
});
