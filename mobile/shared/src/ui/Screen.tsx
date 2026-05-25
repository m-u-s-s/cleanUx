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
  const { bg } = useThemeColors();

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
    <SafeAreaView style={[styles.safe, { backgroundColor: bg }]} edges={edges}>
      {content}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1 },
  content: { flex: 1, paddingHorizontal: spacing.md },
  scrollContent: { paddingHorizontal: spacing.md, paddingBottom: spacing.xl },
});
