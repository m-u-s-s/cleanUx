import React from 'react';
import { View, FlatList, Text, Alert, StyleSheet } from 'react-native';
import { useStripe } from '@stripe/stripe-react-native';
import { Screen, Button, Badge, Skeleton, Divider } from '@/ui';
import { usePaymentMethods, useSetupIntent, useDeletePaymentMethod } from '@/payment';
import type { PaymentMethod } from '@/payment';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

export function SavedPaymentMethodsScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: methods, isLoading } = usePaymentMethods();
  const setupIntent = useSetupIntent();
  const deleteMethod = useDeletePaymentMethod();
  const { initPaymentSheet, presentPaymentSheet } = useStripe();

  const handleAddCard = async () => {
    try {
      const { client_secret } = await setupIntent.mutateAsync();
      const { error: initError } = await initPaymentSheet({
        setupIntentClientSecret: client_secret,
        merchantDisplayName: 'Brio',
        style: 'automatic',
      });
      if (initError) {
        Alert.alert('Erreur', initError.message);
        return;
      }

      const { error } = await presentPaymentSheet();
      if (error) {
        if (error.code !== 'Canceled') Alert.alert('Erreur', error.message);
      } else {
        Alert.alert('Carte ajoutée', 'Votre moyen de paiement a été enregistré.');
      }
    } catch (e: any) {
      Alert.alert('Erreur', e.message ?? "Impossible d'ajouter la carte.");
    }
  };

  const handleDelete = (method: PaymentMethod) => {
    Alert.alert('Supprimer', `Supprimer la carte •••• ${method.last4} ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: () => deleteMethod.mutate(method.id),
      },
    ]);
  };

  return (
    <Screen scroll>
      <Text style={styles.title}>Moyens de paiement</Text>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2].map(i => (
            <Skeleton key={i} width="100%" height={70} />
          ))}
        </View>
      ) : (
        <FlatList
          data={methods ?? []}
          scrollEnabled={false}
          keyExtractor={item => item.id}
          renderItem={({ item }) => (
            <View style={styles.card}>
              <View style={styles.cardInfo}>
                <Text style={styles.cardBrand}>{item.brand.toUpperCase()}</Text>
                <Text style={styles.cardLast4}>•••• {item.last4}</Text>
                <Text style={styles.cardExp}>
                  {item.exp_month}/{item.exp_year}
                </Text>
              </View>
              {item.is_default && <Badge label="Par défaut" variant="brand" />}
              <Button
                label="Supprimer"
                onPress={() => handleDelete(item)}
                variant="ghost"
                size="sm"
              />
            </View>
          )}
          ItemSeparatorComponent={() => <Divider />}
          ListEmptyComponent={
            <Text style={styles.empty}>Aucun moyen de paiement enregistré</Text>
          }
        />
      )}
      <Button
        label="Ajouter une carte"
        onPress={handleAddCard}
        fullWidth
        loading={setupIntent.isPending}
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.md,
  },
  skeletons: { gap: spacing.sm, marginBottom: spacing.md },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    gap: spacing.sm,
  },
  cardInfo: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  cardBrand: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  cardLast4: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
  },
  cardExp: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
  },
  empty: {
    fontSize: typography.fontSize.sm,
    color: t.textMuted,
    textAlign: 'center',
    marginVertical: spacing.lg,
  },
});
