import React, { useState, useRef, useMemo } from 'react';
import { View, Text, FlatList, Dimensions, StyleSheet, useWindowDimensions } from 'react-native';
import { Button, Icon } from '@/ui';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import * as SecureStore from 'expo-secure-store';
import { useTraduction } from '@/i18n';

/**
 * La première ouverture, côté client.
 *
 * LE DISCOURS EST ADOSSÉ AU MOTEUR. Les trois écrans promettaient « réservez en 5 étapes »,
 * « scannez le QR code » et « payez en toute sécurité » : le parcours ne compte pas cinq étapes,
 * le client MONTRE son code plutôt qu'il ne le scanne, et « en toute sécurité » ne dit rien.
 * Chaque écran porte désormais un mécanisme réel et le chiffre qui le prouve.
 *
 * LE FOND SUIVAIT LA NUIT EN DUR. `colors.mode.showcase.*` peignait cet écran sombre même en
 * thème clair — le premier écran de l'application démentait donc le réglage de l'appareil.
 */
const ECRANS = [
  { cle: '1', icone: 'pricetag-outline' as const },
  { cle: '2', icone: 'navigate-outline' as const },
  { cle: '3', icone: 'keypad-outline' as const },
];

const ONBOARDING_KEY = 'onboarding_completed';

interface Props {
  onComplete: () => void;
}

export function OnboardingScreen({ onComplete }: Props) {
  const { t: tr } = useTraduction();
  const jetons = useThemeColors();
  const styles = useMemo(() => stylesFor(jetons), [jetons]);

  // La largeur d'un écran change à la rotation : la figer découpe les pages de travers.
  const { width } = useWindowDimensions();

  const [currentIndex, setCurrentIndex] = useState(0);
  const flatListRef = useRef<FlatList>(null);
  const dernier = currentIndex === ECRANS.length - 1;

  const handleNext = () => {
    if (!dernier) {
      flatListRef.current?.scrollToIndex({ index: currentIndex + 1 });
      setCurrentIndex(currentIndex + 1);
    } else {
      handleComplete();
    }
  };

  const handleComplete = async () => {
    try {
      await SecureStore.setItemAsync(ONBOARDING_KEY, 'true');
    } catch {
      // Stockage refusé : la présentation reviendra, ce qui vaut mieux que de la perdre.
    }
    onComplete();
  };

  return (
    <View style={styles.container} testID="onboarding-screen">
      <FlatList
        ref={flatListRef}
        data={ECRANS}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={(e) => {
          const idx = Math.round(e.nativeEvent.contentOffset.x / width);
          setCurrentIndex(idx);
        }}
        keyExtractor={(item) => item.cle}
        renderItem={({ item }) => (
          <View style={[styles.slide, { width }]}>
            {/* L'icône dans son halo remplace l'emoji : elle suit le thème, elle se
                redimensionne sans crénelage, et elle est lue par les lecteurs d'écran
                via le titre qui la suit plutôt que par un pictogramme muet. */}
            <View style={styles.halo}>
              <View style={styles.pastille}>
                <Icon name={item.icone} size={34} color={jetons.accent} />
              </View>
            </View>
            <Text style={styles.title} accessibilityRole="header">
              {tr(`onboarding.titre_${item.cle}`)}
            </Text>
            <Text style={styles.subtitle}>{tr(`onboarding.texte_${item.cle}`)}</Text>
          </View>
        )}
      />

      <View style={styles.footer}>
        <View
          style={styles.dots}
          accessibilityRole="progressbar"
          accessibilityLabel={tr('onboarding.progression')
            .replace(':courant', String(currentIndex + 1))
            .replace(':total', String(ECRANS.length))}
        >
          {ECRANS.map((e, i) => (
            <View key={e.cle} style={[styles.dot, i === currentIndex && styles.dotActive]} />
          ))}
        </View>

        <Button
          label={dernier ? tr('onboarding.commencer') : tr('onboarding.suivant')}
          onPress={handleNext}
          fullWidth
          size="lg"
          testID="onboarding-suivant"
        />
        {!dernier && (
          <Button label={tr('onboarding.passer')} onPress={handleComplete} variant="ghost" fullWidth />
        )}
      </View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, backgroundColor: t.bg },
  slide: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
  },
  /* Deux cercles concentriques : le halo pose l'accent sans le saturer, la pastille
     porte l'icône sur une surface qui garde son contraste dans les deux thèmes. */
  halo: {
    width: 132,
    height: 132,
    borderRadius: 66,
    backgroundColor: t.inputBg,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.xl,
  },
  pastille: {
    width: 84,
    height: 84,
    borderRadius: 42,
    backgroundColor: t.card,
    borderWidth: 1,
    borderColor: t.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    textAlign: 'center',
    marginBottom: spacing.sm,
  },
  subtitle: {
    fontSize: typography.fontSize.base,
    color: t.textSecondary,
    textAlign: 'center',
    lineHeight: 24,
    maxWidth: 340,
  },
  footer: {
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing['2xl'],
    gap: spacing.sm,
  },
  dots: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: spacing.sm,
    marginBottom: spacing.md,
  },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: t.border },
  dotActive: { backgroundColor: colors.accent.amber, width: 24 },
});

/**
 * L'IMPORT EST STATIQUE, ET C'EST LE POINT.
 *
 * En `await import()` dans un `try` dont le `catch` conclut « déjà vue », un import qui échoue
 * rend TOUJOURS vrai : la présentation ne peut alors plus jamais s'afficher, sans qu'aucune erreur
 * ne remonte. Le prestataire a payé exactement ce défaut.
 */
export async function hasCompletedOnboarding(): Promise<boolean> {
  try {
    return (await SecureStore.getItemAsync(ONBOARDING_KEY)) === 'true';
  } catch {
    // Stockage indisponible : on n'empêche pas d'entrer dans l'application.
    return true;
  }
}
