import { mount } from 'svelte';
import '../../lib/wpeasy-admin-framework/wpea-wp-resets.css';
import '../../lib/wpeasy-admin-framework/wpea-framework.css';
import App from './App.svelte';

const target = document.getElementById('wpef-settings-app');

if (target) {
  mount(App, { target });
}
