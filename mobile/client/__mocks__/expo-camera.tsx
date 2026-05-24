import React from 'react';
import { View } from 'react-native';

export function CameraView(props: any) {
  return <View {...props} testID="camera-view" />;
}

export function useCameraPermissions() {
  return [{ granted: true }, () => Promise.resolve({ granted: true })];
}
