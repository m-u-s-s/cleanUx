import React, { useRef, useState } from 'react';
import { ScrollView, StyleSheet, View, type NativeScrollEvent, type NativeSyntheticEvent } from 'react-native';
import { spacing } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { HAUTEUR_DE_REPERE, decalageDeIndex, indexDepuisDecalage } from '@/catalogue';
import type { RepereDeSonde as Repere } from '@/catalogue';
import { RepereDeSonde } from './RepereDeSonde';

const TIRET = spacing.xs;
const INTERVALLE = spacing.xs + 1;

export interface LigneDeSondeProps {
  reperes: Repere[];
  index: number;
  onIndex: (index: number) => void;
  libelleDuRepere: (repere: Repere) => string;
  mouvementReduit: boolean;
}

/**
 * LA COLONNE DE REPÈRES — la seule chose qui défile.
 *
 * L'aimantation se fait tous les `HAUTEUR_DE_REPERE` points. Les marges haute et basse valent la
 * moitié de la hauteur visible moins un repère : le premier et le dernier peuvent ainsi se poser
 * au centre, là où la lueur marque le niveau de l'eau.
 */
export function LigneDeSonde({ reperes, index, onIndex, libelleDuRepere, mouvementReduit }: LigneDeSondeProps) {
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const defilement = useRef<ScrollView>(null);
  const [hauteur, setHauteur] = useState(0);

  /*
   * LA CIBLE DU RECENTRAGE PROGRAMMÉ.
   *
   * `choisir` anime `scrollTo`, et `onScroll` reçoit chaque décalage intermédiaire de cette
   * animation : sans ce garde-fou, la sélection balaierait tous les repères entre l'ancien et le
   * nouveau choix — l'inverse même de « rien qui s'agite » de la charte. Tant que cette cible est
   * posée, `suivre` l'ignore ; elle se lève d'elle-même à l'arrivée, ou dès que le doigt reprend
   * la main.
   */
  const cibleDuRecentrage = useRef<number | null>(null);

  const marge = Math.max(0, (hauteur - HAUTEUR_DE_REPERE) / 2);
  const longueurDuTrait = Math.max(0, (reperes.length - 1) * HAUTEUR_DE_REPERE);

  const suivre = (evenement: NativeSyntheticEvent<NativeScrollEvent>) => {
    const y = evenement.nativeEvent.contentOffset.y;

    if (cibleDuRecentrage.current !== null) {
      if (Math.abs(y - cibleDuRecentrage.current) < 1) {
        cibleDuRecentrage.current = null;
      }

      return;
    }

    const suivant = indexDepuisDecalage(y, reperes.length);

    if (suivant !== index) {
      onIndex(suivant);
    }
  };

  const choisir = (suivant: number) => {
    cibleDuRecentrage.current = decalageDeIndex(suivant);
    defilement.current?.scrollTo({ y: decalageDeIndex(suivant), animated: !mouvementReduit });

    if (suivant !== index) {
      onIndex(suivant);
    }
  };

  return (
    <View style={styles.cadre} onLayout={e => setHauteur(e.nativeEvent.layout.height)}>
      <View pointerEvents="none" style={[styles.lentille, { top: marge }]} />

      <ScrollView
        ref={defilement}
        testID="ligne-de-sonde"
        showsVerticalScrollIndicator={false}
        snapToInterval={HAUTEUR_DE_REPERE}
        decelerationRate="fast"
        scrollEventThrottle={16}
        onScroll={suivre}
        onMomentumScrollEnd={suivre}
        onScrollBeginDrag={() => { cibleDuRecentrage.current = null; }}
        contentContainerStyle={{ paddingVertical: marge }}
      >
        <View
          pointerEvents="none"
          style={[styles.trait, { top: marge + HAUTEUR_DE_REPERE / 2, height: longueurDuTrait }]}
        >
          {Array.from({ length: Math.ceil(longueurDuTrait / (TIRET + INTERVALLE)) }).map((_, k) => (
            <View key={k} style={styles.tiret} />
          ))}
        </View>

        {reperes.map((repere, i) => (
          <RepereDeSonde
            key={repere.cle}
            repere={repere}
            choisi={i === index}
            libellePrix={libelleDuRepere(repere)}
            mouvementReduit={mouvementReduit}
            onChoisir={() => choisir(i)}
          />
        ))}
      </ScrollView>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  cadre: { flex: 1 },
  lentille: { position: 'absolute', left: 0, right: 0, height: HAUTEUR_DE_REPERE, backgroundColor: t.glow },
  trait: { position: 'absolute', left: '50%', marginLeft: -1, width: 2, overflow: 'hidden' },
  tiret: { width: 2, height: TIRET, marginBottom: INTERVALLE, backgroundColor: t.action, opacity: 0.75 },
});
