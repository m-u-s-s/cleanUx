import React from 'react';
import { ScrollView, View, StyleSheet, ViewProps } from 'react-native';
import { SafeAreaView, Edge } from 'react-native-safe-area-context';
import { colors, spacing } from '@/theme';

interface ScreenProps extends ViewProps {
  scroll?: boolean;
  edges?: Edge[];
  children: React.ReactNode;
}

export function Screen({ scroll, edges = ['top', 'left', 'right'], children, style, ...props }: ScreenProps) {
  const content = scroll ? (
    <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.content, style]} {...props}>
      {children}
    </View>
  );

  return (
    <SafeAreaView style={styles.safe} edges={edges}>
      {content}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.surface[50] },
  content: { flex: 1, paddingHorizontal: spacing.md },
  scrollContent: { paddingHorizontal: spacing.md, paddingBottom: spacing.xl },
});
