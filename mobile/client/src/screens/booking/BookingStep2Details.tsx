import React, { useRef, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import type { TextInput as RNTextInput } from 'react-native';
import { Screen, Button, TextInput, Divider, ProgressBar } from '@/ui';
import { useBooking } from '@/booking';
import { colors, spacing, typography } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingStep2'>;

export function BookingStep2Details({ navigation }: Props) {
  const { state, dispatch } = useBooking();
  const [surface, setSurface] = useState(state.details.surface?.toString() ?? '');
  const [comment, setComment] = useState(state.details.comment);
  const commentRef = useRef<RNTextInput>(null);

  const handleNext = () => {
    dispatch({
      type: 'SET_DETAILS',
      details: {
        surface: surface ? Number(surface) : undefined,
        frequency: undefined,
        options: [],
        comment,
      },
    });
    navigation.navigate('BookingStep3');
  };

  return (
    <Screen scroll>
      <ProgressBar step={2} totalSteps={6} />
      <Text style={styles.title}>Détails</Text>
      <Text style={styles.subtitle}>Précisez votre demande pour {state.serviceName}</Text>
      <View style={styles.form}>
        <TextInput
          label="Surface (m²)"
          value={surface}
          onChangeText={setSurface}
          keyboardType="numeric"
          placeholder="Ex: 60"
          autoFocus
          returnKeyType="next"
          onSubmitEditing={() => commentRef.current?.focus()}
        />
        <Divider />
        <TextInput
          ref={commentRef}
          label="Commentaire (optionnel)"
          value={comment}
          onChangeText={setComment}
          multiline
          numberOfLines={3}
          placeholder="Instructions spéciales, accès, matériel…"
          returnKeyType="done"
        />
      </View>
      <Button label="Suivant" onPress={handleNext} fullWidth size="lg" />
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    marginBottom: spacing.lg,
  },
  form: {
    gap: spacing.md,
    marginBottom: spacing.xl,
  },
});
