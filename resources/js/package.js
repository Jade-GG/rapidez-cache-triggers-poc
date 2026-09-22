// This file will be loaded automatically after installing the package.
// See: https://github.com/rapidez/rapidez/blob/master/resources/js/app.js

document.addEventListener('vue:loaded', function (event) {
    const vue = event.detail.vue

    // import { defineAsyncComponent } from 'vue'
    // vue.component('example', defineAsyncComponent(() => import('./components/Example.vue')))
    // -- Or if you don't want it to be lazy loaded:
    // import example from './components/Example.vue'
    // vue.component('example', example)
    // You can access the main Vue instance with "window.app" or "vue"

    // window.$on('event-name', () => {
    //
    // });
})
