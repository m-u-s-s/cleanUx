import React, { Component, ErrorInfo } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from '@/ui';
import { colors, spacing, typography } from '@/theme';

interface Props { children: React.ReactNode; }
interface State { hasError: boolean; error: Error | null; }

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
      return (
        <View style={styles.container}>
          <Text style={styles.title}>Oups !</Text>
          <Text style={styles.message}>Une erreur inattendue est survenue.</Text>
          {__DEV__ && this.state.error && <Text style={styles.debug}>{this.state.error.message}</Text>}
          <Button label="Réessayer" onPress={this.handleReset} />
        </View>
      );
    }
    return this.props.children;
  }
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: spacing.xl, backgroundColor: colors.surface[50] },
  title: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold, color: colors.surface[900], marginBottom: spacing.sm },
  message: { fontSize: typography.fontSize.base, color: colors.surface[500], marginBottom: spacing.lg, textAlign: 'center' },
  debug: { fontSize: typography.fontSize.xs, color: colors.danger[500], marginBottom: spacing.md, fontFamily: 'monospace' },
});
