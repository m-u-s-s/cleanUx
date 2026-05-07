import './bootstrap';
import './echo-listeners';  
import './cleanux-mission-tracking';
import './assistant-streaming';
import './fullcalendar';
import './pwa';
import './push-notifications';
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';


window.FullCalendar = {
    Calendar,
    plugins: [dayGridPlugin, interactionPlugin],
};


