import React, { useImperativeHandle } from 'react';
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

// forwardRef : le composant réel expose un impératif (animateToRegion) que ProviderMap
// appelle pour le recentrage unique. Sans forwardRef, React logue « Function components
// cannot be given refs » dès que le composant reçoit une ref.
const MapView = React.forwardRef<{ animateToRegion: (...args: any[]) => void }, any>(
  ({ children, testID, ...rest }: any, ref) => {
    // Deps vides : la même instance de jest.fn() est exposée sur tout le cycle de vie du
    // composant, pour qu'un test puisse l'observer sans la relire à chaque rendu.
    useImperativeHandle(ref, () => ({ animateToRegion: jest.fn() }), []);
    return (
      <View testID={testID ?? 'map-view'} {...rest}>{children}</View>
    );
  },
);

export default MapView;
