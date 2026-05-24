module.exports = function (api) {
  api.cache(true);
  return {
    presets: [require('expo/internal/babel-preset')],
    plugins: [
      ['module-resolver', { alias: { '@': './src' } }],
      // react-native-reanimated/plugin requires react-native-worklets (native peer dep)
      // mocked in jest via jest.config.ts moduleNameMapper
    ],
  };
};
