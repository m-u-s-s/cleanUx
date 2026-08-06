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
  /**
   * Casquette SOCIÉTÉ. `/api/auth/me` expose ces deux champs, mais le type ne les déclarait pas :
   * l'application ne pouvait donc pas distinguer un indépendant d'un membre de société, ni une
   * société cliente d'une société prestataire — deux espaces différents.
   *
   * Le serveur les rendait déjà à la connexion ; la reprise de session les a rejoints en même
   * temps que la correction de `me` (phase 0), sans quoi l'aiguillage aurait été juste au premier
   * lancement et faux à tous les suivants.
   */
  is_entreprise?: boolean;
  organization_type?: 'client_company' | 'provider_company' | 'provider_solo' | 'hybrid' | null;
  organization_account_id?: number | null;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    public errorCode: string,
    message: string,
    public errors?: Record<string, string[]>,
    /**
     * Le CORPS du refus, tel quel.
     *
     * Le moteur d'administration répond parfois plus que `error` et `errors` : un refus de
     * suppression liste ses RAISONS — « 3 zones rattachées », « 12 missions en cours ». Sans ce
     * champ, l'intercepteur les jetait ici, et l'écran affichait « une erreur est survenue » alors
     * que le serveur venait d'expliquer précisément quoi faire.
     */
    public payload?: Record<string, unknown>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
