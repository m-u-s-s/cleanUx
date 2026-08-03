import React, { Component, ErrorInfo } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Props { children: React.ReactNode; }
interface State { hasError: boolean; error: Error | null; }

/**
 * L'écran de repli, extrait en composant FONCTION.
 *
 * `ErrorBoundary` doit rester une classe — `componentDidCatch` n'a pas d'équivalent en hook — et
 * une classe ne peut pas appeler `useThemeColors()`. Sans cette extraction, l'écran qui s'affiche
 * quand tout a échoué serait le seul de l'application à ignorer le thème : texte noir sur fond
 * sombre, au pire moment possible.
 */
function ErrorFallback({ error, onReset }: { error: Error | null; onReset: () => void }) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Oups !</Text>
      <Text style={styles.message}>Une erreur inattendue est survenue.</Text>
      {__DEV__ && error && <Text style={styles.debug}>{error.message}</Text>}
      <Button label="Réessayer" onPress={onReset} />
    </View>
  );
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, error: null };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Sentry capture if available
    try {
      const { Sentry } = require('@/sentry/init');
      Sentry?.captureException?.(error, { extra: { componentStack: errorInfo.componentStack } });
    } catch {}
  }

  handleReset = () => this.setState({ hasError: false, error: null });

  render() {
    if (this.state.hasError) {
      return <ErrorFallback error={this.state.error} onReset={this.handleReset} />;
    }

    return this.props.children;
  }
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: spacing.xl, backgroundColor: t.page },
  title: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold, color: t.text, marginBottom: spacing.sm },
  message: { fontSize: typography.fontSize.base, color: t.textSecondary, marginBottom: spacing.lg, textAlign: 'center' },
  // La couleur d'erreur est SÉMANTIQUE : son sens ne dépend pas du fond, et la thématiser la
  // viderait de ce sens.
  debug: { fontSize: typography.fontSize.xs, color: colors.danger[500], marginBottom: spacing.md, fontFamily: 'monospace' },
});
