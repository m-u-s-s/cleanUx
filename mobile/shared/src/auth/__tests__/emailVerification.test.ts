/**
 * LA RÈGLE QUI DÉCIDE DU MUR DE CONFIRMATION.
 *
 * Le serveur exige une adresse confirmée sur 530 de ses 537 routes authentifiées depuis le
 * 2026-08-27. Sans mur, l'application collectionnerait un `403 email_non_verifie` par écran.
 *
 * TOUT L'ENJEU EST DANS LE TROISIÈME CAS. Le parc déjà installé porte des jetons émis avant que le
 * serveur ne serve ce champ : traiter l'absence comme « non confirmée » enfermerait dehors des
 * comptes parfaitement valides. C'est la règle déjà écrite pour le dossier d'inscription et le
 * contrôle facial — l'inconnu laisse passer — et elle n'a de valeur que si on la mesure.
 */
import { adresseAConfirmer } from '../emailVerification';

describe('adresseAConfirmer', () => {
  it('barre la route quand le serveur dit que l’adresse n’est pas confirmée', () => {
    expect(adresseAConfirmer({ email_verified: false })).toBe(true);
    expect(adresseAConfirmer({ email_verified_at: null })).toBe(true);
  });

  it('laisse passer une adresse confirmée', () => {
    expect(adresseAConfirmer({ email_verified: true })).toBe(false);
    expect(adresseAConfirmer({ email_verified_at: '2026-08-27T10:00:00Z' })).toBe(false);
  });

  it('laisse passer quand le champ est ABSENT — le parc déjà installé', () => {
    expect(adresseAConfirmer({})).toBe(false);
    expect(adresseAConfirmer({ is_entreprise: true } as never)).toBe(false);
  });

  it('laisse passer quand il n’y a personne : c’est la connexion qui décide, pas ce mur', () => {
    expect(adresseAConfirmer(null)).toBe(false);
    expect(adresseAConfirmer(undefined)).toBe(false);
  });

  /**
   * Le booléen fait foi sur l'horodatage. `/auth/me` sert les deux ; si jamais ils se
   * contredisaient, c'est celui que le serveur calcule qui doit gagner.
   */
  it('tranche par le booléen quand les deux champs sont là', () => {
    expect(adresseAConfirmer({ email_verified: true, email_verified_at: null })).toBe(false);
    expect(adresseAConfirmer({ email_verified: false, email_verified_at: '2026-08-27T10:00:00Z' })).toBe(true);
  });
});
