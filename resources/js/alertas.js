import Swal from 'sweetalert2';

/**
 * Alertas de SIGAC con SweetAlert2:
 *  - Mensajes del servidor (éxito, error, aviso) leídos de #mensajes-flash.
 *  - Confirmaciones: cualquier <form data-confirmar="..."> o botón de envío con
 *    data-confirmar pide confirmación antes de enviarse.
 *    data-confirmar-tipo="peligro" muestra el botón en rojo.
 */
const base = Swal.mixin({
    buttonsStyling: false,
    customClass: {
        popup: 'rounded-2xl',
        title: 'text-lg font-bold text-slate-900',
        htmlContainer: 'text-sm text-slate-600',
        actions: 'gap-2',
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-secondary',
    },
    confirmButtonText: 'Aceptar',
    cancelButtonText: 'Cancelar',
});

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

export function exito(texto) {
    return toast.fire({ icon: 'success', title: texto });
}

export function aviso(texto) {
    return base.fire({ icon: 'info', title: 'Aviso', text: texto });
}

export function error(texto) {
    return base.fire({ icon: 'error', title: 'No se pudo completar', text: texto, confirmButtonText: 'Entendido' });
}

export async function confirmar(texto, { peligro = false } = {}) {
    const resultado = await base.fire({
        icon: peligro ? 'warning' : 'question',
        title: '¿Confirmar?',
        text: texto,
        showCancelButton: true,
        reverseButtons: true,
        focusCancel: peligro,
        confirmButtonText: peligro ? 'Sí, continuar' : 'Confirmar',
        customClass: {
            popup: 'rounded-2xl',
            title: 'text-lg font-bold text-slate-900',
            htmlContainer: 'text-sm text-slate-600',
            actions: 'gap-2',
            confirmButton: peligro ? 'btn btn-danger' : 'btn btn-primary',
            cancelButton: 'btn btn-secondary',
        },
    });

    return resultado.isConfirmed;
}

// Confirmación antes de enviar formularios.
document.addEventListener('submit', async (evento) => {
    const form = evento.target;
    const origen = evento.submitter?.dataset.confirmar ? evento.submitter : form;
    const mensaje = origen.dataset?.confirmar;

    if (!mensaje || form.dataset.confirmado === '1') {
        delete form.dataset.confirmado;
        return;
    }

    evento.preventDefault();

    if (await confirmar(mensaje, { peligro: origen.dataset.confirmarTipo === 'peligro' })) {
        form.dataset.confirmado = '1';
        form.requestSubmit(evento.submitter ?? undefined);
    }
});

// Mensajes enviados por el servidor tras redirigir.
document.addEventListener('DOMContentLoaded', () => {
    const fuente = document.getElementById('mensajes-flash');
    if (!fuente) return;

    const { exito: ok, error: fallo, aviso: info } = JSON.parse(fuente.textContent);

    if (fallo) error(fallo);
    else if (info) aviso(info);

    if (ok) exito(ok);
});

window.Swal = Swal;
window.SIGAC = { exito, aviso, error, confirmar };
