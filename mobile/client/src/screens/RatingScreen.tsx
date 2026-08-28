import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { Screen, Button, TextInput } from '@/ui';
import { useSubmitRating } from '@/ratings';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

type Props = NativeStackScreenProps<RootStackParamList, 'Rating'>;

function StarRow({ label, value, onChange }: { label: string; value: number; onChange: (v: number) => void }) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.starRow}>
      <Text style={styles.starLabel}>{label}</Text>
      <View style={styles.stars}>
        {[1, 2, 3, 4, 5].map(n => (
          <Text key={n} style={[styles.star, n <= value && styles.starActive]} onPress={() => onChange(n)}>★</Text>
        ))}
      </View>
    </View>
  );
}

export function RatingScreen({ route, navigation }: Props) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { bookingId } = route.params;
  const submit = useSubmitRating();
  const [overall, setOverall] = useState(0);
  const [punctuality, setPunctuality] = useState(0);
  const [quality, setQuality] = useState(0);
  const [communication, setCommunication] = useState(0);
  const [value, setValue] = useState(0);
  const [comment, setComment] = useState('');

  const handleSubmit = async () => {
    if (overall === 0) { Alert.alert('Note requise', 'Veuillez donner une note globale.'); return; }
    await submit.mutateAsync({ bookingId, overall, punctuality, quality, communication, value, comment });
    Alert.alert('Merci !', 'Votre avis a été enregistré.', [{ text: 'OK', onPress: () => navigation.goBack() }]);
  };

  return (
    <Screen scroll>
      <Text style={styles.title}>{tr('rating.evaluer_la_prestation')}</Text>
      <StarRow label={tr('rating.note_globale')} value={overall} onChange={setOverall} />
      <StarRow label={tr('rating.ponctualite')} value={punctuality} onChange={setPunctuality} />
      <StarRow label={tr('rating.qualite')} value={quality} onChange={setQuality} />
      <StarRow label={tr('rating.communication')} value={communication} onChange={setCommunication} />
      <StarRow label={tr('rating.rapport_qualite_prix')} value={value} onChange={setValue} />
      <TextInput
        label={tr('rating.commentaire_optionnel')}
        value={comment}
        onChangeText={setComment}
        multiline
        numberOfLines={3}
        placeholder={tr('rating.partagez_votre_experience')}
      />
      <Button
        label={tr('rating.envoyer')}
        onPress={handleSubmit}
        fullWidth
        size="lg"
        loading={submit.isPending}
        disabled={overall === 0}
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  starRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
  },
  starLabel: { fontSize: typography.fontSize.sm, color: t.text },
  stars: { flexDirection: 'row', gap: 4 },
  star: { fontSize: 28, color: t.border },
  starActive: { color: colors.accent.amber },
});
