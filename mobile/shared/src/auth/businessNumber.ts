/**
 * Contrôle de clé du numéro d'entreprise, côté client.
 *
 * Miroir exact de `App\Support\Validation\BusinessNumber` : le serveur reste l'autorité, mais un
 * numéro invalide doit se signaler pendant la frappe plutôt qu'après un aller-retour réseau. Toute
 * évolution d'un côté doit se répercuter de l'autre — les deux implémentations sont couvertes par
 * les mêmes numéros d'exemple.
 */

/** Séparateurs usuels : « BE 0123.456.749 », « 123 456 789 », « 0123-456-749 ». */
export function normaliseBusinessNumber(raw: string): string {
  return raw.trim().replace(/[\s.\-/]/g, '').toUpperCase();
}

export function isValidBusinessNumber(raw: string): boolean {
  const value = normaliseBusinessNumber(raw);

  if (value === '') return false;

  // Un préfixe BE/FR engage le schéma du pays : c'est lui, et non le repli générique du bas de
  // fonction, qui tranche. Sans cette sortie anticipée, « BE0202239951X » y retomberait.
  if (value.startsWith('BE')) {
    const m = /^BE(\d{10})$/.exec(value);
    return m !== null && isValidBelgianEnterpriseNumber(m[1]!);
  }

  if (value.startsWith('FR')) {
    const m = /^FR([0-9A-Z]{2})(\d{9})$/.exec(value);
    return m !== null && isValidFrenchVatKey(m[1]!, m[2]!) && isLuhnValid(m[2]!);
  }

  // Numéro national sans préfixe : la longueur suffit à le désigner.
  if (/^\d{10}$/.test(value)) return isValidBelgianEnterpriseNumber(value);
  if (/^\d{9}$/.test(value)) return isLuhnValid(value);
  if (/^\d{14}$/.test(value)) return isValidSiret(value);

  // Autre pays de l'Union : forme plausible, clé non vérifiée. Le marché visé est BE/FR ; mieux
  // vaut laisser passer un numéro luxembourgeois que le rejeter à tort — le serveur revérifie.
  return /^[A-Z]{2}[0-9A-Z]{2,12}$/.test(value);
}

/** BCE/KBO : dix chiffres dont les deux derniers valent `97 - (base mod 97)`. */
function isValidBelgianEnterpriseNumber(digits: string): boolean {
  if (digits[0] !== '0' && digits[0] !== '1') return false;

  const base = Number(digits.slice(0, 8));
  const check = Number(digits.slice(8, 10));

  return 97 - (base % 97) === check;
}

/**
 * TVA française : clé numérique `(12 + 3 × (SIREN mod 97)) mod 97`. Les clés alphabétiques
 * (numéros anciens) ne suivent pas cette règle, le SIREN restant contrôlé par Luhn.
 */
function isValidFrenchVatKey(key: string, siren: string): boolean {
  if (!/^\d{2}$/.test(key)) return true;

  return (12 + 3 * (Number(siren) % 97)) % 97 === Number(key);
}

/**
 * SIRET : SIREN (9) + NIC (5), l'ensemble validé par Luhn. La Poste fait exception — ses SIRET
 * commencent par 356000000 et voient la somme de leurs chiffres contrôlée modulo 5.
 */
function isValidSiret(digits: string): boolean {
  if (digits.startsWith('356000000')) {
    return digits.split('').reduce((sum, d) => sum + Number(d), 0) % 5 === 0;
  }

  return isLuhnValid(digits) && isLuhnValid(digits.slice(0, 9));
}

function isLuhnValid(digits: string): boolean {
  let sum = 0;
  let double = false;

  for (let i = digits.length - 1; i >= 0; i--) {
    let digit = Number(digits[i]);

    if (double) {
      digit *= 2;
      if (digit > 9) digit -= 9;
    }

    sum += digit;
    double = !double;
  }

  return sum % 10 === 0;
}
