import React from 'react';
import { View, FlatList, TouchableOpacity, Text, StyleSheet } from 'react-native';
import { Screen, Skeleton } from '@/ui';
import { useServiceCatalog, useBooking } from '@/booking';
import { colors, spacing, typography, radius } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingStep1'>;

export function BookingStep1Service({ navigation }: Props) {
  const { data: categories, isLoading } = useServiceCatalog();
  const { dispatch } = useBooking();

  const handleSelect = (cat: { id: number; name: string; slug: string }) => {
    dispatch({ type: 'SET_SERVICE', serviceId: cat.id, serviceName: cat.name, categorySlug: cat.slug });
    navigation.navigate('BookingStep2');
  };

  return (
    <Screen scroll>
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
          renderItem={({ item }) => (
            <TouchableOpacity style={styles.card} onPress={() => handleSelect(item)} activeOpacity={0.7}>
              <Text style={styles.cardText}>{item.name}</Text>
            </TouchableOpacity>
          )}
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
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.surface[200],
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 80,
  },
  cardText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[800],
    textAlign: 'center',
  },
});
