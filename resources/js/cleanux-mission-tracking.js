window.cleanUxMissionTracking = function (missionId, callbacks = {}) {
    if (!window.Echo) {
        console.error('Laravel Echo/Reverb non initialisé.');
        return null;
    }

    const channel = window.Echo.private(`mission.${missionId}`);

    channel.listen('.position.updated', (event) => {
        if (typeof callbacks.onPositionUpdated === 'function') {
            callbacks.onPositionUpdated(event.data);
        }
    });

    channel.listen('.status.updated', (event) => {
        if (typeof callbacks.onStatusUpdated === 'function') {
            callbacks.onStatusUpdated(event);
        }
    });

    return channel;
};