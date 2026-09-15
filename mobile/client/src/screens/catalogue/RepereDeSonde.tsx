import React, { useEffect } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';
import { GlassSurface } from '@/ui';
import { animation, radius, spacing, typography } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';
import { HAUTEUR_DE_REPERE } from '@/catalogue';
import type { RepereDeSonde as Repere } from '@/catalogue';

/** La largeur de l'axe central : le pointillé de `LigneDeSonde` passe en son milieu. */
const AXE = spacing.xl;

type Moitie = 'gauche' | 'droite';

export interface RepereDeSondeProps {
  repere: Repere;
  choisi: boolean;
  libellePrix: string;
  mouvementReduit: boolean;
  onChoisir: () => void;
}

/**
 * UN REPÈRE DE LA SONDE : le nœud sur la ligne, le fil, la case de verre.
 *
 * Les cases d'un secteur restent du même côté et le secteur suivant passe de l'autre ; son nom se
 * pose en face, sur la moitié libre. Toute la rangée est la cible tactile : 88 pt de haut.
 */
export function RepereDeSonde({ repere, choisi, libellePrix, mouvementReduit, onChoisir }: RepereDeSondeProps) {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const { metier, cote, etiquetteSecteur } = repere;

  const moitieDeLaCase: Moitie = cote;
  const moitieDeLEtiquette: Moitie = cote === 'droite' ? 'gauche' : 'droite';

  // Le halo respire autour du repère choisi — et se tait quand l'appareil réduit les mouvements.
  const echelle = useSharedValue(1);

  useEffect(() => {
    echelle.value = choisi && !mouvementReduit
      ? withRepeat(withTiming(1.8, { duration: animation.duration.slow * 3 }), -1, true)
      : 1;
  }, [choisi, mouvementReduit, echelle]);

  const halo = useAnimatedStyle(() => ({ transform: [{ scale: echelle.value }] }));

  const contreLAxe = (moitie: Moitie) => (moitie === 'droite' ? styles.contreAxeDroit : styles.contreAxeGauche);

  const laCase = (
    <View style={contreLAxe(moitieDeLaCase)}>
      {moitieDeLaCase === 'droite' ? <View style={styles.fil} /> : null}
      <GlassSurface
        strong={choisi}
        radius={radius.md}
        style={[styles.case, choisi && styles.caseChoisie]}
        testID={`case-${metier.slug}`}
      >
        <Text style={styles.nom} numberOfLines={2}>{metier.name}</Text>
        <Text
          style={[styles.prix, metier.floor_price_cents !== null ? styles.prixConnu : styles.prixInconnu]}
          numberOfLines={1}
        >
          {libellePrix}
        </Text>
      </GlassSurface>
      {moitieDeLaCase === 'gauche' ? <View style={styles.fil} /> : null}
    </View>
  );

  const lEtiquette = etiquetteSecteur ? (
    <View style={contreLAxe(moitieDeLEtiquette)}>
      <Text style={styles.etiquette} numberOfLines={1} testID={`etiquette-${metier.slug}`}>
        {etiquetteSecteur}
      </Text>
    </View>
  ) : null;

  const contenu = (moitie: Moitie) => (moitie === moitieDeLaCase ? laCase : lEtiquette);

  return (
    <Pressable
      onPress={onChoisir}
      accessibilityRole="button"
      accessibilityState={{ selected: choisi }}
      accessibilityLabel={tr('catalogue.repere_accessible', { metier: metier.name, prix: libellePrix })}
      style={styles.rangee}
      testID={`repere-${metier.slug}`}
    >
      <View style={styles.moitie} testID={`moitie-gauche-${metier.slug}`}>{contenu('gauche')}</View>

      <View style={styles.axe} pointerEvents="none">
        {choisi ? <Animated.View style={[styles.halo, halo]} /> : null}
        <View style={choisi ? styles.noeudChoisi : styles.noeud} />
      </View>

      <View style={styles.moitie} testID={`moitie-droite-${metier.slug}`}>{contenu('droite')}</View>
    </Pressable>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  rangee: { height: HAUTEUR_DE_REPERE, flexDirection: 'row', alignItems: 'center' },
  moitie: { flex: 1, height: '100%', justifyContent: 'center' },
  contreAxeDroit: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-start', paddingRight: spacing.md },
  contreAxeGauche: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', paddingLeft: spacing.md },
  axe: { width: AXE, height: '100%', alignItems: 'center', justifyContent: 'center' },
  noeud: {
    width: 12, height: 12, borderRadius: radius.pill,
    borderWidth: 1.5, borderColor: t.action, backgroundColor: t.glassStrong,
  },
  noeudChoisi: { width: 16, height: 16, borderRadius: radius.pill, backgroundColor: t.action },
  halo: { position: 'absolute', width: 16, height: 16, borderRadius: radius.pill, backgroundColor: t.glow },
  fil: { width: spacing.sm + spacing.xs, height: 2, backgroundColor: t.action, opacity: 0.75 },
  case: { flexShrink: 1, paddingVertical: spacing.sm, paddingHorizontal: spacing.sm + spacing.xs, gap: spacing['2xs'] },
  caseChoisie: { borderColor: t.action, borderWidth: 1 },
  nom: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold, color: t.textOnGlass },
  prix: { fontSize: typography.fontSize.xs },
  prixConnu: { color: t.argent, fontWeight: typography.fontWeight.semibold },
  prixInconnu: { color: t.mutedOnGlass },
  etiquette: {
    maxWidth: '100%',
    fontSize: typography.fontSize.xs,
    color: t.textOnGlass,
    backgroundColor: t.glassStrong,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs - 1,
    borderRadius: radius.pill,
    overflow: 'hidden',
  },
});
