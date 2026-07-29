import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';

/**
 * Vérification du téléphone au premier écran de l'inscription — donc avant que le compte existe.
 *
 * Ces deux routes sont publiques : `POST /api/auth/phone/verify-request` envoie un SMS,
 * `/verify-confirm` échange le code contre un jeton à usage unique, présenté ensuite à
 * l'inscription. C'est ce jeton, et non un booléen envoyé par l'app, qui autorise le serveur à
 * marquer le téléphone comme vérifié : l'app ne peut pas s'auto-déclarer vérifiée.
 */

/** Le serveur exige E.164 strict et renvoie 422 sinon — autant normaliser avant d'envoyer. */
export function toE164(raw: string, defaultCountryCode = '+32'): string {
  const trimmed = raw.trim().replace(/[\s.\-()]/g, '');

  if (trimmed.startsWith('+')) return trimmed;
  if (trimmed.startsWith('00')) return `+${trimmed.slice(2)}`;
  // « 0471… » est la forme nationale : le 0 initial cède la place à l'indicatif pays.
  if (trimmed.startsWith('0')) return `${defaultCountryCode}${trimmed.slice(1)}`;

  return `${defaultCountryCode}${trimmed}`;
}

export function isPlausibleE164(phone: string): boolean {
  return /^\+[1-9]\d{7,14}$/.test(phone);
}

interface RequestCodeInput {
  phone: string;
}

interface RequestCodeResult {
  phone: string;
  expiresAt: string | null;
}

export function useRequestPhoneCode() {
  return useMutation<RequestCodeResult, ApiError, RequestCodeInput>({
    mutationFn: async ({ phone }) => {
      const res = await apiClient.post('/auth/phone/verify-request', { phone });

      return { phone: res.data.phone, expiresAt: res.data.expires_at ?? null };
    },
  });
}

interface ConfirmCodeInput {
  phone: string;
  code: string;
}

interface ConfirmCodeResult {
  /** À transmettre tel quel à l'inscription. Usage unique, durée de vie 30 minutes. */
  token: string;
}

export function useConfirmPhoneCode() {
  return useMutation<ConfirmCodeResult, ApiError, ConfirmCodeInput>({
    mutationFn: async ({ phone, code }) => {
      const res = await apiClient.post('/auth/phone/verify-confirm', { phone, code });

      return { token: res.data.phone_verification_token };
    },
  });
}
