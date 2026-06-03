/**
 * Página /insights: tendencia por proyecto (barras apiladas por semana) con
 * Chart.js. Los datos vienen en <script id="insights-data" type="application/json">
 * con la forma { labels: [...], series: [{ name, color, data: [...] }] }.
 */
import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

export function initInsights() {
    const dataEl = document.getElementById('insights-data');
    const ctx    = document.getElementById('insights-trend');
    if (!dataEl || !ctx) return;

    let data;
    try { data = JSON.parse(dataEl.textContent); } catch { return; }

    const isDark  = document.documentElement.classList.contains('dark');
    const gridCol = isDark ? 'rgba(148, 163, 184, 0.12)' : 'rgba(100, 116, 139, 0.12)';
    const textCol = isDark ? 'rgba(203, 213, 225, 0.85)' : 'rgba(71, 85, 105, 0.9)';

    const fmt = (v) => (v >= 60 ? `${Math.floor(v / 60)}h ${v % 60 ? (v % 60) + 'm' : ''}`.trim() : `${v}m`);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: (data.series || []).map((s) => ({
                label: s.name,
                data: s.data,
                backgroundColor: s.color,
                borderWidth: 0,
                borderRadius: 3,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: textCol, boxWidth: 10, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: (c) => `${c.dataset.label}: ${fmt(c.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: { color: textCol } },
                y: {
                    stacked: true,
                    grid: { color: gridCol },
                    ticks: {
                        color: textCol,
                        callback: (v) => (v >= 60 ? `${Math.floor(v / 60)}h` : `${v}m`),
                    },
                },
            },
        },
    });
}
