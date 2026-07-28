import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';

export interface Trade {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  description?: string | null;
}

/**
 * Une question posée au prestataire pour ce métier (trades.provider_form_schema).
 * Même forme que le schéma de réservation côté client, pour que les deux se lisent pareil.
 */
export interface ProviderField {
  key: string;
  type: 'text' | 'number' | 'boolean' | 'select';
  label: string;
  help?: string;
  required?: boolean;
  min?: number;
  max?: number;
  default?: string | number | boolean;
  options?: Array<{ value: string; label: string }>;
}

/**
 * Les deux endpoints sont PUBLICS : le formulaire d'inscription les appelle avant que le compte
 * n'existe, donc avant tout jeton. Ne pas les basculer derrière `auth:sanctum`.
 */
export function useTrades() {
  return useQuery<Trade[]>({
    queryKey: ['trades'],
    queryFn: async () => (await apiClient.get('/trades')).data.data ?? [],
    // Le référentiel métiers bouge rarement : inutile de le refetch à chaque montage.
    staleTime: 5 * 60 * 1000,
  });
}

export function useTradeProviderFields(tradeId: number | null) {
  return useQuery<ProviderField[]>({
    queryKey: ['trades', tradeId, 'provider-fields'],
    queryFn: async () => (await apiClient.get(`/trades/${tradeId}/provider-fields`)).data.fields ?? [],
    enabled: tradeId !== null,
    staleTime: 5 * 60 * 1000,
  });
}
