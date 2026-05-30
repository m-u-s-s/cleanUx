import { parseBridgeMessage, INJECTED_BRIDGE_JS } from '../bridge';

describe('parseBridgeMessage', () => {
  it('parses each known message type', () => {
    expect(parseBridgeMessage(JSON.stringify({ type: 'ready' }))).toEqual({ type: 'ready' });
    expect(parseBridgeMessage(JSON.stringify({ type: 'requestBack' }))).toEqual({ type: 'requestBack' });
    expect(parseBridgeMessage(JSON.stringify({ type: 'openNative', route: '/booking/new' })))
      .toEqual({ type: 'openNative', route: '/booking/new' });
    expect(parseBridgeMessage(JSON.stringify({ type: 'sessionExpired' }))).toEqual({ type: 'sessionExpired' });
  });

  it('rejects unknown types and malformed json', () => {
    expect(parseBridgeMessage(JSON.stringify({ type: 'evil' }))).toBeNull();
    expect(parseBridgeMessage('not json')).toBeNull();
    expect(parseBridgeMessage(JSON.stringify({ noType: true }))).toBeNull();
  });

  it('rejects openNative without a string route', () => {
    expect(parseBridgeMessage(JSON.stringify({ type: 'openNative' }))).toBeNull();
    expect(parseBridgeMessage(JSON.stringify({ type: 'openNative', route: 123 }))).toBeNull();
  });

  it('exposes injected JS that posts a ready event', () => {
    expect(INJECTED_BRIDGE_JS).toContain('ReactNativeWebView');
    expect(INJECTED_BRIDGE_JS).toContain("type:'ready'");
  });
});
