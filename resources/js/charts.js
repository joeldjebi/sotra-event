/*
| Graphiques EVENT TRANS (Chart.js, sans framework) : composant Alpine « chart » utilisé par <x-ui.chart>.
|
| - La configuration (JSON) est lue dans l'attribut data-chart de l'élément ; quand Livewire re-rend la page
|   (filtre de période), l'attribut change et le graphique est mis à jour sans être recréé.
| - Les couleurs viennent des jetons CSS (--color-brand, --color-ink-tertiary…) : le graphique est redessiné
|   quand le thème change (attribut data-theme de <html>).
| - Marques fines (colonnes ≤ 24 px, extrémité arrondie de 4 px, base carrée), grille en filet discret,
|   info-bulle : la valeur en gras d'abord, le libellé ensuite.
*/
import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

const root = document.documentElement;
const token = (name) => getComputedStyle(root).getPropertyValue(`--color-${name}`).trim();

const numberFormat = new Intl.NumberFormat('fr-FR');
const compactFormat = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });

export const formatValue = (value, format) => {
    if (format === 'fcfa') {
        return `${numberFormat.format(value)} FCFA`;
    }

    return numberFormat.format(value);
};

const tick = (value) => (Math.abs(value) >= 10000 ? compactFormat.format(value) : numberFormat.format(value));

function options(config, horizontal) {
    const ink = token('ink');
    const inkSecondary = token('ink-secondary');
    const muted = token('ink-tertiary');
    const grid = token('separator');
    const font = { family: getComputedStyle(document.body).fontFamily, size: 12 };

    const valueAxis = {
        beginAtZero: true,
        border: { display: false },
        grid: { color: grid, lineWidth: 1, drawTicks: false },
        ticks: { color: muted, font, padding: 8, maxTicksLimit: 5, precision: 0, callback: tick },
    };
    const categoryAxis = {
        border: { display: !horizontal, color: grid },
        grid: { display: false },
        ticks: horizontal
            ? { color: inkSecondary, font: { ...font, size: 13 }, padding: 6 }
            : { color: muted, font, maxRotation: 0, autoSkip: true, autoSkipPadding: 14 },
    };

    return {
        indexAxis: horizontal ? 'y' : 'x',
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 400 },
        // La colonne entière (pas seulement la marque peinte) déclenche l'info-bulle.
        interaction: { mode: 'index', intersect: false, axis: horizontal ? 'y' : 'x' },
        layout: { padding: { top: 4, right: horizontal ? 8 : 0 } },
        scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: token('elevated'),
                borderColor: token('line'),
                borderWidth: 1,
                cornerRadius: 10,
                padding: { x: 12, y: 10 },
                caretSize: 0,
                displayColors: true,
                usePointStyle: true,
                boxWidth: 10,
                boxHeight: 2,
                titleColor: inkSecondary,
                titleFont: { ...font, weight: '500' },
                bodyColor: ink,
                bodyFont: { ...font, size: 13, weight: '600' },
                footerColor: inkSecondary,
                footerFont: { ...font, weight: '400' },
                callbacks: {
                    title: (items) => (config.titles ? config.titles[items[0].dataIndex] : items[0].label),
                    label: (item) => `${formatValue(item.parsed[horizontal ? 'x' : 'y'], config.format)} ${config.unit ?? ''}`.trim(),
                    labelPointStyle: () => ({ pointStyle: 'line', rotation: 0 }),
                    labelColor: (item) => ({ borderColor: item.dataset.backgroundColor, backgroundColor: item.dataset.backgroundColor, borderWidth: 2 }),
                    footer: (items) => (config.details ?? []).map((detail) => `${detail.name} : ${formatValue(detail.values[items[0].dataIndex], detail.format)}`),
                },
            },
        },
    };
}

function datasets(config, horizontal) {
    return config.series.map((series) => {
        const color = token(series.color ?? 'brand');

        return {
            label: series.name,
            data: series.values,
            backgroundColor: color,
            hoverBackgroundColor: color,
            borderWidth: 0,
            borderRadius: { topLeft: horizontal ? 0 : 4, topRight: 4, bottomRight: horizontal ? 4 : 0, bottomLeft: 0 },
            borderSkipped: false,
            maxBarThickness: horizontal ? 20 : 24,
            categoryPercentage: horizontal ? 0.78 : 0.86,
            barPercentage: 1,
            minBarLength: 0,
            pointStyle: 'line',
        };
    });
}

export function chart() {
    return {
        instance: null,
        observers: [],

        config() {
            try {
                return JSON.parse(this.$el.dataset.chart || '{}');
            } catch (error) {
                return { labels: [], series: [] };
            }
        },

        init() {
            this.draw();

            const theme = new MutationObserver(() => this.draw());
            theme.observe(root, { attributes: true, attributeFilter: ['data-theme'] });

            const data = new MutationObserver(() => this.refresh());
            data.observe(this.$el, { attributes: true, attributeFilter: ['data-chart'] });

            this.observers = [theme, data];
        },

        draw() {
            const canvas = this.$el.querySelector('canvas');

            if (!canvas) {
                return;
            }

            this.instance?.destroy();

            const config = this.config();
            const horizontal = Boolean(config.horizontal);

            this.instance = new Chart(canvas, {
                type: 'bar',
                data: { labels: config.labels, datasets: datasets(config, horizontal) },
                options: options(config, horizontal),
            });
        },

        refresh() {
            if (!this.instance) {
                return this.draw();
            }

            const config = this.config();
            const horizontal = Boolean(config.horizontal);

            this.instance.data.labels = config.labels;
            this.instance.data.datasets = datasets(config, horizontal);
            this.instance.options = options(config, horizontal);
            this.instance.update();
        },

        destroy() {
            this.observers.forEach((observer) => observer.disconnect());
            this.instance?.destroy();
            this.instance = null;
        },
    };
}

const register = () => window.Alpine.data('chart', chart);

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
