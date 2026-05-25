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
            '@/ErrorBoundary': '../shared/src/ErrorBoundary',
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
