import React from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ErrorState, Screen, Skeleton } from '@/ui';
import { radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useModuleCatalogue } from './useModuleCatalogue';
import type { ModuleDuCatalogue } from './useModuleCatalogue';

/**
 * LE RÉPERTOIRE DES MODULES, EN NATIF.
 *
 * Le web a sa page Modules depuis le registre unique ; le mobile n'avait rien d'équivalent : les
 * applications exposaient une poignée d'écrans et laissaient le reste — 37 modules pour un client,
 * 90 pour un administrateur — inatteignable depuis le téléphone.
 *
 * IL EST PARTAGÉ ENTRE LES DEUX APPLICATIONS. Les cases changent avec le rôle, pas le rendu : ce
 * qui diffère vient du serveur, qui déduit le contexte du jeton. Écrire deux écrans identiques
 * aurait été deux occasions de les faire diverger.
 *
 * L'OUVERTURE PASSE PAR L'HÔTE WEBVIEW, et c'est ce qui rend le répertoire complet aujourd'hui
 * plutôt que dans six mois : chaque module a une page web qui fonctionne. Un module migré en natif
 * se contentera d'être intercepté ici, sans que le catalogue change.
 */
export function ModulesScreen({
  onOuvrir,
}: {
  /** Ouvre un module. L'appelant décide de l'hôte — chaque application a le sien. */
  onOuvrir: (module: ModuleDuCatalogue) => void;
}) {
  const theme = useThemeColors();
  const styles = stylesFor(theme);

  const { data, isLoading, isError, refetch, isRefetching } = useModuleCatalogue();

  if (isLoading) {
    return (
      <Screen>
        <View testID="modules-chargement" style={styles.squelettes}>
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} width="100%" height={64} />
          ))}
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState
          message="Le catalogue des modules n’a pas pu être chargé."
          onRetry={() => {
            void refetch();
          }}
        />
      </Screen>
    );
  }

  const groupes = data?.groups ?? [];

  return (
    <Screen testID="modules-screen">
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.contenu}
        refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={() => void refetch()} />}
      >
        <View>
          <Text style={styles.titre}>Modules</Text>
          <Text style={styles.sousTitre}>Tout ce que cet espace sait faire, rangé par fonction.</Text>
        </View>

        {groupes.map((groupe) => (
          <View key={groupe.category}>
            <Text style={styles.sectionTitre}>{groupe.label}</Text>

            <View style={styles.grille}>
              {groupe.modules.map((module) => (
                <Pressable
                  key={module.key}
                  onPress={() => onOuvrir(module)}
                  accessibilityRole="button"
                  accessibilityLabel={module.label}
                  style={({ pressed }) => [styles.carte, pressed && styles.cartePressee]}
                >
                  <Text style={styles.carteIcone}>{module.icon}</Text>
                  <Text style={styles.carteLabel} numberOfLines={2}>
                    {module.label}
                  </Text>
                </Pressable>
              ))}
            </View>
          </View>
        ))}

        {groupes.length === 0 ? (
          <Text style={styles.vide}>Aucun module disponible pour ce compte.</Text>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  contenu: { gap: spacing.lg, paddingBottom: spacing.xl },
  squelettes: { gap: spacing.sm },
  titre: { ...typography.preset.headline, color: t.text },
  sousTitre: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: spacing.xs },
  sectionTitre: {
    fontSize: typography.fontSize.xs,
    fontWeight: '700',
    letterSpacing: 0.8,
    textTransform: 'uppercase',
    color: t.textMuted,
    marginBottom: spacing.sm,
  },
  grille: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  carte: {
    flexGrow: 1,
    flexBasis: '45%',
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: t.inputBg,
  },
  cartePressee: { opacity: 0.7 },
  carteIcone: { fontSize: 20 },
  carteLabel: { flex: 1, fontSize: typography.fontSize.sm, fontWeight: '600', color: t.text },
  vide: { fontSize: typography.fontSize.sm, color: t.textSecondary, textAlign: 'center' },
});
