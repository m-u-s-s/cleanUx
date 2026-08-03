import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Button, DetailRow, Divider, ErrorState, Screen, Skeleton } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { readServerErrors, useResourceAction, useResourceDelete, useResourceDetail, useResourceIndex } from './hooks';
import { formatCell } from './format';
import type { ResourceAction, ResourceColumn } from './types';

interface Params {
  resource: string;
  title: string;
  id: string | number;
}

/**
 * Le détail générique d'une ligne, et les actions qu'on peut y lancer.
 *
 * LA CONFIRMATION D'UNE ACTION DESTRUCTIVE EST TENUE ICI. Le serveur annonce `destructive` et le
 * texte à afficher ; c'est l'interface qui demande. Le serveur ne peut pas savoir si le doigt a
 * glissé — mais il peut dire ce que l'action détruit, et c'est ce texte-là qu'on montre, jamais
 * un « Êtes-vous sûr ? » générique qu'on valide sans lire.
 */
export function ResourceDetailScreen({ route }: { route: { params: Params } }) {
  const { resource, title, id } = route.params;
  const navigation = useNavigation<{ navigate: (s: string, p?: object) => void; goBack: () => void }>();

  const { data: row, isLoading, isError, refetch } = useResourceDetail(resource, id);
  // Le descripteur vit avec la liste : le relire ici évite un second contrat à maintenir.
  const { data: liste } = useResourceIndex(resource);
  const descripteur = liste?.pages[0]?.resource;

  const action = useResourceAction(resource);
  const suppression = useResourceDelete(resource);
  const [erreur, setErreur] = useState<string | null>(null);

  if (isLoading) {
    return (
      <Screen>
        <View testID="resource-detail-loading" style={{ paddingTop: spacing.md, gap: spacing.sm }}>
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} width="100%" height={44} />
          ))}
        </View>
      </Screen>
    );
  }

  if (isError || !row) {
    return (
      <Screen>
        <ErrorState
          message="Cet élément n’a pas pu être chargé."
          onRetry={() => {
            void refetch();
          }}
        />
      </Screen>
    );
  }

  const colonnes: ResourceColumn[] = descripteur?.columns ?? [];
  const modifiable = (descripteur?.form.length ?? 0) > 0;

  const lancer = (a: ResourceAction) => {
    const executer = () => {
      setErreur(null);
      action.mutate(
        { id, action: a.key },
        { onError: (e) => setErreur(readServerErrors(e).message) },
      );
    };

    if (!a.destructive) {
      executer();

      return;
    }

    Alert.alert(a.label, a.confirm ?? '', [
      { text: 'Annuler', style: 'cancel' },
      { text: a.label, style: 'destructive', onPress: executer },
    ]);
  };

  return (
    <Screen>
      <ScrollView showsVerticalScrollIndicator={false}>
        <Text style={styles.heading}>{title}</Text>

        {colonnes.map((column) => (
          <DetailRow
            key={column.key}
            label={column.label}
            value={formatCell(row[column.key], column.type)}
          />
        ))}

        {/* Les champs que le détail porte en plus des colonnes de liste. */}
        {Object.keys(row)
          .filter((key) => key !== 'id' && !colonnes.some((c) => c.key === key))
          .map((key) => (
            <DetailRow key={key} label={key} value={formatCell(row[key], 'text')} />
          ))}

        {erreur ? <Text style={styles.erreur}>{erreur}</Text> : null}

        <Divider />

        <View style={styles.actions}>
          {modifiable ? (
            <Button
              label="Modifier"
              variant="secondary"
              onPress={() => navigation.navigate('AdminResourceForm', { resource, title, id })}
            />
          ) : null}

          {(descripteur?.actions ?? []).map((a) => (
            <Button
              key={a.key}
              label={a.label}
              variant={a.destructive ? 'danger' : 'secondary'}
              loading={action.isPending}
              onPress={() => lancer(a)}
            />
          ))}

          {modifiable ? (
            <Button
              label="Supprimer"
              variant="danger"
              loading={suppression.isPending}
              onPress={() =>
                Alert.alert('Supprimer', 'Cet élément sera définitivement retiré.', [
                  { text: 'Annuler', style: 'cancel' },
                  {
                    text: 'Supprimer',
                    style: 'destructive',
                    onPress: () =>
                      suppression.mutate(id, {
                        onSuccess: () => navigation.goBack(),
                        onError: (e) => setErreur(readServerErrors(e).message),
                      }),
                  },
                ])
              }
            />
          ) : null}
        </View>
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  heading: {
    ...typography.preset.headline,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  actions: { gap: spacing.sm, paddingVertical: spacing.md },
  erreur: {
    color: colors.danger[500],
    fontSize: typography.fontSize.sm,
    paddingVertical: spacing.sm,
  },
});
