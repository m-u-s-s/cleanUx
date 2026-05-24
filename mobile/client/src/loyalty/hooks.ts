import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';

export interface LoyaltyAccount {
  tier: 'bronze' | 'silver' | 'gold' | 'platinum';
  total_points: number;
  redeemable_points: number;
  period_points: number;
}

export interface LoyaltyReward {
  id: number;
  name: string;
  type: string;
  points_cost: number;
  stock?: number;
}

export function useLoyaltyAccount() {
  return useQuery<LoyaltyAccount>({
    queryKey: ['loyalty', 'me'],
    queryFn: async () => (await apiClient.get('/client/loyalty/me')).data,
  });
}

export function useLoyaltyRewards() {
  return useQuery<LoyaltyReward[]>({
    queryKey: ['loyalty', 'rewards'],
    queryFn: async () => {
      const res = await apiClient.get('/client/loyalty/rewards');
      return res.data.data ?? res.data;
    },
  });
}
