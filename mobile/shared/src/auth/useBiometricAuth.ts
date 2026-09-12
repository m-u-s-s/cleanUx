/**
 * Prompts the user with biometric authentication (Face ID / fingerprint).
 * Returns true on success, false if hardware not present, nothing enrolled,
 * or the user cancels / fails.
 * Uses dynamic import so the module is optional (not required in the client bundle).
 */
import { traduireMaintenant } from '@/i18n';

export async function authenticateWithBiometrics(): Promise<boolean> {
  try {
    // expo-local-authentication is an optional peer dep — dynamic import with type assertion
    type LocalAuth = {
      hasHardwareAsync(): Promise<boolean>;
      isEnrolledAsync(): Promise<boolean>;
      authenticateAsync(opts: { promptMessage: string; cancelLabel: string; fallbackLabel: string }): Promise<{ success: boolean }>;
    };
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const LocalAuthentication = await import('expo-local-authentication' as any) as LocalAuth;

    const compatible = await LocalAuthentication.hasHardwareAsync();
    if (!compatible) return false;

    const enrolled = await LocalAuthentication.isEnrolledAsync();
    if (!enrolled) return false;

    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: traduireMaintenant('use_biometric_auth.confirmer_votre_identite'),
      cancelLabel: 'Annuler',
      fallbackLabel: traduireMaintenant('use_biometric_auth.utiliser_le_mot_de_passe'),
    });

    return result.success;
  } catch {
    // expo-local-authentication not available
    return false;
  }
}
