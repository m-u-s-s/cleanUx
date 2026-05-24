import type { Config } from 'jest';

const config: Config = {
  preset: 'jest-expo',
  transformIgnorePatterns: [
    'node_modules/(?!((jest-)?react-native|@react-native(-community)?)|expo(nent)?|@expo(nent)?/.*|@expo-google-fonts/.*|react-navigation|@react-navigation/.*|@sentry/react-native|expo-secure-store|expo-constants|expo-status-bar|@gorhom)',
  ],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
    // react-native-reanimated v4 requires native worklets — use local stub to avoid WorkletsError
    'react-native-reanimated': '<rootDir>/__mocks__/react-native-reanimated',
    // safe-area-context: use local mock with named exports (the official mock uses default export)
    'react-native-safe-area-context': '<rootDir>/__mocks__/react-native-safe-area-context',
    // pusher-js react-native build requires @react-native-community/netinfo (not installed); use mock
    'pusher-js/react-native': '<rootDir>/__mocks__/pusher-js-react-native',
    // @expo/vector-icons loads native fonts at runtime — mock for Jest
    '^@expo/vector-icons$': '<rootDir>/__mocks__/@expo/vector-icons',
    '^@expo/vector-icons/(.*)$': '<rootDir>/__mocks__/@expo/vector-icons',
    // @gorhom/bottom-sheet: use official mock to avoid native gesture handler init
    '^@gorhom/bottom-sheet$': '@gorhom/bottom-sheet/mock',
    // react-native-maps: use local stub to avoid native map init in Jest
    '^react-native-maps$': '<rootDir>/__mocks__/react-native-maps.tsx',
    // expo-camera: use local stub to avoid native camera init in Jest
    '^expo-camera$': '<rootDir>/__mocks__/expo-camera.tsx',
    // @stripe/stripe-react-native: use local stub to avoid native Stripe init in Jest
    '^@stripe/stripe-react-native$': '<rootDir>/__mocks__/@stripe/stripe-react-native.tsx',
  },
};

export default config;
