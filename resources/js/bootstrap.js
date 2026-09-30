import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.withCredentials = true;

// CSRF: axios sends the XSRF-TOKEN cookie back as the X-XSRF-TOKEN header. Laravel
// refreshes that cookie on every response, so the token stays valid after logout /
// login (session regeneration) without a page reload. A fixed X-CSRF-TOKEN header from
// the <meta> tag would take precedence and go stale, causing 419 errors.
window.axios.defaults.withXSRFToken = true;
