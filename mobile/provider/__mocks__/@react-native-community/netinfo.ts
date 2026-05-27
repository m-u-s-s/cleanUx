// Jest mock for @react-native-community/netinfo
const NetInfo = {
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
};

export default NetInfo;
export const addEventListener = NetInfo.addEventListener;
export const fetch = NetInfo.fetch;
