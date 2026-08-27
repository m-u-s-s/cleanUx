import React from 'react';
import { View, Text, Image, Pressable, Linking, StyleSheet } from 'react-native';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ChatMessage } from './types';

export type PieceJointeDuMessageProps = {
  piece?: ChatMessage['attachment'];
};

/** Un nom lisible pour ce qui n'est pas une image : le type MIME seul ne parle à personne. */
function libelle(mime: string | null): string {
  if (!mime) {
    return 'Fichier joint';
  }
  if (mime === 'application/pdf') {
    return 'Document PDF';
  }
  if (mime.startsWith('audio/')) {
    return 'Message vocal';
  }
  if (mime.startsWith('video/')) {
    return 'Vidéo';
  }

  return 'Fichier joint';
}

/** Le poids, quand le serveur le connaît — sinon rien, plutôt qu'un « 0 Ko » faux. */
function poids(octets: number | null): string | null {
  if (!octets || octets <= 0) {
    return null;
  }

  return octets < 1024 * 1024
    ? `${Math.round(octets / 1024)} Ko`
    : `${(octets / (1024 * 1024)).toFixed(1)} Mo`;
}

/**
 * La pièce jointe d'un message : une image s'affiche, le reste s'annonce et s'ouvre au doigt.
 * Partagée par les deux applications, qui affichent la même discussion.
 */
export function PieceJointeDuMessage({ piece }: PieceJointeDuMessageProps) {
  const theme = useThemeColors();

  if (!piece?.url) {
    return null;
  }

  const ouvrir = () => {
    Linking.openURL(piece.url).catch(() => undefined);
  };

  if ((piece.mime_type ?? '').startsWith('image/')) {
    return (
      <Pressable
        onPress={ouvrir}
        accessibilityRole="imagebutton"
        accessibilityLabel="Pièce jointe, image"
        testID="piece-jointe-image-ouvrir"
      >
        <Image
          source={{ uri: piece.url }}
          style={[styles.image, { backgroundColor: theme.border }]}
          resizeMode="cover"
          testID="piece-jointe-image"
        />
      </Pressable>
    );
  }

  const taille = poids(piece.size_bytes);
  const nom = libelle(piece.mime_type);

  return (
    <Pressable
      onPress={ouvrir}
      style={[styles.fichier, { borderColor: theme.border }]}
      accessibilityRole="button"
      accessibilityLabel={taille ? `${nom}, ${taille}` : nom}
      testID="piece-jointe-fichier"
    >
      <View style={[styles.pastille, { backgroundColor: theme.border }]} />
      <Text style={[styles.nom, { color: theme.text }]} numberOfLines={1}>
        {nom}
      </Text>
      {taille ? <Text style={[styles.taille, { color: theme.textSecondary }]}>{taille}</Text> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  image: {
    width: 180,
    height: 130,
    borderRadius: radius.sm,
    marginTop: spacing.xs,
  },
  fichier: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    marginTop: spacing.xs,
    paddingVertical: spacing.xs,
    paddingHorizontal: spacing.sm,
    borderWidth: StyleSheet.hairlineWidth,
    borderRadius: radius.sm,
  },
  pastille: {
    width: 22,
    height: 22,
    borderRadius: radius.sm,
  },
  nom: {
    flexShrink: 1,
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
  },
  taille: {
    fontSize: typography.fontSize.xs,
  },
});
