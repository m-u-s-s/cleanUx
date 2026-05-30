import React from 'react';
import { View } from 'react-native';

// Minimal WebView stub for Jest: renders a View, exposes source.uri via
// accessibilityLabel for assertions, and forwards prop callbacks so tests can
// drive onMessage / onError / onLoadEnd.
export const WebView = React.forwardRef(function WebView(props: any, _ref) {
  return (
    <View
      testID="mock-webview"
      accessibilityLabel={props?.source?.uri ?? ''}
      onLayout={() => props?.onLoadEnd?.()}
    >
      {props.children}
    </View>
  );
});

export default WebView;
