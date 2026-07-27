import { createApp } from './app.js';
createApp().then(app => app.start()).catch(error => { console.error(JSON.stringify({ level: 'error', event: 'agent.start_failed', message: error.message })); process.exit(1); });
