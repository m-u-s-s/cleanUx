// Jest mock for pusher-js/react-native
const Pusher = jest.fn().mockImplementation(() => ({
  subscribe: jest.fn().mockReturnValue({
    bind: jest.fn(),
    unbind: jest.fn(),
  }),
  unsubscribe: jest.fn(),
  disconnect: jest.fn(),
  connection: { bind: jest.fn(), state: 'disconnected' },
}));

export default Pusher;
