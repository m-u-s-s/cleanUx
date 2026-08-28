import React, { useState, useRef } from 'react';
/*
 * IMPORT STATIQUE, comme `shared/storage/secureStore.ts` — et non `await import()`.
 *
 * Les deux accès de cet écran chargeaient le module dynamiquement, dans un `try` dont le `catch`
 * conclut « déjà vu, on saute la présentation ». Le jour où la forme du module ne se prête pas à
 * cet appel, `getItemAsync` est `undefined`, l'appel lève, et le témoin répond TOUJOURS `true` :
 * la présentation ne peut alors plus jamais s'afficher, sans qu'aucune erreur ne remonte.
 *
 * C'est exactement ce qui s'est produit ici, et c'est le seul endroit du dépôt à employer cette
 * forme. Le reste du code importe le module statiquement, et fonctionne.
 */
import * as SecureStore from 'expo-secure-store';
import { View, Text, FlatList, Dimensions, StyleSheet } from 'react-native';
import { Button } from '@/ui';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

const { width } = Dimensions.get('window');

const SLIDES = [
  {
    title: 'Recevez des missions',
    subtitle: 'Acceptez des missions adaptées à vos compétences et votre localisation. Gérez votre planning en toute liberté.',
    emoji: '🔔',
  },
  {
    title: 'Suivez votre progression',
    subtitle: 'Consultez vos statistiques, badges et évaluations clients. Améliorez votre score pour obtenir plus de missions.',
    emoji: '📈',
  },
  {
    title: 'Gagnez plus',
    subtitle: 'Plus vous réalisez de missions de qualité, plus vos revenus augmentent. Débloquez des bonus avec vos badges.',
    emoji: '💰',
  },
];

const WALKTHROUGH_KEY = 'provider_walkthrough_completed';

interface Props {
  onComplete: () => void;
}

export function WalkthroughScreen({ onComplete }: Props) {
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
      await SecureStore.setItemAsync(WALKTHROUGH_KEY, 'true');
    } catch (erreur) {
      /*
       * UN CATCH MUET EST CE QUI A MASQUÉ CE DÉFAUT.
       *
       * Si le drapeau n'est pas écrit, la présentation revient à CHAQUE lancement — et rien, nulle
       * part, ne dit pourquoi. On laisse entrer quand même (ce n'est jamais bloquant), mais on le
       * dit.
       */
      console.warn('[walkthrough] drapeau non enregistré :', erreur);
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
          <Button label={tr('walkthrough.passer')} onPress={handleComplete} variant="ghost" fullWidth />
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

export async function hasCompletedWalkthrough(): Promise<boolean> {
  try {
    return (await SecureStore.getItemAsync(WALKTHROUGH_KEY)) === 'true';
  } catch {
    // Stockage indisponible : on ne bloque pas l'accès à l'application pour une présentation.
    return true;
  }
}
