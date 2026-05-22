declare global {
  interface Window {
    Echo: {
      private: (channel: string) => { listen: (event: string, handler: (payload: unknown) => void) => unknown };
      leaveChannel: (channel: string) => void;
    };
  }
}

type Handlers = Record<string, (payload: unknown) => void>;

export function useReverbChannel(channelName: string, handlers: Handlers) {
  if (typeof window === 'undefined' || !window.Echo) {
    return { unsubscribe: () => {} };
  }

  const channel = window.Echo.private(channelName);
  Object.entries(handlers).forEach(([event, handler]) => {
    channel.listen(`.${event}`, handler);
  });

  return {
    unsubscribe: () => {
      window.Echo.leaveChannel(`private-${channelName}`);
    },
  };
}
