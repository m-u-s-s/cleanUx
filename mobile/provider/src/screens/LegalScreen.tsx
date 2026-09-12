import React from 'react';
import { Text, StyleSheet } from 'react-native';
import { Screen } from '@/ui';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { traduireMaintenant } from '@/i18n';

type Props = NativeStackScreenProps<RootStackParamList, 'Legal'>;

const CONTENT = {
  terms: {
    title: "Conditions Générales d'Utilisation",
    body: `Dernière mise à jour : Juillet 2026

1. OBJET
Les présentes CGU régissent ltraduireMaintenant('legal.utilisation_de_l_2')application brio, marketplace de services à domicile multi-métiers.

2. INSCRIPTION
LtraduireMaintenant('legal.utilisateur_doit_etre_majeur_et_fournir_des_2')inscription.

3. SERVICES
brio met en relation des clients avec des prestataires de services. brio n'est pas prestataire des services proposés.

4. PAIEMENT
Les paiements sont traités via Stripe. Le montant est pré-autorisé à la réservation et capturé à la fin de la mission.

5. ANNULATION
Les conditions dtraduireMaintenant('legal.annulation_varient_selon_le_delai_consultez_la_2')annulation dans l'application.

6. RESPONSABILITÉ
brio agit en tant qu'intermédiaire. La responsabilité des prestations incombe aux prestataires.

7. DONNÉES PERSONNELLES
Voir notre Politique de Confidentialité.

8. CONTACT
support@brio.com`,
  },
  privacy: {
    title: traduireMaintenant('legal.politique_de_confidentialite_2'),
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
Accès, rectification, suppression, portabilité, opposition. Exercez-les depuis l'onglet Profil > RGPD ou à dpo@brio.com.

6. SOUS-TRAITANTS
Stripe (paiements), Sentry (crash reporting), Expo (notifications push).

7. CONTACT DPO
dpo@brio.com`,
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
