import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Button, ErrorState, Screen, Skeleton } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { readServerErrors, useResourceDetail, useResourceIndex, useResourceSave } from './hooks';
import { FieldInput } from './FieldInput';
import type { ResourceField } from './types';

interface Params {
  resource: string;
  title: string;
  /** Absent = création. */
  id?: string | number;
  /**
   * Valeurs imposées par le CONTEXTE d'où l'on vient.
   *
   * On crée une zone depuis l'écran d'un pays : ce pays n'est pas un choix à refaire. Le laisser
   * vide obligerait à le resélectionner, avec le risque de créer la zone dans le mauvais marché —
   * une erreur qui ne se voit qu'en cherchant une zone disparue.
   */
  prefill?: Record<string, unknown>;
}

/**
 * Le formulaire générique — création et édition.
 *
 * LES ERREURS SE POSENT CHAMP PAR CHAMP. Le serveur rend `{errors: {champ: [...]}}` ; les fondre
 * dans un message unique obligerait à relire tout le formulaire pour trouver la ligne fautive. La
 * validation reste côté serveur : le mobile ne connaît que le type et le caractère obligatoire,
 * pas les règles — une règle d'unicité ne se rejoue pas ici.
 */
export function ResourceFormScreen({ route }: { route: { params: Params } }) {
  const styles = stylesFor(useThemeColors());

  const { resource, title, id, prefill } = route.params;
  const navigation = useNavigation<{ goBack: () => void }>();

  const { data: liste, isLoading: chargementDescripteur, isError } = useResourceIndex(resource);
  const descripteur = liste?.pages[0]?.resource;

  const { data: existant, isLoading: chargementLigne } = useResourceDetail(
    resource,
    id ?? '',
  );

  const enregistrer = useResourceSave(resource, id);

  const [valeurs, setValeurs] = useState<Record<string, unknown>>({});
  const [erreursChamp, setErreursChamp] = useState<Record<string, string>>({});
  const [erreurGenerale, setErreurGenerale] = useState<string | null>(null);

  // En édition, le formulaire part des valeurs existantes. Le faire dans un effet plutôt qu'en
  // état initial : la ligne arrive après le premier rendu.
  useEffect(() => {
    if (id && existant) {
      setValeurs(existant as Record<string, unknown>);
    }
  }, [id, existant]);

  // En création, le contexte s'applique une fois — pas à chaque rendu, sinon il écraserait ce que
  // l'utilisateur vient de saisir dans le même champ.
  useEffect(() => {
    if (!id && prefill) {
      setValeurs((courant) => ({ ...prefill, ...courant }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  if (chargementDescripteur || (id && chargementLigne)) {
    return (
      <Screen>
        <View testID="resource-form-loading" style={{ paddingTop: spacing.md, gap: spacing.md }}>
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} width="100%" height={56} />
          ))}
        </View>
      </Screen>
    );
  }

  if (isError || !descripteur) {
    return (
      <Screen>
        <ErrorState message="Le formulaire n’a pas pu être chargé." />
      </Screen>
    );
  }

  const soumettre = () => {
    setErreursChamp({});
    setErreurGenerale(null);

    // On n'envoie QUE les champs du formulaire : renvoyer la ligne entière en édition posterait
    // des champs que le descripteur n'expose pas, et le serveur les refuserait de toute façon.
    const charge: Record<string, unknown> = {};
    for (const field of descripteur.form) {
      if (valeurs[field.key] !== undefined) {
        charge[field.key] = valeurs[field.key];
      }
    }

    enregistrer.mutate(charge, {
      onSuccess: () => navigation.goBack(),
      onError: (error) => {
        const { message, fields } = readServerErrors(error);
        setErreursChamp(fields);
        setErreurGenerale(Object.keys(fields).length > 0 ? null : message);
      },
    });
  };

  return (
    <Screen>
      <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
        <Text style={styles.heading}>
          {id ? `Modifier — ${title}` : `Créer — ${title}`}
        </Text>

        {descripteur.form.map((field) => (
          <FieldInput
            key={field.key}
            field={field}
            value={valeurs[field.key]}
            error={erreursChamp[field.key]}
            onChange={(value) => setValeurs((v) => ({ ...v, [field.key]: value }))}
          />
        ))}

        {erreurGenerale ? <Text style={styles.erreur}>{erreurGenerale}</Text> : null}

        <View style={{ paddingVertical: spacing.md }}>
          <Button
            label={id ? 'Enregistrer' : 'Créer'}
            onPress={soumettre}
            loading={enregistrer.isPending}
            fullWidth
          />
        </View>
      </ScrollView>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  heading: {
    ...typography.preset.headline,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  erreur: {
    color: colors.danger[500],
    fontSize: typography.fontSize.sm,
    paddingVertical: spacing.sm,
  },
});
