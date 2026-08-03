import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { Screen } from '@/ui';
import { colors, spacing, typography } from '@/theme';

/**
 * Filet de sécurité du lot A.
 *
 * L'annuaire ne rend navigables que les modules déclarés couverts dans le registre serveur, et
 * aucun ne l'est encore : cet écran n'est donc atteignable que si quelqu'un bascule un module sur
 * `descriptor` ou `screen` avant que le moteur de console (lot B) n'existe. Il dit alors la
 * vérité plutôt que de faire tomber l'application sur une route inconnue.
 */
export function AdminResourceScreen({ route }: { route: { params?: { title?: string } } }) {
  const title = route.params?.title ?? 'Ce module';

  return (
    <Screen>
      <View style={styles.container}>
        <Text style={styles.title}>{title} n’est pas encore servi ici</Text>
        <Text style={styles.body}>
          Le module est déclaré couvert dans le registre, mais l’écran qui doit le rendre n’est pas
          encore livré. Il reste disponible sur le web en attendant.
        </Text>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', paddingHorizontal: spacing.lg },
  title: {
    ...typography.preset.headline,
    color: colors.surface[900],
    marginBottom: spacing.sm,
    textAlign: 'center',
  },
  body: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    textAlign: 'center',
    lineHeight: 20,
  },
});
