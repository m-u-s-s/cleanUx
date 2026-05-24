import React, { useState } from 'react';
import { View, Text, Switch, StyleSheet } from 'react-native';
import { Screen, Button, TextInput } from '@/ui';
import { useBooking } from '@/booking';
import { colors, spacing, typography } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingStep4'>;

export function BookingStep4Scheduling({ navigation }: Props) {
  const { state, dispatch } = useBooking();
  const [isAsap, setIsAsap] = useState(state.scheduling.isAsap);
  const [date, setDate] = useState(state.scheduling.date);
  const [time, setTime] = useState(state.scheduling.time);

  const handleNext = () => {
    dispatch({
      type: 'SET_SCHEDULING',
      scheduling: { date, time, isAsap },
    });
    navigation.navigate('BookingStep5');
  };

  const isValid = isAsap || (date.length >= 8 && time.length >= 4);

  return (
    <Screen scroll>
      <Text style={styles.title}>Quand ?</Text>
      <Text style={styles.subtitle}>Planifiez votre intervention</Text>
      <View style={styles.form}>
        <View style={styles.asapRow}>
          <Text style={styles.asapLabel}>Dès que possible</Text>
          <Switch
            value={isAsap}
            onValueChange={setIsAsap}
            trackColor={{ true: colors.brand[500], false: colors.surface[300] }}
          />
        </View>
        {!isAsap && (
          <>
            <TextInput
              label="Date"
              value={date}
              onChangeText={setDate}
              placeholder="2026-06-15"
            />
            <TextInput
              label="Heure"
              value={time}
              onChangeText={setTime}
              placeholder="09:00"
            />
          </>
        )}
      </View>
      <Button label="Confirmer" onPress={handleNext} fullWidth size="lg" disabled={!isValid} />
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
  asapRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
  },
  asapLabel: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[800],
  },
});
