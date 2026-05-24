'use strict';
/**
 * Local Reanimated mock that avoids the NativeWorklets initialization error
 * in the Jest/Node environment. Provides stub implementations for all hooks
 * and animated primitives used by PulseDot and Skeleton.
 */
const React = require('react');
const { View, Text, Image, ScrollView } = require('react-native');

const useSharedValue = (init) => ({ value: init });
const useAnimatedStyle = (cb) => cb();
const withRepeat = (animation) => animation;
const withTiming = (toValue) => toValue;
const withSpring = (toValue) => toValue;
const withDelay = (_, animation) => animation;
const withSequence = (...animations) => animations[animations.length - 1];
const withDecay = () => 0;
const runOnJS = (fn) => fn;
const runOnUI = (fn) => fn;
const useDerivedValue = (fn) => ({ value: fn() });
const useAnimatedRef = () => ({ current: null });
const useAnimatedScrollHandler = () => () => {};
const useAnimatedGestureHandler = () => () => {};
const interpolate = (value) => value;
const interpolateColor = () => '#000000';
const useAnimatedReaction = () => {};
const cancelAnimation = () => {};

const Easing = {
  linear: (t) => t,
  ease: (t) => t,
  quad: (t) => t,
  cubic: (t) => t,
  sin: (t) => t,
  circle: (t) => t,
  exp: (t) => t,
  elastic: () => (t) => t,
  back: () => (t) => t,
  bounce: (t) => t,
  bezier: () => (t) => t,
  bezierFn: () => (t) => t,
  in: (easing) => easing,
  out: (easing) => easing,
  inOut: (easing) => easing,
};

const createAnimatedComponent = (Component) => {
  const AnimatedComponent = React.forwardRef((props, ref) =>
    React.createElement(Component, { ...props, ref }),
  );
  AnimatedComponent.displayName = `Animated(${Component.displayName || Component.name || 'Component'})`;
  return AnimatedComponent;
};

const AnimatedView = createAnimatedComponent(View);
const AnimatedText = createAnimatedComponent(Text);
const AnimatedImage = createAnimatedComponent(Image);
const AnimatedScrollView = createAnimatedComponent(ScrollView);

module.exports = {
  default: {
    View: AnimatedView,
    Text: AnimatedText,
    Image: AnimatedImage,
    ScrollView: AnimatedScrollView,
    createAnimatedComponent,
    FlatList: require('react-native').FlatList,
  },
  // Named exports
  View: AnimatedView,
  Text: AnimatedText,
  Image: AnimatedImage,
  ScrollView: AnimatedScrollView,
  createAnimatedComponent,
  useSharedValue,
  useAnimatedStyle,
  useDerivedValue,
  useAnimatedRef,
  useAnimatedScrollHandler,
  useAnimatedGestureHandler,
  useAnimatedReaction,
  withTiming,
  withSpring,
  withRepeat,
  withDelay,
  withSequence,
  withDecay,
  runOnJS,
  runOnUI,
  interpolate,
  interpolateColor,
  cancelAnimation,
  Easing,
  Extrapolation: { CLAMP: 'clamp', EXTEND: 'extend', IDENTITY: 'identity' },
  ReduceMotion: { System: 'system', Always: 'always', Never: 'never' },
  // Re-export FlatList stub
  FlatList: require('react-native').FlatList,
};
