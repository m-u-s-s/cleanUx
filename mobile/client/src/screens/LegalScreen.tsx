import React from 'react';
import { Text, StyleSheet } from 'react-native';
import { Screen } from '@/ui';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'Legal'>;

const CONTENT = {
  terms: {
    title: "Conditions Générales d'Utilisation",
    body: `Dernière mise à jour : Mai 2026

1. OBJET
Les présentes CGU régissent l'utilisation de l'application CleanUx, marketplace de services à domicile multi-métiers.

2. INSCRIPTION
L'utilisateur doit être majeur et fournir des informations exactes lors de l'inscription.

3. SERVICES
CleanUx met en relation des clients avec des prestataires de services. CleanUx n'est pas prestataire des services proposés.

4. PAIEMENT
Les paiements sont traités via Stripe. Le montant est pré-autorisé à la réservation et capturé à la fin de la mission.

5. ANNULATION
Les conditions d'annulation varient selon le délai. Consultez la politique d'annulation dans l'application.

6. RESPONSABILITÉ
CleanUx agit en tant qu'intermédiaire. La responsabilité des prestations incombe aux prestataires.

7. DONNÉES PERSONNELLES
Voir notre Politique de Confidentialité.

8. CONTACT
support@cleanux.com`,
  },
  privacy: {
    title: 'Politique de Confidentialité',
    body: `Dernière mise à jour : Mai 2026

1. DONNÉES COLLECTÉES
- Identité (nom, email, téléphone)
- Adresses de prestation
- Données de paiement (traitées par Stripe)
- Géolocalisation (pendant les missions)
- Historique des réservations

2. FINALITÉS
- Fourniture du service de mise en relation
- Paiement et facturation
- Communication (notifications, chat)
- Amélioration du service

3. BASE LÉGALE
Exécution du contrat (Art. 6.1.b RGPD) et consentement pour la géolocalisation.

4. DURÉE DE CONSERVATION
Données conservées pendant la durée de la relation + 3 ans.

5. VOS DROITS (RGPD)
Accès, rectification, suppression, portabilité, opposition. Exercez-les depuis l'onglet Profil > RGPD ou à dpo@cleanux.com.

6. SOUS-TRAITANTS
Stripe (paiements), Sentry (crash reporting), Expo (notifications push).

7. CONTACT DPO
dpo@cleanux.com`,
  },
};

export function LegalScreen({ route }: Props) {
  const styles = stylesFor(useThemeColors());

  const { type } = route.params;
  const content = CONTENT[type];

  return (
    <Screen scroll>
      <Text style={styles.title}>{content.title}</Text>
      <Text style={styles.body}>{content.body}</Text>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  body: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    lineHeight: 22,
  },
});
