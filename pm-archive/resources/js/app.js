import { createApp } from 'vue';
import router from './router';
import App from './App.vue';
import { vClickOutside } from './directives/clickOutside';
import './bootstrap';

const app = createApp(App);

app.directive('click-outside', vClickOutside);
app.use(router);
app.mount('#app');
