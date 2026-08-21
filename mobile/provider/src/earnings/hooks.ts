import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';

export interface WalletBalance { available: number; pending: number; currency: string; }
export interface WalletTransaction { id: number; type: string; amount: number; currency: string; description: string; created_at: string; }
export interface StripeConnectStatus { onboarded: boolean; charges_enabled: boolean; payouts_enabled: boolean; requirements: string[]; }
export interface Payout { id: string; amount: number; currency: string; status: string; arrival_date: string; }

// `enabled` par défaut à `true` : aucun appelant existant (WalletScreen, DashboardScreen,
// WalletScreen) n'a besoin de changer. DashboardActionsSheet le passe à `false` tant
// que le sheet est fermé (voir useMissionInbox pour la même raison).
export function useWalletBalance(enabled: boolean = true) {
  return useQuery<WalletBalance>({
    queryKey: ['provider', 'wallet', 'balance'],
    queryFn: async () => (await apiClient.get('/provider/wallet/balance')).data,
    enabled,
  });
}

export function useWalletTransactions() {
  return useQuery<WalletTransaction[]>({
    queryKey: ['provider', 'wallet', 'transactions'],
    queryFn: async () => (await apiClient.get('/provider/wallet/transactions')).data.data ?? [],
  });
}

export function useWithdraw() {
  const qc = useQueryClient();
  return useMutation<void, ApiError, { amount: number }>({
    mutationFn: async ({ amount }) => { await apiClient.post('/provider/wallet/withdraw', { amount }); },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['provider', 'wallet'] }),
  });
}

export function useStripeConnectStatus() {
  return useQuery<StripeConnectStatus>({
    queryKey: ['provider', 'stripe-connect', 'status'],
    queryFn: async () => (await apiClient.get('/provider/stripe-connect/status')).data,
  });
}

export function useStripePayouts() {
  return useQuery<Payout[]>({
    queryKey: ['provider', 'stripe-connect', 'payouts'],
    queryFn: async () => (await apiClient.get('/provider/stripe-connect/payouts')).data.data ?? [],
  });
}
