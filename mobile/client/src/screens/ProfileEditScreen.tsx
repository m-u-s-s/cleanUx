import React, { useRef, useState } from 'react';
import { View, Text, Alert, TouchableOpacity, StyleSheet } from 'react-native';
import type { TextInput as RNTextInput } from 'react-native';
import { Screen, Button, TextInput, Avatar } from '@/ui';
import { useAuth } from '@/auth';
import { apiClient } from '@/api';
import { spacing, typography, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

type Props = NativeStackScreenProps<RootStackParamList, 'ProfileEdit'>;

export function ProfileEditScreen({ navigation }: Props) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { user, setUser } = useAuth();
  const [name, setName] = useState(user?.name ?? '');
  const [phone, setPhone] = useState(user?.phone ?? '');
  const [saving, setSaving] = useState(false);
  const [avatarUri, setAvatarUri] = useState<string | undefined>((user as any)?.avatar_url);
  const phoneRef = useRef<RNTextInput>(null);

  const pickAvatar = async () => {
    try {
      const ImagePicker = await import('expo-image-picker');
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        Alert.alert('Permission requise', 'Veuillez autoriser l\'accès à la galerie.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'] as any,
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.7,
      });
      if (!result.canceled && result.assets[0]) {
        const uri = result.assets[0].uri;
        setAvatarUri(uri);
        // Upload to server (best-effort)
        try {
          const formData = new FormData();
          formData.append('avatar', { uri, type: 'image/jpeg', name: 'avatar.jpg' } as any);
          await apiClient.post('/client/profile/avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
          });
        } catch {
          // best-effort — photo shown locally even if upload fails
        }
      }
    } catch {
      // expo-image-picker not available
    }
  };

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
      <TouchableOpacity onPress={pickAvatar} style={styles.avatarWrapper}>
        <Avatar name={user?.name ?? '?'} imageUri={avatarUri} size={80} />
        <Text style={styles.changePhotoText}>{tr('profile_edit.changer_la_photo')}</Text>
      </TouchableOpacity>
      <View style={styles.form}>
        <TextInput label="Nom" value={name} onChangeText={setName} autoFocus returnKeyType="next" onSubmitEditing={() => phoneRef.current?.focus()} />
        <TextInput ref={phoneRef} label={tr('profile_edit.telephone')} value={phone} onChangeText={setPhone} keyboardType="phone-pad" returnKeyType="done" />
        <Button label="Enregistrer" onPress={handleSave} fullWidth loading={saving} />
      </View>
    </Screen>
  );
}

/*
 * L'ECRAN N'AVAIT AUCUNE CONSCIENCE DU THEME. « Changer la photo » portait `brand.500`,
 * un indigo fige : 4,47 sur le blanc et 3,88 sur le panneau de nuit — sous le seuil des
 * DEUX cotes, la signature d'une couleur figee sur un fond qui bouge.
 */
const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  avatarWrapper: { alignItems: 'center', marginBottom: spacing.lg, marginTop: spacing.md },
  changePhotoText: { color: t.brandText, fontSize: typography.fontSize.sm, marginTop: spacing.xs },
  form: { gap: spacing.md },
});
