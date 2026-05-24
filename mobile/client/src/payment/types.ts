export interface PaymentMethod {
  id: string;
  brand: string;
  last4: string;
  exp_month: number;
  exp_year: number;
  is_default: boolean;
}

export interface PaymentIntentResult {
  client_secret: string;
  payment_intent_id?: string;
  amount?: number;
  currency?: string;
}

export interface SetupIntentResult {
  client_secret: string;
}
