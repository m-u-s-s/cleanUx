import { useColorScheme } from './useColorScheme';
import { colors } from './colors';

export function useThemeColors() {
  const { colorScheme } = useColorScheme();
  const isDark = colorScheme === 'dark';
  return {
    bg: isDark ? colors.surface[900] : colors.surface[50],
    card: isDark ? colors.surface[800] : '#ffffff',
    cardElevated: isDark ? colors.surface[700] : '#ffffff',
    text: isDark ? colors.surface[50] : colors.surface[900],
    textSecondary: isDark ? colors.surface[400] : colors.surface[600],
    textMuted: isDark ? colors.surface[500] : colors.surface[400],
    border: isDark ? colors.surface[700] : colors.surface[200],
    inputBg: isDark ? colors.surface[800] : colors.surface[50],
  };
}
