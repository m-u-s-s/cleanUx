import * as LocalAuthentication from 'expo-local-authentication';

/**
 * Prompts the user with biometric authentication (Face ID / fingerprint).
 * Returns true on success, false if hardware not present, nothing enrolled,
 * or the user cancels / fails.
 */
export async function authenticateWithBiometrics(): Promise<boolean> {
  const compatible = await LocalAuthentication.hasHardwareAsync();
  if (!compatible) return false;

  const enrolled = await LocalAuthentication.isEnrolledAsync();
  if (!enrolled) return false;

  const result = await LocalAuthentication.authenticateAsync({
    promptMessage: 'Confirmer votre identite',
    cancelLabel: 'Annuler',
    fallbackLabel: 'Utiliser le mot de passe',
  });

  return result.success;
}
