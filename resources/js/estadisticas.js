import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, DoughnutController, ArcElement, Tooltip, Legend);

Chart.defaults.font.family = "'Inter Variable', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#475569';
Chart.defaults.plugins.legend.labels.boxWidth = 12;

const COLORES = {
    principal: '#0f172a',
    presente: '#059669',
    ausente: '#e11d48',
    retraso: '#d97706',
    justificada: '#0284c7',
    serie: ['#0f172a', '#334155', '#475569', '#64748b', '#94a3b8', '#cbd5e1'],
    sexo: ['#e11d48', '#0284c7', '#94a3b8'],
};

const ejes = {
    x: { grid: { display: false } },
    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
};

function barras(id, datos, { horizontal = false, color = COLORES.principal } = {}) {
    const lienzo = document.getElementById(id);
    if (!lienzo) return;
    new Chart(lienzo, {
        type: 'bar',
        data: {
            labels: datos.labels,
            datasets: [{ data: datos.data, backgroundColor: color, borderRadius: 6, maxBarThickness: 36 }],
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: horizontal ? { x: ejes.y, y: ejes.x } : ejes,
        },
    });
}

function apiladas(id, datos) {
    const lienzo = document.getElementById(id);
    if (!lienzo) return;
    const series = [
        ['Presente', 'P', COLORES.presente],
        ['Retraso', 'R', COLORES.retraso],
        ['Justificada', 'J', COLORES.justificada],
        ['Ausente', 'A', COLORES.ausente],
    ];
    new Chart(lienzo, {
        type: 'bar',
        data: {
            labels: datos.labels,
            datasets: series.map(([label, clave, color]) => ({
                label,
                data: datos[clave],
                backgroundColor: color,
                borderRadius: 4,
                maxBarThickness: 40,
            })),
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { x: { ...ejes.x, stacked: true }, y: { ...ejes.y, stacked: true } },
        },
    });
}

function dona(id, datos, colores = COLORES.serie) {
    const lienzo = document.getElementById(id);
    if (!lienzo) return;
    new Chart(lienzo, {
        type: 'doughnut',
        data: {
            labels: datos.labels,
            datasets: [{ data: datos.data, backgroundColor: colores, borderColor: '#fff', borderWidth: 2 }],
        },
        options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } },
    });
}

const fuente = document.getElementById('datos-estadisticas');
if (fuente) {
    const d = JSON.parse(fuente.textContent);
    barras('grafico-asignaturas', d.asignaturas, { horizontal: true });
    apiladas('grafico-meses', d.meses);
    barras('grafico-docentes', d.docentes, { horizontal: true, color: '#334155' });
    barras('grafico-niveles', d.niveles, { color: '#475569' });
    dona('grafico-sexo', d.sexo, COLORES.sexo);
    barras('grafico-edades', d.edades, { color: '#64748b' });
    dona('grafico-resultados', d.resultados, [COLORES.presente, COLORES.ausente, COLORES.retraso, '#94a3b8', COLORES.justificada]);
}
