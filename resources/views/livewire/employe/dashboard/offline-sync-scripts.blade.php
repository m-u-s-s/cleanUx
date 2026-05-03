@push('scripts')
<script>
    const OFFLINE_QUEUE_KEY = 'cleanux_offline_actions';

    function getOfflineQueue() {
        return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    }

    function saveOfflineQueue(queue) {
        localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    }

    function queueOfflineAction(type, missionId, payload = {}) {
        const queue = getOfflineQueue();

        queue.push({
            type: type,
            mission_id: missionId,
            payload: payload,
            created_at: new Date().toISOString(),
        });

        saveOfflineQueue(queue);
    }

    async function syncOfflineActions() {
        const queue = getOfflineQueue();

        if (!queue.length || !navigator.onLine) {
            return;
        }

        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!token) {
            return;
        }

        try {
            const response = await fetch('/missions/offline-sync', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    actions: queue,
                }),
            });

            const result = await response.json();

            if (result.ok) {
                saveOfflineQueue([]);
                console.log(`${result.synced} action(s) synchronisée(s).`);
            }
        } catch (error) {
            console.warn('Synchronisation offline impossible pour le moment.', error);
        }
    }

    window.addEventListener('online', syncOfflineActions);
    setInterval(syncOfflineActions, 30000);
</script>
@endpush
