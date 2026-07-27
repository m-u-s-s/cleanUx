import React from 'react';
import { View } from 'react-native';

// Stub des composants natifs de react-native-maps pour Jest : ils rendent des View
// porteuses de testID, ce qui permet d'assertion sur les marqueurs sans moteur de carte.
export const Marker = ({ children, testID, ...rest }: any) => (
  <View testID={testID ?? 'map-marker'} {...rest}>{children}</View>
);

export const Callout = ({ children, ...rest }: any) => (
  <View testID="map-callout" {...rest}>{children}</View>
);

export const Polyline = (props: any) => <View testID="map-polyline" {...props} />;

export const PROVIDER_DEFAULT = 'default';
export const PROVIDER_GOOGLE = 'google';

const MapView = ({ children, testID, ...rest }: any) => (
  <View testID={testID ?? 'map-view'} {...rest}>{children}</View>
);

export default MapView;
