@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
    let chartInstance = null;
    let chartMensuelInstance = null;
    let adminCalendarInstance = null;
    let livewireListenersRegistered = false;

    function initAdminCharts() {
        const chartStatsEl = document.querySelector('#chartStats');
        const chartMensuelEl = document.querySelector('#chartMensuel');

        if (!chartStatsEl || !chartMensuelEl) return;

        if (chartInstance) chartInstance.destroy();
        if (chartMensuelInstance) chartMensuelInstance.destroy();

        chartInstance = new ApexCharts(chartStatsEl, {
            chart: {
                type: 'donut',
                height: 300
            },
            series: [0, 0, 0],
            labels: ['Confirmés', 'En attente', 'Refusés'],
            colors: ['#16a34a', '#eab308', '#dc2626']
        });

        chartMensuelInstance = new ApexCharts(chartMensuelEl, {
            chart: {
                type: 'line',
                height: 300
            },
            series: [{
                name: 'RDV',
                data: Array(12).fill(0)
            }],
            xaxis: {
                categories: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']
            },
            colors: ['#3b82f6']
        });

        chartInstance.render();
        chartMensuelInstance.render();
    }

    function initAdminCalendar() {
        const calendarEl = document.getElementById('fullcalendar-admin');
        if (!calendarEl) return;

        if (adminCalendarInstance) {
            adminCalendarInstance.destroy();
        }

        adminCalendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'fr',
            events: @js($rdvs),
            eventClick(info) {
                alert('RDV avec ' + info.event.title);
            }
        });

        adminCalendarInstance.render();
    }

    function registerAdminDashboardListeners() {
        if (livewireListenersRegistered) return;
        livewireListenersRegistered = true;

        Livewire.on('updateChartData', (event) => {
            const data = event?.data ?? event;
            if (!chartInstance) return;

            chartInstance.updateSeries([
                data.confirme || 0,
                data.attente || 0,
                data.refuse || 0
            ]);
        });

        Livewire.on('updateMonthlyChart', (event) => {
            const data = event?.data ?? event;
            if (!chartMensuelInstance) return;

            chartMensuelInstance.updateSeries([{
                name: 'RDV',
                data: data
            }]);
        });
    }

    function bootAdminDashboard() {
        initAdminCharts();
        initAdminCalendar();
        registerAdminDashboardListeners();
    }

    document.addEventListener('livewire:load', bootAdminDashboard);
    document.addEventListener('livewire:navigated', bootAdminDashboard);
</script>
@endpush
