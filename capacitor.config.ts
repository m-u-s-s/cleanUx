/**
 * Capacitor configuration for CleanUx mobile native wrappers (iOS + Android).
 *
 * Usage :
 *   1. npm install --save @capacitor/core @capacitor/cli @capacitor/ios @capacitor/android
 *   2. npx cap init "CleanUx" "com.cleanux.app" --web-dir=public
 *   3. npx cap add ios && npx cap add android
 *   4. npm install @capacitor/push-notifications @capacitor/geolocation @capacitor/app
 *   5. npx cap sync
 *   6. npx cap open ios   (puis build dans Xcode)
 *   7. npx cap open android  (puis build dans Android Studio)
 *
 * En production : pointer `server.url` vers https://app.cleanux.com ; en dev/staging
 * pointer vers le staging serveur Laravel.
 */
import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.cleanux.app',
  appName: 'CleanUx',
  webDir: 'public',
  bundledWebRuntime: false,

  server: {
    // Pour staging : point vers serveur Laravel ; pour build prod app store : commenter et bundler les assets.
    url: process.env.CAPACITOR_SERVER_URL || 'https://staging.cleanux.com',
    cleartext: false,
    // androidScheme: 'https',
  },

  ios: {
    contentInset: 'always',
    backgroundColor: '#ffffff',
    scrollEnabled: true,
    limitsNavigationsToAppBoundDomains: true,
  },

  android: {
    backgroundColor: '#ffffff',
    allowMixedContent: false,
    captureInput: true,
    webContentsDebuggingEnabled: false,
  },

  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      launchAutoHide: true,
      backgroundColor: '#6366f1',
      androidScaleType: 'CENTER_CROP',
      showSpinner: false,
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    Geolocation: {
      // permissions request: handled at runtime via JS API
    },
  },
};

export default config;
