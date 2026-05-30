/**
 * Fixed, enumerated message protocol between an embedded web page and the
 * native WebView host. Not arbitrary RPC — only these shapes are accepted.
 */
export type BridgeMessage =
  | { type: 'ready' }
  | { type: 'requestBack' }
  | { type: 'openNative'; route: string }
  | { type: 'sessionExpired' }
  | { type: 'error'; message?: string };

const KNOWN_TYPES = ['ready', 'requestBack', 'openNative', 'sessionExpired', 'error'];

export function parseBridgeMessage(raw: string): BridgeMessage | null {
  try {
    const msg = JSON.parse(raw);
    if (!msg || typeof msg.type !== 'string' || !KNOWN_TYPES.includes(msg.type)) {
      return null;
    }
    if (msg.type === 'openNative' && typeof msg.route !== 'string') {
      return null;
    }
    return msg as BridgeMessage;
  } catch {
    return null;
  }
}

/**
 * Injected into every embedded page. Exposes window.CleanUxBridge for pages
 * that want to hand off to native, and announces readiness. Trailing `true;`
 * is required by react-native-webview's injectedJavaScript contract.
 */
export const INJECTED_BRIDGE_JS = `
(function(){
  if (window.CleanUxBridge) { return; }
  var post = function(msg){ if(window.ReactNativeWebView){ window.ReactNativeWebView.postMessage(JSON.stringify(msg)); } };
  window.CleanUxBridge = {
    post: post,
    back: function(){ post({type:'requestBack'}); },
    openNative: function(route){ post({type:'openNative', route: route}); }
  };
  post({type:'ready'});
})();
true;
`;
