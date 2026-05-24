import type { Config } from 'jest';

const config: Config = {
  preset: 'jest-expo',
  transformIgnorePatterns: [
    'node_modules/(?!((jest-)?react-native|@react-native(-community)?)|expo(nent)?|@expo(nent)?/.*|@expo-google-fonts/.*|react-navigation|@react-navigation/.*|@sentry/react-native|expo-secure-store|expo-constants|expo-status-bar)',
  ],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
    // react-native-reanimated v4 requires react-native-worklets (native only)
    'react-native-reanimated': '<rootDir>/node_modules/react-native-reanimated/mock',
  },
};

export default config;
