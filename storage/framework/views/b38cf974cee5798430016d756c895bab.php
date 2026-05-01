<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
    let chartInstance = null;
    let chartMensuelInstance = null;
    let adminCalendarInstance = null;
    let livewireListenersRegistered = false;

    const initialStats = <?php echo \Illuminate\Support\Js::from($statistiquesData ?? [])->toHtml() ?>;
    const initialMonthlyStats = <?php echo \Illuminate\Support\Js::from($statsMensuelles ?? [])->toHtml() ?>;
    const initialEvents = <?php echo \Illuminate\Support\Js::from($rdvs ?? [])->toHtml() ?>;

    function initAdminCharts() {
        const chartStatsEl = document.querySelector('#chartStats');
        const chartMensuelEl = document.querySelector('#chartMensuel');

        if (!chartStatsEl || !chartMensuelEl) return;

        if (chartInstance) chartInstance.destroy();
        if (chartMensuelInstance) chartMensuelInstance.destroy();

        chartInstance = new ApexCharts(chartStatsEl, {
            chart: {
                type: 'donut',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            series: [
                Number(initialStats.confirme || 0),
                Number(initialStats.attente || 0),
                Number(initialStats.refuse || 0),
                Number(initialStats.en_route || 0),
                Number(initialStats.sur_place || 0),
                Number(initialStats.termine || 0),
            ],
            labels: ['Confirmés', 'En attente', 'Refusés', 'En route', 'Sur place', 'Terminés'],
            legend: {
                position: 'bottom'
            },
            dataLabels: {
                enabled: true
            },
            stroke: {
                width: 2
            },
            colors: ['#16a34a', '#eab308', '#dc2626', '#2563eb', '#4f46e5', '#047857'],
            noData: {
                text: 'Aucune donnée'
            }
        });

        chartMensuelInstance = new ApexCharts(chartMensuelEl, {
            chart: {
                type: 'area',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            series: [{
                name: 'RDV',
                data: Array.isArray(initialMonthlyStats) && initialMonthlyStats.length ?
                    initialMonthlyStats.map(value => Number(value || 0)) :
                    Array(12).fill(0)
            }],
            xaxis: {
                categories: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                opacity: 0.25
            },
            dataLabels: {
                enabled: false
            },
            colors: ['#2563eb'],
            noData: {
                text: 'Aucune donnée'
            }
        });

        chartInstance.render();
        chartMensuelInstance.render();
    }

    function initAdminCalendar() {
        const calendarEl = document.getElementById('fullcalendar-admin');

        if (!calendarEl || typeof FullCalendar === 'undefined') return;

        if (adminCalendarInstance) {
            adminCalendarInstance.destroy();
        }

        adminCalendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'fr',
            height: 'auto',
            firstDay: 1,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek'
            },
            buttonText: {
                today: 'Aujourd’hui',
                month: 'Mois',
                week: 'Semaine'
            },
            events: initialEvents,
            eventClick(info) {
                const title = info.event.title || 'Rendez-vous';
                const zone = info.event.extendedProps?.zone || 'Zone non précisée';
                const start = info.event.start ?
                    info.event.start.toLocaleString('fr-FR') :
                    'Date non précisée';

                window.dispatchEvent(new CustomEvent('admin-calendar-event-clicked', {
                    detail: {
                        title,
                        zone,
                        start
                    }
                }));
            }
        });

        adminCalendarInstance.render();
    }

    function registerAdminDashboardListeners() {
        if (livewireListenersRegistered || typeof Livewire === 'undefined') return;

        livewireListenersRegistered = true;

        Livewire.on('updateChartData', (event) => {
            const data = event?.data ?? event;

            if (!chartInstance) return;

            chartInstance.updateSeries([
                Number(data.confirme || 0),
                Number(data.attente || 0),
                Number(data.refuse || 0),
                Number(data.en_route || 0),
                Number(data.sur_place || 0),
                Number(data.termine || 0),
            ]);
        });

        Livewire.on('updateMonthlyChart', (event) => {
            const data = event?.data ?? event;

            if (!chartMensuelInstance) return;

            chartMensuelInstance.updateSeries([{
                name: 'RDV',
                data: Array.isArray(data) ? data.map(value => Number(value || 0)) : []
            }]);
        });
    }

    function bootAdminDashboard() {
        initAdminCharts();
        initAdminCalendar();
        registerAdminDashboardListeners();
    }

    document.addEventListener('DOMContentLoaded', bootAdminDashboard);
    document.addEventListener('livewire:init', bootAdminDashboard);
    document.addEventListener('livewire:navigated', bootAdminDashboard);
</script>
<?php $__env->stopPush(); ?><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/scripts.blade.php ENDPATH**/ ?>