import React from 'react';
import { View } from 'react-native';

export function StripeProvider({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}

export function useStripe() {
  return {
    initPaymentSheet: jest.fn().mockResolvedValue({ error: null }),
    presentPaymentSheet: jest.fn().mockResolvedValue({ error: null }),
    confirmPayment: jest.fn().mockResolvedValue({ paymentIntent: {}, error: null }),
  };
}

export function CardField(props: any) {
  return <View {...props} testID="card-field" />;
}
