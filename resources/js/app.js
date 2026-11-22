import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { connectEcho } from './echo';
import { registerMotion } from './motion';
import { registerToasts } from './toasts';

window.Alpine = Alpine;
window.connectEcho = connectEcho;

Alpine.plugin(focus);
registerMotion(Alpine);
registerToasts(Alpine);

Alpine.start();
