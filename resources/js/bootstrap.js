import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Must match the cookie name issued by App\Http\Middleware\VerifyCsrfToken and the
// xsrfCookieName configured for Inertia's client in app.js — axios defaults to the
// generic "XSRF-TOKEN" name, which this app no longer sets.
window.axios.defaults.xsrfCookieName = 'bxtorneum_xsrf_token';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
