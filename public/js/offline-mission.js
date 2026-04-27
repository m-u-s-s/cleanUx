window.OfflineMission = {
    KEY: 'offline_mission_queue',

    getQueue() {
        return JSON.parse(localStorage.getItem(this.KEY) || '[]');
    },

    saveQueue(queue) {
        localStorage.setItem(this.KEY, JSON.stringify(queue));
    },

    add(action) {
        const queue = this.getQueue();

        queue.push({
            ...action,
            created_at: new Date().toISOString()
        });

        this.saveQueue(queue);
    },

    async sync() {
        if (!navigator.onLine) return;

        let queue = this.getQueue();

        if (!queue.length) return;

        let newQueue = [];

        for (let action of queue) {
            try {
                await fetch(action.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify(action.payload)
                });
            } catch (e) {
                newQueue.push(action);
            }
        }

        this.saveQueue(newQueue);
    }
};