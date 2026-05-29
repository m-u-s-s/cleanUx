export { apiClient } from './client';
export { ApiError } from './types';
export type { User } from './types';
export { offlineAwareMutation } from './offlineAwareClient';
export type { MutationResult } from './offlineAwareClient';
export { useOfflineSync } from './useOfflineSync';

/** Generic paginated/single-item API response envelope. */
export interface ApiResponse<T = unknown> {
  data: T;
  message?: string;
  status?: string;
}
