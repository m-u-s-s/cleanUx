import React, { forwardRef, useCallback } from 'react';
import { StyleSheet } from 'react-native';
import GorhomBottomSheet, { BottomSheetBackdrop, BottomSheetView } from '@gorhom/bottom-sheet';
import type { BottomSheetDefaultBackdropProps } from '@gorhom/bottom-sheet/lib/typescript/components/bottomSheetBackdrop/types';
import {radius, spacing } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface BottomSheetProps {
  snapPoints: (string | number)[];
  children: React.ReactNode;
  onClose?: () => void;
}

export const BottomSheet = forwardRef<GorhomBottomSheet, BottomSheetProps>(
  ({ snapPoints, children, onClose }, ref) => {
    const styles = stylesFor(useThemeColors());

    const renderBackdrop = useCallback(
      (props: BottomSheetDefaultBackdropProps) => (
        <BottomSheetBackdrop {...props} disappearsOnIndex={-1} appearsOnIndex={0} opacity={0.5} />
      ),
      [],
    );

    return (
      <GorhomBottomSheet
        ref={ref}
        snapPoints={snapPoints}
        enablePanDownToClose
        backdropComponent={renderBackdrop}
        onClose={onClose}
        index={-1}
        handleIndicatorStyle={styles.indicator}
        backgroundStyle={styles.background}
      >
        <BottomSheetView style={styles.content}>
          {children}
        </BottomSheetView>
      </GorhomBottomSheet>
    );
  },
);

BottomSheet.displayName = 'BottomSheet';

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  background: { backgroundColor: t.bg, borderTopLeftRadius: radius.xl, borderTopRightRadius: radius.xl },
  indicator: { backgroundColor: t.border, width: 40 },
  content: { paddingHorizontal: spacing.md, paddingBottom: spacing.xl },
});
