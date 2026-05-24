import React, { useState } from 'react';
import { View, Alert, StyleSheet } from 'react-native';
import { Screen, Button, TextInput } from '@/ui';
import { useAuth } from '@/auth';
import { apiClient } from '@/api';
import { spacing } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'ProfileEdit'>;

export function ProfileEditScreen({ navigation }: Props) {
  const { user, setUser } = useAuth();
  const [name, setName] = useState(user?.name ?? '');
  const [phone, setPhone] = useState(user?.phone ?? '');
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    try {
      const res = await apiClient.put('/client/profile', { name, phone });
      setUser(res.data.user ?? res.data);
      Alert.alert('Profil mis à jour');
      navigation.goBack();
    } catch (e: any) {
      Alert.alert('Erreur', e.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen scroll>
      <View style={styles.form}>
        <TextInput label="Nom" value={name} onChangeText={setName} />
        <TextInput label="Téléphone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
        <Button label="Enregistrer" onPress={handleSave} fullWidth loading={saving} />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: { gap: spacing.md, marginTop: spacing.md },
});
