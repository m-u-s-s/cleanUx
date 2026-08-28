import React, { useState, useRef } from 'react';
import { View, Text, FlatList, Dimensions, StyleSheet } from 'react-native';
import { Button } from '@/ui';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import * as SecureStore from 'expo-secure-store';
import { useTraduction } from '@/i18n';

const { width } = Dimensions.get('window');

const SLIDES = [
  {
    title: 'Bienvenue sur Brio',
    subtitle: 'Trouvez le prestataire idéal pour tous vos besoins : nettoyage, peinture, babysitting et plus encore.',
    emoji: '👋',
  },
  {
    title: 'Réservez en 5 étapes',
    subtitle: 'Choisissez un service, précisez vos besoins, sélectionnez une date et confirmez. Simple et rapide.',
    emoji: '📅',
  },
  {
    title: 'Suivez en temps réel',
    subtitle: 'Suivez votre prestataire en direct sur la carte, scannez le QR code et payez en toute sécurité.',
    emoji: '📍',
  },
];

const ONBOARDING_KEY = 'onboarding_completed';

interface Props {
  onComplete: () => void;
}

export function OnboardingScreen({ onComplete }: Props) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const [currentIndex, setCurrentIndex] = useState(0);
  const flatListRef = useRef<FlatList>(null);

  const handleNext = () => {
    if (currentIndex < SLIDES.length - 1) {
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
    <View style={styles.container}>
      <FlatList
        ref={flatListRef}
        data={SLIDES}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        scrollEnabled={true}
        onMomentumScrollEnd={(e) => {
          const idx = Math.round(e.nativeEvent.contentOffset.x / width);
          setCurrentIndex(idx);
        }}
        keyExtractor={(_, i) => String(i)}
        renderItem={({ item }) => (
          <View style={[styles.slide, { width }]}>
            <Text style={styles.emoji}>{item.emoji}</Text>
            <Text style={styles.title}>{item.title}</Text>
            <Text style={styles.subtitle}>{item.subtitle}</Text>
          </View>
        )}
      />
      <View style={styles.footer}>
        <View style={styles.dots}>
          {SLIDES.map((_, i) => (
            <View key={i} style={[styles.dot, i === currentIndex && styles.dotActive]} />
          ))}
        </View>
        <Button
          label={currentIndex < SLIDES.length - 1 ? 'Suivant' : 'Commencer'}
          onPress={handleNext}
          fullWidth
          size="lg"
        />
        {currentIndex < SLIDES.length - 1 && (
          <Button label={tr('onboarding.passer')} onPress={handleComplete} variant="ghost" fullWidth />
        )}
      </View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.mode.showcase.night },
  slide: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
  },
  emoji: { fontSize: 64, marginBottom: spacing.xl },
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.mode.showcase.text,
    textAlign: 'center',
    marginBottom: spacing.sm,
  },
  subtitle: {
    fontSize: typography.fontSize.base,
    color: colors.mode.showcase.muted,
    textAlign: 'center',
    lineHeight: 24,
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
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: t.textSecondary },
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
