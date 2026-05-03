    <script>
        async function toggleChecklistItem(itemId, checked) {
            try {
                await fetch(`/mission-checklist-items/${itemId}/toggle`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: checked ? 'done' : 'pending',
                    }),
                });
            } catch (error) {
                console.error('Erreur checklist mission:', error);
            }
        }

        let cleanUxTrackingInterval = null;

        function startSendingPosition(sessionId) {
            if (cleanUxTrackingInterval) {
                clearInterval(cleanUxTrackingInterval);
            }

            cleanUxTrackingInterval = setInterval(() => {
                if (!navigator.geolocation) {
                    return;
                }

                navigator.geolocation.getCurrentPosition(async function(position) {
                    try {
                        await fetch(`/mission-tracking-sessions/${sessionId}/tracking/push`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                lat: position.coords.latitude,
                                lng: position.coords.longitude,
                                accuracy_meters: position.coords.accuracy,
                                speed_kmh: position.coords.speed ? position.coords.speed * 3.6 : null,
                                heading: position.coords.heading,
                                source: 'browser',
                                app_state: document.hidden ? 'background' : 'foreground',
                            }),
                        });
                    } catch (error) {
                        console.error('Erreur tracking mission:', error);
                    }
                });
            }, 15000);
        }
    </script>
