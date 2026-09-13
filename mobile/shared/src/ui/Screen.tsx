import React from 'react';
import { ScrollView, View, StyleSheet, ViewProps } from 'react-native';
import { SafeAreaView, Edge } from 'react-native-safe-area-context';
import { spacing } from '@/theme';
import { GlassSurface } from './GlassSurface';

interface ScreenProps extends ViewProps {
  scroll?: boolean;
  edges?: Edge[];
  /**
   * L'écran laisse voir la TOILE au lieu de poser sa plaque.
   *
   * À réserver aux écrans dont tout le contenu est déjà sur du verre — l'accueil, le tableau de
   * bord. Ailleurs, un texte posé à nu sur le rendu tombe tantôt sur la quille noire, tantôt sur
   * les caustiques blanches : aucune couleur ne tient sur les deux.
   */
  toile?: boolean;
  children: React.ReactNode;
}

export function Screen({
  scroll,
  edges = ['top', 'left', 'right'],
  toile = false,
  children,
  style,
  ...props
}: ScreenProps) {

  /*
   * L'ÉCRAN NE PEINT JAMAIS D'APLAT — il pose une PLAQUE, ce qui n'est pas la même chose.
   *
   * Il a longtemps été entièrement transparent, pour laisser voir la toile montée une fois à la
   * racine par `NightShell`. C'était tenable tant que cette toile était une nuance sage. Depuis
   * qu'elle porte le rendu de la planche, elle va du noir de la quille au blanc des caustiques :
   * un titre d'écran y devenait illisible une fois sur deux, selon l'endroit où il tombait.
   *
   * La plaque est un FRÈRE du contenu, posé derrière lui, jamais un parent : envelopper le
   * contenu changerait la mise en page de quatre-vingt-dix-neuf écrans d'un coup.
   */
  const content = scroll ? (
    <ScrollView
      contentContainerStyle={styles.scrollContent}
      showsVerticalScrollIndicator={false}
      keyboardShouldPersistTaps="handled"
      keyboardDismissMode="on-drag"
    >
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.content, style]} {...props}>
      {children}
    </View>
  );

  return (
    // `testID` fixe : c'est la couche qui décide si la toile se voit, et remonter à elle par
    // la chaîne des parents dans un test se casse au premier changement de structure.
    <SafeAreaView testID="screen-safe" style={styles.safe} edges={edges}>
      {toile ? null : (
        <GlassSurface testID="screen-plaque" radius={0} strong style={StyleSheet.absoluteFill} />
      )}
      {content}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: 'transparent' },
  content: { flex: 1, paddingHorizontal: spacing.md },
  scrollContent: { paddingHorizontal: spacing.md, paddingBottom: spacing.xl },
});
