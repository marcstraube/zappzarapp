/**
 * Main JavaScript Entry Point
 *
 * This file runs in the browser and is bundled by Vite.
 * Output: public/build/assets/app-[hash].js
 */

// Import main CSS
import '../css/app.css';

// Example: Fetch API data
async function fetchHealth() {
    try {
        const response = await fetch('/api/health');
        const data = await response.json();
        console.log('Health check:', data);

        // Update DOM
        const healthEl = document.getElementById('health-status');
        if (healthEl) {
            healthEl.textContent = JSON.stringify(data, null, 2);
            healthEl.className = 'health-ok';
        }
    } catch (error) {
        console.error('Health check failed:', error);

        const healthEl = document.getElementById('health-status');
        if (healthEl) {
            healthEl.textContent = 'Error: ' + error.message;
            healthEl.className = 'health-error';
        }
    }
}

// DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('App loaded!');
    console.log('Environment:', import.meta.env.MODE);
    console.log('Vite HMR:', import.meta.hot ? 'Enabled' : 'Disabled');

    // Fetch health status
    fetchHealth();

    // Example: Button click
    const testBtn = document.getElementById('test-btn');
    if (testBtn) {
        testBtn.addEventListener('click', () => {
            alert('Button clicked! HMR is working if this updates without page reload.');
        });
    }
});

// Hot Module Replacement (HMR)
if (import.meta.hot) {
    import.meta.hot.accept(() => {
        console.log('HMR update received');
    });
}
