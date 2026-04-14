import Chart from 'chart.js/auto';

import './echo';

const activeCharts = new Map();

function destroyMissingCharts() {
    const availableKeys = new Set(
        Array.from(document.querySelectorAll('canvas[data-chart-key]')).map((canvas) => canvas.dataset.chartKey),
    );

    for (const [key, chart] of activeCharts.entries()) {
        if (!availableKeys.has(key)) {
            chart.destroy();
            activeCharts.delete(key);
        }
    }
}

function initializeCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas, index) => {
        if (!canvas.dataset.chartKey) {
            canvas.dataset.chartKey = `chart-${window.location.pathname}-${index}`;
        }

        const context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        if (activeCharts.has(canvas.dataset.chartKey)) {
            activeCharts.get(canvas.dataset.chartKey)?.destroy();
            activeCharts.delete(canvas.dataset.chartKey);
        }

        const config = JSON.parse(canvas.dataset.chart);
        activeCharts.set(canvas.dataset.chartKey, new Chart(context, config));
    });

    destroyMissingCharts();
}

document.addEventListener('DOMContentLoaded', initializeCharts);
document.addEventListener('livewire:navigated', () => requestAnimationFrame(initializeCharts));
