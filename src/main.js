import {createApp} from 'vue'
import {createPinia} from 'pinia'
import App from './Installer.vue'
import {apiPlugin} from './plugins/api';


const app = createApp(App)

app.use(createPinia())
app.use(apiPlugin)

console.log(import.meta.env.VITE_APP_INSTALL_URL)
app.mount('#app')
