import { createApp } from 'vue';
import MissionLiveIsland from './MissionLiveIsland.vue';

const mountPoint = document.getElementById('mission-live-island');
if (mountPoint) {
  const propsAttr = mountPoint.getAttribute('data-props');
  const props = propsAttr ? JSON.parse(propsAttr) : {};
  createApp(MissionLiveIsland, props).mount(mountPoint);
}
