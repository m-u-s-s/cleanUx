import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { Icon, Screen } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * Le choix entre les ressources d'un module multi-modèles.
 *
 * POURQUOI CET ÉCRAN EXISTE. Cinq pages d'administration sont des tableaux de bord : « Opérations
 * B2B » gère contrats, ordres de travail et grilles tarifaires ; le risque montre évaluations et
 * blocages ; les contrats, documents et signatures. Le moteur sert un modèle par descripteur, et
 * l'annuaire n'ouvrait que le principal — les autres étaient écrits, corrects et inatteignables.
 *
 * IL NE S'INTERPOSE QUE LÀ OÙ IL SERT. Soixante-dix modules n'ont qu'une ressource et continuent de
 * s'ouvrir directement : imposer un écran de choix à une seule entrée ajouterait un toucher à
 * chaque ouverture, au bénéfice de cinq modules et au détriment de tous les autres.
 *
 * LES LIBELLÉS VIENNENT DU SERVEUR, pas d'une table ici. Une seconde liste de noms divergerait du
 * jour où un descripteur serait renommé, et l'écran annoncerait une ressource qui n'existe plus.
 */
export function ResourcePickerScreen() {
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<{ navigate: (screen: string, params?: object) => void }>();

  const route = useRoute<{
    key: string;
    name: string;
    params: { title: string; resources: string[] };
  }>();

  const { title, resources } = route.params;

  return (
    <Screen>
      <Text style={styles.intro}>
        Ce module couvre plusieurs domaines. Choisissez celui que vous voulez ouvrir.
      </Text>

      <FlatList
        data={resources}
        keyExtractor={(item) => item}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => (
          <Pressable
            onPress={() =>
              navigation.navigate('AdminResourceList', { resource: item, title: libelle(item) })
            }
            accessibilityRole="button"
            accessibilityLabel={`Ouvrir ${libelle(item)}`}
            style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
          >
            <Text style={styles.rowTitre}>{libelle(item)}</Text>
            <Icon name="chevron-forward" size={18} color={colors.surface[400]} />
          </Pressable>
        )}
        ListHeaderComponent={<Text style={styles.module}>{title}</Text>}
      />
    </Screen>
  );
}

/**
 * Un libellé lisible à partir d'une clé de ressource.
 *
 * `b2b-work-orders` devient « B2b work orders » — imparfait, et volontairement mécanique : une
 * table de traductions ici divergerait du serveur au premier renommage, et l'écran annoncerait
 * alors une ressource qui n'existe plus sous ce nom.
 */
function libelle(cle: string): string {
  const mots = cle.replace(/-/g, ' ');

  return mots.charAt(0).toUpperCase() + mots.slice(1);
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  intro: {
    ...typography.preset.subhead,
    color: t.textSecondary,
    paddingVertical: spacing.md,
  },
  module: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
    paddingBottom: spacing.sm,
    textTransform: 'uppercase',
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 60,
    paddingHorizontal: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  rowPressed: { backgroundColor: t.inputBg },
  rowTitre: { ...typography.preset.bodyReadable, color: t.text },
});
