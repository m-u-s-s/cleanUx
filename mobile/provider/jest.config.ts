import type { Config } from 'jest';

const config: Config = {
  preset: 'jest-expo',
  // react-test-renderer (used by @testing-library/react-native) is deprecated
  // under React 19 and leaves an async operation pending at the end of a run
  // that node cannot attribute: `jest --detectOpenHandles` reports NO leaking
  // handle in our code, yet jest's 1s grace timer still trips and prints either
  // "A worker process has failed to exit gracefully" (parallel) or "Jest did
  // not exit one second after the test run" (serial). Running in a single
  // worker plus forceExit ends the run cleanly once every suite has passed
  // (exit code 0). This never hides a test failure; if a real handle leak is
  // ever suspected, re-check with `jest --detectOpenHandles` (green today).
  maxWorkers: 1,
  forceExit: true,
  // Le module natif de react-native-gesture-handler (RNGestureHandlerModule.install()) n'a
  // aucune implémentation Jest/Node. App.tsx enveloppe désormais tout dans GestureHandlerRootView
  // (Task 9), donc tout test qui monte <App /> en a besoin — c'est le fichier de setup officiel
  // documenté par le package, qui mocke le module natif pour toute la suite.
  // Ces deux chemins passent par `require.resolve` et non par un `<rootDir>/node_modules/...` écrit
  // à la main : dans un espace de travail npm, c'est npm qui décide si un paquet atterrit dans
  // `provider/node_modules` ou à la racine `mobile/node_modules`, et il change d'avis d'une montée
  // de version à l'autre. Les deux entrées ci-dessous pariaient chacune sur un côté différent — la
  // montée en SDK 57 a déplacé gesture-handler et cassé la suite. `require.resolve` suit la
  // résolution Node et reste juste des deux côtés.
  setupFiles: [
    require.resolve('react-native-gesture-handler/jestSetup.js'),
    // Skia s'installe par des liaisons natives JSI, absentes en environnement Jest. Le paquet
    // fournit son propre fichier de mise en place ; sans lui, tout test qui touche au fond nuit
    // échoue sur « Native Skia Module failed to correctly install JSI Bindings ».
    require.resolve('@shopify/react-native-skia/jestSetup.js'),
  ],
  transformIgnorePatterns: [
    // @shopify/react-native-skia est publié en modules ES (`main: lib/module/index.js`) :
    // sans cette exception, Jest le lit tel quel et échoue sur le premier `import`.
    'node_modules/(?!((jest-)?react-native|@react-native(-community)?)|expo(nent)?|@expo(nent)?/.*|@expo-google-fonts/.*|react-navigation|@react-navigation/.*|@sentry/react-native|@shopify/react-native-skia|expo-secure-store|expo-constants|expo-status-bar|@gorhom)',
  ],
  // Allow Jest to find node_modules from the provider dir when processing shared/ files
  modulePaths: [
    '<rootDir>/node_modules',
  ],
  moduleDirectories: ['node_modules', '<rootDir>/node_modules'],
  moduleNameMapper: {
    /*
     * `react-native-webview` exige un module natif (`RNCWebViewModule`) absent sous Jest : sans ce
     * stub, TOUTE suite chargeant le navigateur racine échoue au démarrage — pas un test rouge,
     * une suite qui ne se charge pas. C'est ce qui est arrivé en branchant l'hôte WebView sur
     * l'application prestataire : deux suites ont cessé de tourner sans qu'aucun test n'échoue.
     *
     * L'application cliente avait déjà ce stub ; on reprend le même plutôt qu'une variante.
     */
    '^react-native-webview$': '<rootDir>/__mocks__/react-native-webview.tsx',
    // Shared modules — map @/ prefixed shared paths to shared/src
    '^@/api(.*)$': '<rootDir>/../shared/src/api$1',
    '^@/auth(.*)$': '<rootDir>/../shared/src/auth$1',
    '^@/chat(.*)$': '<rootDir>/../shared/src/chat$1',
    '^@/config(.*)$': '<rootDir>/../shared/src/config$1',
    '^@/notifications(.*)$': '<rootDir>/../shared/src/notifications$1',
    '^@/push(.*)$': '<rootDir>/../shared/src/push$1',
    '^@/realtime(.*)$': '<rootDir>/../shared/src/realtime$1',
    '^@/sentry(.*)$': '<rootDir>/../shared/src/sentry$1',
    '^@/storage(.*)$': '<rootDir>/../shared/src/storage$1',
    '^@/theme(.*)$': '<rootDir>/../shared/src/theme$1',
    '^@/ui(.*)$': '<rootDir>/../shared/src/ui$1',
    '^@/ErrorBoundary$': '<rootDir>/../shared/src/ErrorBoundary',
    // L'ordre compte : ces entrées doivent précéder le motif générique `^@/(.*)$`, qui les
    // capterait sinon et les ferait pointer vers src/ — où elles n'existent pas.
    '^@/onboarding(.*)$': '<rootDir>/../shared/src/onboarding$1',
    '^@/trades(.*)$': '<rootDir>/../shared/src/trades$1',
    '^@/parity(.*)$': '<rootDir>/../shared/src/parity$1',
    '^@/webview(.*)$': '<rootDir>/../shared/src/webview$1',
    '^@/finance(.*)$': '<rootDir>/../shared/src/finance$1',
    '^@brio/shared(.*)$': '<rootDir>/../shared/src$1',
    // Provider-only modules
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
    // expo-camera: use local stub to avoid native camera init in Jest
    '^expo-camera$': '<rootDir>/__mocks__/expo-camera.tsx',
    // expo-notifications: use local stub to avoid native notification init in Jest
    '^expo-notifications$': '<rootDir>/__mocks__/expo-notifications',
    // expo-image-picker: use local stub to avoid native media library init in Jest
    '^expo-image-picker$': '<rootDir>/__mocks__/expo-image-picker',
    // @react-native-async-storage/async-storage: in-memory mock for Jest
    '^@react-native-async-storage/async-storage$': '<rootDir>/__mocks__/@react-native-async-storage/async-storage',
    // @react-native-community/netinfo: simple mock to avoid native module init
    '^@react-native-community/netinfo$': '<rootDir>/__mocks__/@react-native-community/netinfo',
    // expo-font + @expo-google-fonts: avoid native font loading in Jest
    '^expo-font$': '<rootDir>/__mocks__/expo-font',
    '^@expo-google-fonts/figtree$': '<rootDir>/__mocks__/@expo-google-fonts/figtree',
    '^@expo-google-fonts/space-grotesk$': '<rootDir>/__mocks__/@expo-google-fonts/space-grotesk',
    // react-native-maps: stub local pour éviter l'init du moteur de carte natif en Jest
    '^react-native-maps$': '<rootDir>/__mocks__/react-native-maps',
  },
};

export default config;
