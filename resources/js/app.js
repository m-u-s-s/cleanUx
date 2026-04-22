import './bootstrap';
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

window.FullCalendar = {
    Calendar,
    plugins: [dayGridPlugin, interactionPlugin],
};


