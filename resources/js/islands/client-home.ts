import { createApp } from 'vue';
import ClientHomeIsland from './ClientHomeIsland.vue';

const mountPoint = document.getElementById('client-home-island');
if (mountPoint) {
  const propsAttr = mountPoint.getAttribute('data-props');
  const props = propsAttr ? JSON.parse(propsAttr) : {};
  createApp(ClientHomeIsland, props).mount(mountPoint);
}
