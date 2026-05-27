/**
 * Página /reports: gráfica "Por día" con Chart.js. Los datos vienen
 * embebidos en <script id="reports-data" type="application/json">.
 */
import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

export function initReports() {
    const dataEl = document.getElementById('reports-data');
    const ctx    = document.getElementById('chart-by-day');
    if (!dataEl || !ctx) return;

    let data;
    try { data = JSON.parse(dataEl.textContent); } catch { return; }

    const isDark   = document.documentElement.classList.contains('dark');
    const gridCol  = isDark ? 'rgba(148, 163, 184, 0.12)' : 'rgba(100, 116, 139, 0.12)';
    const textCol  = isDark ? 'rgba(203, 213, 225, 0.85)' : 'rgba(71, 85, 105, 0.9)';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.byDay.map((d) => d.label),
            datasets: [{
                data: data.byDay.map((d) => d.minutes),
                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                hoverBackgroundColor: 'rgba(16, 185, 129, 1)',
                borderRadius: 4,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const m = ctx.parsed.y;
                            return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`;
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: textCol,
                        font: { size: 10 },
                        callback: (v) => v >= 60 ? `${Math.floor(v / 60)}h` : `${v}m`,
                    },
                    grid: { color: gridCol },
                    border: { display: false },
                },
                x: {
                    ticks: { color: textCol, font: { size: 10 } },
                    grid: { display: false },
                    border: { display: false },
                },
            },
        },
    });
}
