// API
export { apiClient, ApiError } from './api';
export type { User, ApiResponse } from './api';

// Auth
export { AuthProvider, useAuth, useLogin, useRegister, useMe, authenticateWithBiometrics, SECOND_FACTEUR_REQUIS } from './auth';

// Chat
export { useChatThreads, useChatMessages, useSendMessage, useMarkThreadRead, useLiveChat } from './chat';
export type { ChatThread, ChatMessage } from './chat/types';

// Config
export { env } from './config/env';

// Face check
export {
  useFaceCheckStatus,
  useEnrollFace,
  useStartFaceCheck,
  useSubmitFaceCheck,
  useFaceCheck,
  useAbandonFaceCheck,
  useReportFaceIncident,
  useWithdrawFaceConsent,
  faceCheckBloqueLeTerrain,
} from './faceCheck';
export type { FaceCheckStatus, FaceCheck, FaceCheckState } from './faceCheck';

// Notifications
export { useNotifications, useMarkAllRead } from './notifications';
export type { AppNotification } from './notifications/hooks';

// Push
export { useRegisterPushToken } from './push';
export { setupForegroundNotifications } from './push/foreground';
export { useNotificationRouting } from './push/useNotificationRouting';

// Realtime
export { RealtimeProvider, useRealtime, useChannel, useSocketConfig } from './realtime';
export type { SocketConfig } from './realtime/useSocketConfig';

// Storage
export { secureStore } from './storage/secureStore';

// Theme
export { colors } from './theme/colors';
export { spacing } from './theme/spacing';
export { radius } from './theme/radius';
export { typography } from './theme/typography';
export { shadows } from './theme/shadows';
export { animation } from './theme/animation';
export { useColorScheme } from './theme/useColorScheme';
export { useThemeColors } from './theme/useThemeColors';

// UI
export {
  Button,
  Badge,
  Icon,
  Avatar,
  Tag,
  Divider,
  TextInput,
  StatCard,
  KPICard,
  PulseDot,
  Skeleton,
  Screen,
  BottomSheet,
  EmptyState,
  ErrorState,
  ProgressBar,
  AnimatedListItem,
  SuccessOverlay,
} from './ui';
export { fadeUpConfig, fadeInConfig, shimmerConfig } from './ui/animations';
export { useReducedMotion, useScreenReader, a11y } from './ui/a11y';

// Hooks
export { maybeRequestReview, incrementCompletedBookings } from './hooks/useAppRating';

// ErrorBoundary
export { ErrorBoundary } from './ErrorBoundary';

// WebView (parity foundation)
export { EmbeddedModuleScreen, parseBridgeMessage, INJECTED_BRIDGE_JS, fetchWebViewUrl } from './webview';
export type { EmbeddedModuleScreenProps, BridgeMessage } from './webview';

// Parity
export { fetchParityMap } from './parity';
export type { ParityModule } from './parity';

/*
 * Format — PARTAGÉ à dessein. Ces défauts d'affichage ont été corrigés côté client puis
 * redécouverts à l'identique côté prestataire le lendemain : statut technique, date ISO, message
 * interne d'axios. Une seule copie évite une troisième découverte.
 */
export { libelleStatut, formatDateHeure, formatAdresse, formatDelai, messageDErreur } from './format';
