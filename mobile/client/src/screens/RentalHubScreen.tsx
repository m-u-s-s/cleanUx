import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { EmptyState, ErrorState, GlassSurface, Icon, Screen, Skeleton } from '@/ui';
import { CLES_LOCATION, modulesChoisis, useModuleCatalogue } from '@/modules';
import type { ModuleDuCatalogue } from '@/modules';
import { radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';
import type { RootStackParamList } from '@/navigation/types';

/**
 * L'ONGLET « LOCATION » — la porte d'entrée du module de location entre membres.
 *
 * IL REMPLACE « EXPLORER » DANS LA BARRE. La recherche de prestataires reste atteignable depuis le
 * profil : un écran qu'on retire de la barre sans lui rouvrir une porte devient un écran orphelin,
 * et ce dépôt en a déjà payé sept.
 *
 * LES CASES VIENNENT DU CATALOGUE, PAS D'UNE LISTE ÉCRITE ICI. Chemin, libellé et icône sont
 * décidés par `config/modules.php` ; une clé absente veut dire que ce compte n'a pas ce module, et
 * la case ne s'affiche simplement pas. C'est ce qui fait qu'un compte en société — dont les clés
 * portent un autre préfixe — voit le même écran sans condition écrite ici.
 */
export function RentalHubScreen() {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const { data, isLoading, isError, refetch } = useModuleCatalogue();

  const ouvrir = (module: ModuleDuCatalogue) =>
    navigation.navigate('EmbeddedModule', { path: module.path, title: module.label });

  const louer = modulesChoisis(data, [...CLES_LOCATION.louer, ...CLES_LOCATION.louerLogement]);
  const mettre = modulesChoisis(data, [...CLES_LOCATION.mesVehicules, ...CLES_LOCATION.mesLogements]);
  const miennes = modulesChoisis(data, [...CLES_LOCATION.mesLocations]);

  if (isLoading) {
    return (
      <Screen testID="location-chargement">
        <View style={styles.squelettes}>
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} width="100%" height={72} />
          ))}
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState message={tr('modules.le_catalogue_des_modules_n_a_pas')} onRetry={() => void refetch()} />
      </Screen>
    );
  }

  const rien = louer.length + mettre.length + miennes.length === 0;

  const section = (titre: string, modules: ModuleDuCatalogue[]) =>
    modules.length === 0 ? null : (
      <View key={titre} style={styles.section}>
        <Text style={styles.sectionTitre}>{titre}</Text>
        {modules.map(module => (
          <Pressable
            key={module.key}
            accessibilityRole="button"
            accessibilityLabel={module.label}
            onPress={() => ouvrir(module)}
            testID={`location-${module.key}`}
          >
            <GlassSurface strong radius={radius.lg} style={styles.case}>
              <Text style={styles.emoji}>{module.icon}</Text>
              <Text style={styles.libelle} numberOfLines={2}>
                {module.label}
              </Text>
              <Icon name="chevron-forward" size={18} color={theme.action} />
            </GlassSurface>
          </Pressable>
        ))}
      </View>
    );

  return (
    <Screen testID="location-screen">
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.contenu}>
        <Text style={styles.titre}>{tr('location.titre')}</Text>
        <Text style={styles.intro}>{tr('location.intro')}</Text>

        {rien ? (
          <EmptyState title={tr('location.titre')} message={tr('location.indisponible')} />
        ) : (
          <>
            {section(tr('location.mettre_en_location'), mettre)}
            {section(tr('location.louer'), louer)}
            {section(tr('location.mes_locations'), miennes)}
          </>
        )}
      </ScrollView>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    contenu: { gap: spacing.lg, paddingBottom: spacing.xl },
    squelettes: { gap: spacing.sm, paddingTop: spacing.md },
    titre: {
      fontSize: typography.fontSize['2xl'],
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginTop: spacing.md,
    },
    intro: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: -spacing.md },
    section: { gap: spacing.sm },
    sectionTitre: {
      fontSize: typography.fontSize.xs,
      fontWeight: typography.fontWeight.semibold,
      letterSpacing: typography.letterSpacing.wide,
      textTransform: 'uppercase',
      color: t.textMuted,
    },
    case: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.md,
      paddingHorizontal: spacing.md,
      paddingVertical: spacing.md,
    },
    emoji: { fontSize: 24 },
    libelle: { flex: 1, fontSize: typography.fontSize.base, fontWeight: typography.fontWeight.medium, color: t.textOnGlass },
  });
