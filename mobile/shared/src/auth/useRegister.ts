import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { secureStore } from '@/storage/secureStore';
import type { User } from '@/api/types';

interface RegisterInput {
  name: string; email: string; password: string; passwordConfirmation: string;
  phone?: string; locale?: string; acceptTerms: boolean; deviceName?: string;
  /**
   * Jeton rendu par POST /api/auth/phone/verify-confirm, quand le téléphone a été vérifié par
   * SMS avant la création du compte. C'est lui — et non un booléen envoyé d'ici — qui autorise
   * le serveur à poser `phone_verified_at`. Usage unique, 30 minutes de validité.
   */
  phoneVerificationToken?: string;
  /**
   * Quelle app inscrit. L'app cliente l'omet et obtient un compte client, comme avant.
   * L'app prestataire demande 'provider' : sans cela le serveur créait un compte client,
   * que le garde `role:employe` enfermait hors de TOUTE la surface prestataire — onboarding
   * compris, donc sans aucun moyen de devenir prestataire.
   */
  accountType?: 'client' | 'provider';
  /**
   * Jeton Cloudflare Turnstile. L'endpoint porte le middleware `turnstile`, qui l'exige via
   * l'en-tête `X-Turnstile-Token` : sans lui l'inscription était refusée en production.
   * Absent quand le captcha n'est pas configuré (dev), où le serveur laisse passer.
   */
  captchaToken?: string | null;
  /**
   * Indépendant ou société. Une société donne lieu côté serveur à un OrganizationAccount
   * `provider_company` dont le signataire devient `owner` — c'est ce que consomment l'espace
   * web provider-company et le rattachement des missions. Omis = indépendant.
   */
  providerKind?: 'independent' | 'company';
  /**
   * Particulier ou société côté client. Une société donne lieu à un OrganizationAccount
   * `client_company` dont le signataire devient `owner` — c'est ce que consomment le multi-sites,
   * les contrats B2B et la facturation centralisée. Omis = particulier.
   */
  clientKind?: 'individual' | 'company';
  companyName?: string;
  vatNumber?: string;
  /**
   * Métier visé et réponses aux questions propres à ce métier (trades.provider_form_schema,
   * servi par GET /api/trades/{trade}/provider-fields). Sans métier déclaré, le matching n'a
   * rien sur quoi travailler et le prestataire ne recevrait jamais de mission.
   */
  tradeId?: number;
  tradeAnswers?: Record<string, string | boolean>;
}
interface RegisterResult { token: string; user: User; }

export function useRegister() {
  return useMutation<RegisterResult, ApiError, RegisterInput>({
    mutationFn: async (input) => {
      const res = await apiClient.post('/auth/register', {
        name: input.name, email: input.email, password: input.password,
        password_confirmation: input.passwordConfirmation,
        phone: input.phone, locale: input.locale, accept_terms: input.acceptTerms,
        phone_verification_token: input.phoneVerificationToken,
        account_type: input.accountType,
        provider_kind: input.providerKind,
        client_kind: input.clientKind,
        company_name: input.companyName,
        vat_number: input.vatNumber,
        trade_id: input.tradeId,
        trade_answers: input.tradeAnswers,
        device_name: input.deviceName ?? 'cleanux-mobile',
      }, input.captchaToken ? { headers: { 'X-Turnstile-Token': input.captchaToken } } : undefined);
      await secureStore.setToken(res.data.token);
      return { token: res.data.token, user: res.data.user };
    },
  });
}
