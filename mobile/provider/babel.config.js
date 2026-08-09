module.exports = function (api) {
  api.cache(true);
  return {
    presets: [require('expo/internal/babel-preset')],
    plugins: [
      [
        'module-resolver',
        {
          alias: {
            // Shared modules — resolve to the monorepo shared package
            '@/api': '../shared/src/api',
            '@/auth': '../shared/src/auth',
            '@/chat': '../shared/src/chat',
            '@/config': '../shared/src/config',
            '@/notifications': '../shared/src/notifications',
            '@/push': '../shared/src/push',
            '@/realtime': '../shared/src/realtime',
            '@/sentry': '../shared/src/sentry',
            '@/storage': '../shared/src/storage',
            '@/theme': '../shared/src/theme',
            '@/onboarding': '../shared/src/onboarding',
            '@/trades': '../shared/src/trades',
            '@/catalog': '../shared/src/catalog',
            '@/ui': '../shared/src/ui',
            '@/ErrorBoundary': '../shared/src/ErrorBoundary',
            // Déclarés dans tsconfig.json mais absents ici : le typage passait, l'import
            // échouait à l'exécution. La console d'administration en dépend.
            '@/modules': '../shared/src/modules',
            '@/parity': '../shared/src/parity',
            '@/webview': '../shared/src/webview',
            '@/finance': '../shared/src/finance',
            '@brio/shared': '../shared/src',
            // Provider-only modules — resolve to local src/
            '@': './src',
          },
        },
      ],
      // react-native-reanimated/plugin requires react-native-worklets (native peer dep)
      // mocked in jest via jest.config.ts moduleNameMapper
    ],
  };
};
