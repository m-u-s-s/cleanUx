/**
 * Dedicated ApexCharts entry — loaded only on pages that render charts, via
 * @push('scripts') @vite(['resources/js/apexcharts.js']). Keeps ApexCharts out
 * of the global app.js bundle (it was ~heavy and shipped on every page).
 *
 * Consumers rely on the `window.ApexCharts` global and initialise on deferred
 * events (livewire:init / livewire:navigated / Livewire.hook / DOMContentLoaded),
 * which fire after this deferred module executes — so the global is always ready.
 */
import ApexCharts from 'apexcharts';

window.ApexCharts = ApexCharts;
