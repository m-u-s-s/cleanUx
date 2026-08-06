import React from 'react';
import { View, Text, TextInput, Alert, ScrollView, StyleSheet } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Local {
  id: number;
  name: string;
  code: string | null;
  city: string | null;
}

interface Metier {
  id: number;
  name: string;
}

/**
 * Une même prestation demandée pour plusieurs locaux d'un coup.
 *
 * Côté serveur, la demande s'appuie sur `bookings.parent_booking_id` — une colonne présente depuis
 * la migration initiale et restée inutilisée : une demande MÈRE porte l'intention commune, chaque
 * local reçoit sa réservation FILLE. Aucune table n'a été ajoutée pour cela.
 *
 * Le service refuse une demande dont aucun local n'est recevable : une mère sans fille serait une
 * coquille vide, invisible dans les listes et jamais traitée.
 */
export function CompanyMultiSiteRequestScreen() {
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const [selection, setSelection] = React.useState<number[]>([]);
  const [metierId, setMetierId] = React.useState<number | null>(null);
  const [date, setDate] = React.useState('');
  const [duree, setDuree] = React.useState('120');

  const { data: locaux } = useQuery<Local[]>({
    queryKey: ['client-company', 'sites'],
    queryFn: async () => (await apiClient.get('/client/company/sites')).data.data ?? [],
  });

  const { data: metiers } = useQuery<Metier[]>({
    queryKey: ['trades'],
    queryFn: async () => (await apiClient.get('/trades')).data.data ?? [],
  });

  const envoyer = useMutation({
    mutationFn: async () => {
      await apiClient.post('/client/company/multi-site-request', {
        site_ids: selection,
        trade_id: metierId,
        scheduled_at: date,
        duration_minutes: Number(duree) || 60,
      });
    },
    onSuccess: () => {
      setSelection([]);
      qc.invalidateQueries({ queryKey: ['bookings'] });
      Alert.alert('Demande envoyée', 'Chaque local sélectionné a reçu sa réservation.');
    },
    onError: () =>
      Alert.alert('Demande refusée', "Vérifiez la sélection, le métier et la date — ou vos droits."),
  });

  function basculer(id: number) {
    setSelection((actuelle) =>
      actuelle.includes(id) ? actuelle.filter((x) => x !== id) : [...actuelle, id],
    );
  }

  const pret = selection.length > 0 && metierId !== null && date.trim().length > 0;

  return (
    <Screen>
      <ScrollView>
        <Text style={styles.title}>Demande multi-locaux</Text>
        <Text style={styles.intro}>
          Une même prestation, planifiée d'un coup pour plusieurs de vos locaux.
        </Text>

        <Text style={styles.section}>Locaux concernés</Text>
        {(locaux ?? []).length === 0 ? (
          <EmptyState title="Aucun local" message="Ajoutez vos sites depuis l'espace société." />
        ) : (
          <View style={styles.choix}>
            {(locaux ?? []).map((l) => (
              <Button
                key={l.id}
                label={`${selection.includes(l.id) ? '✓ ' : ''}${l.name}`}
                size="sm"
                variant={selection.includes(l.id) ? 'primary' : 'ghost'}
                onPress={() => basculer(l.id)}
              />
            ))}
          </View>
        )}

        <Text style={styles.section}>Prestation</Text>
        <View style={styles.choix}>
          {(metiers ?? []).slice(0, 12).map((m) => (
            <Button
              key={m.id}
              label={m.name}
              size="sm"
              variant={metierId === m.id ? 'primary' : 'ghost'}
              onPress={() => setMetierId(m.id)}
            />
          ))}
        </View>

        <Text style={styles.section}>Quand</Text>
        <TextInput
          value={date}
          onChangeText={setDate}
          placeholder="AAAA-MM-JJ HH:MM"
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-date"
        />
        <TextInput
          value={duree}
          onChangeText={setDuree}
          keyboardType="number-pad"
          placeholder="Durée par local (min)"
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-duree"
        />

        <Button
          label={`Envoyer la demande${selection.length > 0 ? ` (${selection.length} local·aux)` : ''}`}
          onPress={() => pret && envoyer.mutate()}
          disabled={! pret || envoyer.isPending}
          fullWidth
        />
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
    intro: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginBottom: spacing.md,
    },
    section: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginTop: spacing.md,
      marginBottom: spacing.xs,
    },
    choix: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.xs,
    },
    champ: {
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
      marginBottom: spacing.sm,
    },
    placeholder: {
      color: t.textMuted,
    },
  });
