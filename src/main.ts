import {createApp} from 'vue'
import {setLocaleData} from '@wordpress/i18n'
import './style.css'
import App from './App.vue'
import {router} from "./router.ts";

if (window.__fastcloudwpI18n) {
    setLocaleData(window.__fastcloudwpI18n, 'fastcloud-offload-media')
}

createApp(App)
    .use(router)
    .mount('#fastcloudwp-app')
