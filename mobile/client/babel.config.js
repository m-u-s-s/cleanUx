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
            '@/ui': '../shared/src/ui',
            '@/modules': '../shared/src/modules',
            '@/parity': '../shared/src/parity',
            '@/webview': '../shared/src/webview',
            '@/finance': '../shared/src/finance',
            '@/format': '../shared/src/format',
            '@/ErrorBoundary': '../shared/src/ErrorBoundary',
            /*
             * Déclaré dans tsconfig.json, absent d'ici et de jest.config.ts : le typage passait,
             * et l'exécution reposait sur le lien symbolique d'espace de travail — qui résout le
             * paquet racine mais PAS ses sous-chemins (`@brio/shared/format` pointe alors sur un
             * dossier qui n'existe pas). L'application prestataire l'épingle depuis toujours ; le
             * client vivait sur un accident d'installation.
             */
            '@brio/shared': '../shared/src',
            // Client-only modules — resolve to local src/
            '@': './src',
          },
        },
      ],
      // react-native-reanimated/plugin requires react-native-worklets (native peer dep)
      // mocked in jest via jest.config.ts moduleNameMapper
    ],
  };
};
