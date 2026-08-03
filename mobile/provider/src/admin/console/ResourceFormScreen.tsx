import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Button, ErrorState, Screen, Skeleton, TextInput } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { readServerErrors, useResourceDetail, useResourceIndex, useResourceSave } from './hooks';
import type { ResourceField } from './types';

interface Params {
  resource: string;
  title: string;
  /** Absent = création. */
  id?: string | number;
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
  const { resource, title, id } = route.params;
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

/**
 * Le seul endroit qui connaît la correspondance type déclaré → composant natif.
 *
 * Les types non encore outillés retombent sur une saisie texte plutôt que de ne rien rendre : un
 * champ absent ferait échouer l'enregistrement sans que rien ne l'explique à l'écran.
 */
function FieldInput({
  field,
  value,
  error,
  onChange,
}: {
  field: ResourceField;
  value: unknown;
  error?: string;
  onChange: (value: unknown) => void;
}) {
  if (field.type === 'bool') {
    return (
      <View style={styles.switchRow}>
        <Text style={styles.switchLabel}>{field.label}</Text>
        <Switch
          accessibilityLabel={field.label}
          value={value === true || value === 1 || value === '1'}
          onValueChange={onChange}
        />
      </View>
    );
  }

  const clavier =
    field.type === 'email'
      ? 'email-address'
      : field.type === 'phone'
        ? 'phone-pad'
        : field.type === 'number' || field.type === 'money'
          ? 'decimal-pad'
          : 'default';

  return (
    <View style={{ paddingBottom: spacing.sm }}>
      <TextInput
        label={field.required ? `${field.label} *` : field.label}
        accessibilityLabel={field.label}
        value={value === undefined || value === null ? '' : String(value)}
        onChangeText={onChange}
        error={error}
        keyboardType={clavier}
        autoCapitalize={field.type === 'email' ? 'none' : 'sentences'}
        multiline={field.type === 'textarea'}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  heading: {
    ...typography.preset.headline,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.md,
  },
  switchLabel: { fontSize: typography.fontSize.base, color: colors.surface[900] },
  erreur: {
    color: colors.danger[500],
    fontSize: typography.fontSize.sm,
    paddingVertical: spacing.sm,
  },
});
