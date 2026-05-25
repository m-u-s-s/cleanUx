import React, { useState } from 'react';
import { View, FlatList, TouchableOpacity, Text, StyleSheet } from 'react-native';
import { Screen, Skeleton, ProgressBar, AnimatedListItem } from '@/ui';
import { useServiceCatalog, useBooking } from '@/booking';
import { colors, spacing, typography, radius, useThemeColors } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingStep1'>;

export function BookingStep1Service({ navigation }: Props) {
  const { data: categories, isLoading } = useServiceCatalog();
  const { dispatch } = useBooking();
  const themeColors = useThemeColors();
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const handleSelect = (cat: { id: number; name: string; slug: string }) => {
    setSelectedId(cat.id);
    dispatch({ type: 'SET_SERVICE', serviceId: cat.id, serviceName: cat.name, categorySlug: cat.slug });
    // Brief highlight before navigating
    setTimeout(() => navigation.navigate('BookingStep2'), 180);
  };

  return (
    <Screen scroll>
      <ProgressBar step={1} totalSteps={5} />
      <Text style={styles.title}>Quel service ?</Text>
      <Text style={styles.subtitle}>Choisissez le type de prestation</Text>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3, 4, 5, 6].map(i => (
            <Skeleton key={i} width="48%" height={80} borderRadius={radius.md} />
          ))}
        </View>
      ) : (
        <FlatList
          data={categories}
          numColumns={2}
          scrollEnabled={false}
          columnWrapperStyle={styles.row}
          keyExtractor={item => String(item.id)}
          renderItem={({ item, index }) => {
            const isSelected = selectedId === item.id;
            return (
              <AnimatedListItem index={index}>
                <TouchableOpacity
                  style={[
                    styles.card,
                    { backgroundColor: themeColors.card },
                    isSelected && styles.cardSelected,
                  ]}
                  onPress={() => handleSelect(item)}
                  activeOpacity={0.7}
                  accessibilityLabel={item.name}
                  accessibilityRole="button"
                >
                  {isSelected && <Text style={styles.checkmark}>✓</Text>}
                  <Text style={[styles.cardText, isSelected && styles.cardTextSelected]}>
                    {item.name}
                  </Text>
                </TouchableOpacity>
              </AnimatedListItem>
            );
          }}
        />
      )}
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
  skeletons: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
  },
  row: {
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
  card: {
    flex: 1,
    borderRadius: radius.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.surface[200],
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 80,
  },
  cardSelected: {
    borderColor: colors.brand[500],
    borderWidth: 2,
    backgroundColor: colors.brand[50],
  },
  cardText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[800],
    textAlign: 'center',
  },
  cardTextSelected: {
    color: colors.brand[700],
    fontWeight: typography.fontWeight.semibold,
  },
  checkmark: {
    fontSize: 16,
    color: colors.brand[500],
    marginBottom: 4,
  },
});
