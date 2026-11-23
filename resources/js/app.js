import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { registerAjax } from './ajax';
import { registerCommunity } from './community';
import { connectEcho } from './echo';
import { registerMotion } from './motion';
import { registerToasts } from './toasts';

window.Alpine = Alpine;
window.connectEcho = connectEcho;

Alpine.plugin(focus);
registerMotion(Alpine);
registerToasts(Alpine);
registerAjax(Alpine);
registerCommunity(Alpine);

Alpine.start();
