import React, { useRef, useState } from 'react';
import { View, Text, FlatList, TouchableOpacity, StyleSheet } from 'react-native';
import type { TextInput as RNTextInput } from 'react-native';
import { Screen, Button, TextInput, ProgressBar } from '@/ui';
import { useBooking, usePostalAutocomplete } from '@/booking';
import { colors, spacing, typography, radius } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingStep3'>;

export function BookingStep3Coordinates({ navigation }: Props) {
  const { state, dispatch } = useBooking();
  const [address, setAddress] = useState(state.coordinates.address);
  const [city, setCity] = useState(state.coordinates.city);
  const [postal, setPostal] = useState(state.coordinates.postalCode);
  const { data: postalSuggestions } = usePostalAutocomplete(postal);
  const postalRef = useRef<RNTextInput>(null);
  const cityRef = useRef<RNTextInput>(null);

  const selectPostal = (item: { postal_code: string; city: string }) => {
    setPostal(item.postal_code);
    setCity(item.city);
  };

  const handleNext = () => {
    dispatch({
      type: 'SET_COORDINATES',
      coordinates: { address, city, postalCode: postal },
    });
    navigation.navigate('BookingStep4');
  };

  const isValid = address.length > 3 && city.length > 1 && postal.length >= 4;

  return (
    <Screen scroll>
      <ProgressBar step={3} totalSteps={5} />
      <Text style={styles.title}>Adresse</Text>
      <Text style={styles.subtitle}>Où doit-on intervenir ?</Text>
      <View style={styles.form}>
        <TextInput
          label="Adresse"
          value={address}
          onChangeText={setAddress}
          placeholder="123 Rue de la Paix"
          autoFocus
          returnKeyType="next"
          onSubmitEditing={() => postalRef.current?.focus()}
        />
        <TextInput
          ref={postalRef}
          label="Code postal"
          value={postal}
          onChangeText={setPostal}
          keyboardType="numeric"
          placeholder="75001"
          returnKeyType="next"
          onSubmitEditing={() => cityRef.current?.focus()}
        />
        {postalSuggestions && postalSuggestions.length > 0 && (
          <FlatList
            data={postalSuggestions.slice(0, 5)}
            scrollEnabled={false}
            keyExtractor={(item) => `${item.postal_code}-${item.city}`}
            renderItem={({ item }) => (
              <TouchableOpacity style={styles.suggestion} onPress={() => selectPostal(item)}>
                <Text style={styles.suggestionText}>
                  {item.postal_code} — {item.city}
                </Text>
              </TouchableOpacity>
            )}
          />
        )}
        <TextInput
          ref={cityRef}
          label="Ville"
          value={city}
          onChangeText={setCity}
          placeholder="Paris"
          returnKeyType="done"
        />
      </View>
      <Button label="Suivant" onPress={handleNext} fullWidth size="lg" disabled={!isValid} />
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
  suggestion: {
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.surface[200],
  },
  suggestionText: {
    fontSize: typography.fontSize.sm,
    color: colors.brand[600],
  },
});
