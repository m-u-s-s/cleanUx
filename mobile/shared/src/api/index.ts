export { apiClient } from './client';
export { ApiError } from './types';
export type { User } from './types';
export { offlineAwareMutation } from './offlineAwareClient';
export type { MutationResult } from './offlineAwareClient';
export { useOfflineSync } from './useOfflineSync';
export { bindAppStateToQueryFocus } from './appFocus';

/** Generic paginated/single-item API response envelope. */
export interface ApiResponse<T = unknown> {
  data: T;
  message?: string;
  status?: string;
}
export { setAppAudience, getAppAudience, APP_AUDIENCE_HEADER } from './appAudience';
export type { AppAudience } from './appAudience';
