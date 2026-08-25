import React from 'react';
import { ScrollView, View, StyleSheet, ViewProps } from 'react-native';
import { SafeAreaView, Edge } from 'react-native-safe-area-context';
import { spacing } from '@/theme';

interface ScreenProps extends ViewProps {
  scroll?: boolean;
  edges?: Edge[];
  children: React.ReactNode;
}

export function Screen({ scroll, edges = ['top', 'left', 'right'], children, style, ...props }: ScreenProps) {

  /*
   * L'ÉCRAN NE PEINT JAMAIS SON FOND — dans AUCUN des deux modes.
   *
   * Il laisse voir la toile montée une fois à la racine par `NightShell` : les gouttes en
   * sombre, les auras diffuses en clair. Peindre un aplat ici la masquerait entièrement, et
   * la régression serait invisible sur une capture — la couleur est presque la même — tout en
   * étant totale.
   *
   * Le mode clair y échappait tant que la toile n'y rendait rien. Depuis qu'elle rend, un
   * aplat par écran effacerait exactement ce que le verre est censé filtrer.
   */
  const fond = 'transparent';

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
    // `testID` fixe : c'est la couche qui décide si la toile nuit se voit, et remonter à elle par
    // la chaîne des parents dans un test se casse au premier changement de structure.
    <SafeAreaView testID="screen-safe" style={[styles.safe, { backgroundColor: fond }]} edges={edges}>
      {content}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1 },
  content: { flex: 1, paddingHorizontal: spacing.md },
  scrollContent: { paddingHorizontal: spacing.md, paddingBottom: spacing.xl },
});
