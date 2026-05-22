import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useReverbChannel } from './useReverbChannel';

describe('useReverbChannel', () => {
  let mockEcho: { private: ReturnType<typeof vi.fn>; leaveChannel: ReturnType<typeof vi.fn> };
  let mockChannel: { listen: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    mockChannel = { listen: vi.fn().mockReturnThis() };
    mockEcho = {
      private: vi.fn().mockReturnValue(mockChannel),
      leaveChannel: vi.fn(),
    };
    // @ts-expect-error inject mock
    window.Echo = mockEcho;
  });

  it('subscribes to private channel and listens to event', () => {
    const handler = vi.fn();
    useReverbChannel('mission.42', { 'MissionLivePosition': handler });

    expect(mockEcho.private).toHaveBeenCalledWith('mission.42');
    expect(mockChannel.listen).toHaveBeenCalledWith('.MissionLivePosition', handler);
  });

  it('returns unsubscribe function', () => {
    const { unsubscribe } = useReverbChannel('mission.42', {});
    unsubscribe();
    expect(mockEcho.leaveChannel).toHaveBeenCalledWith('private-mission.42');
  });
});
