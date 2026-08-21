import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';

/**
 * LE PALIER EST UN OBJET, PAS UN MOT — et il peut ne pas exister.
 *
 * Ce type annonçait `tier: 'bronze' | 'silver' | 'gold' | 'platinum'`, et l'écran en tirait
 * `account.tier.toUpperCase()`. Or `/client/loyalty/me` rend un OBJET `{slug, name, icon…}`
 * quand un palier est atteint, et `null` sinon. Les deux cas plantaient donc l'écran « Programme
 * fidélité » : « Cannot read property 'toUpperCase' of null », relevé dans l'émulateur sur un
 * compte neuf — c'est-à-dire sur tout nouveau client.
 *
 * `total_points` n'existe pas davantage : le serveur le nomme `lifetime_points`.
 */
export interface LoyaltyAccount {
  tier: { slug: string; name: string } | null;
  total_points: number;
  /** Absent de la réponse tant que l'API ne l'expose pas : `null` se lit « inconnu », pas « zéro ». */
  redeemable_points: number | null;
  period_points: number;
}

type CompteDeLApi = {
  lifetime_points?: number;
  period_points?: number;
  redeemable_points?: number | null;
  tier?: { slug: string; name: string } | null;
};

function versCompte(brut: CompteDeLApi): LoyaltyAccount {
  return {
    tier: brut.tier ?? null,
    total_points: brut.lifetime_points ?? 0,
    redeemable_points: brut.redeemable_points ?? null,
    period_points: brut.period_points ?? 0,
  };
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
    queryFn: async () => versCompte((await apiClient.get('/client/loyalty/me')).data ?? {}),
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

export interface RedeemResult {
  id: number;
  code: string;
  status: string;
  voucher_code?: string;
  delivery_method?: string;
  points_spent: number;
}

/** Échange une récompense contre des points et rafraîchit le solde + le catalogue. */
export function useRedeemReward() {
  const queryClient = useQueryClient();
  return useMutation<RedeemResult, unknown, number>({
    mutationFn: async (rewardId: number) => {
      const res = await apiClient.post('/client/loyalty/rewards/redeem', { reward_id: rewardId });
      return res.data.data ?? res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['loyalty'] });
    },
  });
}
