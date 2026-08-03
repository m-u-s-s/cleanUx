export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: string;
  locale: string;
  email_verified_at: string | null;
  created_at: string;
  // SP2 — premium gate for choosing a brand-new provider. Authoritative gate is
  // backend (customerProfile->isPremium()); mobile reads this hint optimistically
  // when present and falls back to the upsell encart otherwise.
  is_premium?: boolean;
  /**
   * Administrateur de plateforme. Sert d'AIGUILLAGE D'INTERFACE — quel espace ouvrir au
   * démarrage — et rien d'autre : l'autorité reste le serveur, qui garde chaque route.
   * Servi par `/auth/login` comme par `/auth/me`, sans quoi la reprise de session dégraderait
   * l'administrateur en compte ordinaire.
   */
  is_admin?: boolean;
  /**
   * Casquette prestataire. Un compte peut porter les DEUX : un administrateur qui intervient
   * aussi sur le terrain existe, et le forcer à tenir deux comptes lui donnerait deux historiques
   * et deux facturations. Ce drapeau est ce qui permet de lui proposer de choisir son espace.
   */
  is_provider?: boolean;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    public errorCode: string,
    message: string,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
