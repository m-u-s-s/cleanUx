import React from 'react';
import { StyleSheet, Switch, Text, View } from 'react-native';
import { TextInput } from '@/ui';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { ResourceField } from './types';

/**
 * Le SEUL endroit qui connaît la correspondance type déclaré → composant natif.
 *
 * Extrait du formulaire pour être partagé avec la feuille de saisie des actions : le motif d'un
 * refus est un champ comme un autre, et le dupliquer aurait donné deux rendus à maintenir — donc
 * un des deux qui finit par ignorer un type.
 *
 * Les types non encore outillés retombent sur une saisie texte plutôt que de ne rien rendre : un
 * champ absent ferait échouer l'envoi sans que rien ne l'explique à l'écran.
 */
export function FieldInput({
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
  const styles = stylesFor(useThemeColors());

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

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.md,
  },
  switchLabel: { fontSize: typography.fontSize.base, color: t.text },
});
