import React from 'react';
import { View, FlatList, Text, TextInput, Alert, StyleSheet } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface RendezVous {
  id: number;
  scheduled_at: string | null;
  status: string;
  site: string | null;
  signer: string | null;
  notes: string | null;
}

interface Local {
  id: number;
  name: string;
}

const LIBELLES_STATUT: Record<string, string> = {
  scheduled: 'Planifié',
  completed: 'Signé',
  cancelled: 'Annulé',
};

/**
 * Les signatures de contrat sur place, en natif.
 *
 * L'API refuse une date passée, et refuse EXPLICITEMENT un local qui n'appartient pas à la
 * société — plutôt que de basculer silencieusement le rendez-vous « à distance », ce qui
 * changerait la demande sans le dire.
 */
export function CompanySigningAppointmentsScreen() {
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const [date, setDate] = React.useState('');
  const [notes, setNotes] = React.useState('');
  const [siteId, setSiteId] = React.useState<number | null>(null);

  const { data: rendezVous, refetch, isRefetching } = useQuery<RendezVous[]>({
    queryKey: ['client-company', 'signing-appointments'],
    queryFn: async () => (await apiClient.get('/client/company/signing-appointments')).data.data ?? [],
  });

  const { data: locaux } = useQuery<Local[]>({
    queryKey: ['client-company', 'sites'],
    queryFn: async () => (await apiClient.get('/client/company/sites')).data.data ?? [],
  });

  const planifier = useMutation({
    mutationFn: async () => {
      await apiClient.post('/client/company/signing-appointments', {
        scheduled_at: date,
        organization_site_id: siteId,
        notes: notes !== '' ? notes : null,
      });
    },
    onSuccess: () => {
      setNotes('');
      qc.invalidateQueries({ queryKey: ['client-company', 'signing-appointments'] });
    },
    onError: () =>
      Alert.alert('Rendez-vous impossible', 'Vérifiez la date choisie et le local sélectionné.'),
  });

  return (
    <Screen>
      <Text style={styles.title}>Signatures sur place</Text>

      <View style={styles.formulaire}>
        <TextInput
          value={date}
          onChangeText={setDate}
          placeholder="Date et heure (AAAA-MM-JJ HH:MM)"
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-date"
        />
        <TextInput
          value={notes}
          onChangeText={setNotes}
          placeholder="Note (facultatif)"
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-notes"
        />

        {/* Sans local choisi, la signature est planifiée à distance — c'est explicite. */}
        <View style={styles.locaux}>
          <Button
            label="À distance"
            size="sm"
            variant={siteId === null ? 'primary' : 'ghost'}
            onPress={() => setSiteId(null)}
          />
          {(locaux ?? []).map((l) => (
            <Button
              key={l.id}
              label={l.name}
              size="sm"
              variant={siteId === l.id ? 'primary' : 'ghost'}
              onPress={() => setSiteId(l.id)}
            />
          ))}
        </View>

        <Button
          label="Planifier"
          onPress={() => date.trim() && planifier.mutate()}
          disabled={planifier.isPending || date.trim().length === 0}
          fullWidth
        />
      </View>

      <FlatList
        data={rendezVous ?? []}
        keyExtractor={(r) => String(r.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`rdv-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.scheduled_at
                  ? new Date(item.scheduled_at).toLocaleString('fr-FR', {
                      day: '2-digit',
                      month: 'short',
                      hour: '2-digit',
                      minute: '2-digit',
                    })
                  : 'Date inconnue'}
                {item.site ? ` — ${item.site}` : ' — à distance'}
              </Text>
              {item.notes ? (
                <Text style={styles.detail} numberOfLines={1}>
                  {item.notes}
                </Text>
              ) : null}
            </View>

            <Badge
              label={LIBELLES_STATUT[item.status] ?? item.status}
              variant={item.status === 'completed' ? 'brand' : 'neutral'}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun rendez-vous"
            message="Planifiez une signature pour formaliser un contrat en présence."
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
      gap: spacing.sm,
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
    placeholder: {
      color: t.textMuted,
    },
    locaux: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.xs,
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
