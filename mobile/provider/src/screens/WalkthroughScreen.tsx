import React, { useState, useRef, useMemo } from 'react';
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
import { View, Text, FlatList, StyleSheet, useWindowDimensions } from 'react-native';
import { Button, Icon } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/**
 * La première ouverture, côté prestataire.
 *
 * CE QUE LES TROIS ÉCRANS PROMETTAIENT ÉTAIT FAUX. « Améliorez votre score pour obtenir plus de
 * missions » et « Débloquez des bonus avec vos badges » : mesuré dans le moteur, les badges
 * n'entrent dans AUCUNE des neuf mesures du classement, et aucun bonus ne leur est attaché.
 * « Plus vous réalisez de missions, plus vos revenus augmentent » est une promesse de volume que
 * rien ne garantit.
 *
 * Ce qui les remplace se vérifie : la commission et son plancher, l'offre unique et le refus sans
 * justification, les métiers et zones déclarés à l'inscription.
 *
 * LE FOND SUIVAIT LA NUIT EN DUR, comme côté client : `colors.mode.showcase.*` peignait cet écran
 * sombre même en thème clair.
 */
const ECRANS = [
  { cle: '1', icone: 'cash-outline' as const },
  { cle: '2', icone: 'notifications-outline' as const },
  { cle: '3', icone: 'construct-outline' as const },
];

const WALKTHROUGH_KEY = 'provider_walkthrough_completed';

interface Props {
  onComplete: () => void;
}

export function WalkthroughScreen({ onComplete }: Props) {
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
    <View style={styles.container} testID="walkthrough-screen">
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
            {/* L'icône dans son halo remplace l'emoji : elle suit le thème et se
                redimensionne sans crénelage. */}
            <View style={styles.halo}>
              <View style={styles.pastille}>
                <Icon name={item.icone} size={34} color={jetons.accent} />
              </View>
            </View>
            <Text style={styles.title} accessibilityRole="header">
              {tr(`walkthrough.titre_${item.cle}`)}
            </Text>
            <Text style={styles.subtitle}>{tr(`walkthrough.texte_${item.cle}`)}</Text>
          </View>
        )}
      />

      <View style={styles.footer}>
        <View
          style={styles.dots}
          accessibilityRole="progressbar"
          accessibilityLabel={tr('walkthrough.progression')
            .replace(':courant', String(currentIndex + 1))
            .replace(':total', String(ECRANS.length))}
        >
          {ECRANS.map((e, i) => (
            <View key={e.cle} style={[styles.dot, i === currentIndex && styles.dotActive]} />
          ))}
        </View>

        <Button
          label={dernier ? tr('walkthrough.commencer') : tr('walkthrough.suivant')}
          onPress={handleNext}
          fullWidth
          size="lg"
          testID="walkthrough-suivant"
        />
        {!dernier && (
          <Button label={tr('walkthrough.passer')} onPress={handleComplete} variant="ghost" fullWidth />
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

export async function hasCompletedWalkthrough(): Promise<boolean> {
  try {
    return (await SecureStore.getItemAsync(WALKTHROUGH_KEY)) === 'true';
  } catch {
    // Stockage indisponible : on ne bloque pas l'accès à l'application pour une présentation.
    return true;
  }
}
