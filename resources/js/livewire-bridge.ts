// Forwards Vue island events into Livewire's event bus.

interface LivewireGlobal {
  dispatch: (event: string, detail: Record<string, unknown>) => void;
}

declare global {
  interface Window {
    Livewire?: LivewireGlobal;
  }
}

const forward = (sourceEvent: string, livewireEvent: string) => {
  window.addEventListener(sourceEvent, (e: Event) => {
    const detail = (e as CustomEvent).detail ?? {};
    if (window.Livewire) {
      window.Livewire.dispatch(livewireEvent, detail);
    } else {
      console.warn(`[cleanux] Livewire not yet ready for ${livewireEvent}`, detail);
    }
  });
};

export function installLivewireBridge() {
  forward('cleanux:client-action', 'client-action');
  forward('cleanux:mission-scan', 'mission-scan-requested');
  forward('cleanux:mission-call', 'mission-call-requested');
}
