import React from 'react';
import { ScrollView, View, StyleSheet, ViewProps } from 'react-native';
import { SafeAreaView, Edge } from 'react-native-safe-area-context';
import { spacing } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';

interface ScreenProps extends ViewProps {
  scroll?: boolean;
  edges?: Edge[];
  children: React.ReactNode;
}

export function Screen({ scroll, edges = ['top', 'left', 'right'], children, style, ...props }: ScreenProps) {
  const { bg, isDark } = useThemeColors();

  /*
   * En sombre, l'écran ne peint pas son fond : il laisse voir la toile nuit montée une fois à la
   * racine par `NightShell`. Peindre `bg` ici la masquerait entièrement — un aplat presque de la
   * même couleur, donc une régression invisible sur une capture d'écran et pourtant totale.
   */
  const fond = isDark ? 'transparent' : bg;

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
