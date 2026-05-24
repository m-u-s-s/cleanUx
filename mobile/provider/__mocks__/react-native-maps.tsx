import React from 'react';
import { View } from 'react-native';

const MockMapView = (props: any) => <View {...props} testID="map-view">{props.children}</View>;
const MockMarker = (props: any) => <View {...props} testID="map-marker" />;
const MockPolyline = (props: any) => <View {...props} testID="map-polyline" />;

export default MockMapView;
export const Marker = MockMarker;
export const Polyline = MockPolyline;
export const PROVIDER_DEFAULT = 'default';
