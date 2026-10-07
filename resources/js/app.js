import './bootstrap';
import './alertas';
import Alpine from 'alpinejs';

/**
 * Planilla de notas: calcula en vivo la nota del lapso de cada estudiante
 * (Σ nota × porcentaje / 100) mientras el profesor escribe.
 */
Alpine.data('planillaNotas', (pesos = {}, notaMaxima = 20) => ({
    pesos,
    notaMaxima,
    version: 0, // se incrementa con cada tecla para recalcular
    notaLapso(fila) {
        this.version;
        const inputs = this.$root.querySelectorAll(`[data-fila="${fila}"]`);
        let total = 0;
        inputs.forEach((input) => {
            const valor = parseFloat(String(input.value).replace(',', '.'));
            if (!Number.isNaN(valor)) {
                total += (valor * (this.pesos[input.dataset.evaluacion] ?? 0)) / 100;
            }
        });
        return total.toFixed(2);
    },
    invalida(input) {
        this.version;
        if (input.value === '') return false;
        const valor = parseFloat(String(input.value).replace(',', '.'));
        return Number.isNaN(valor) || valor < 0 || valor > this.notaMaxima;
    },
}));

/** Pase de lista: marca rápida y contadores. */
Alpine.data('paseDeLista', (estados = {}) => ({
    estados,
    marcar(id, estado) {
        this.estados[id] = estado;
    },
    todos(estado) {
        Object.keys(this.estados).forEach((id) => (this.estados[id] = estado));
    },
    cuenta(estado) {
        return Object.values(this.estados).filter((e) => e === estado).length;
    },
}));

/** Lista de selección con buscador (cátedras, estudiantes). */
Alpine.data('selectorConFiltro', () => ({
    filtro: '',
    coincide(texto) {
        const normalizar = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        return normalizar(texto).includes(normalizar(this.filtro.trim()));
    },
}));

window.Alpine = Alpine;
Alpine.start();
