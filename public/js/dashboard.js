'use strict';
const dashboardForm = document.querySelector('[data-dashboard-filter]');
const dashboardContent = document.querySelector('#dashboard-content');
if (dashboardForm && dashboardContent) {
    const controls = document.querySelector('[data-live-controls]');
    const status = document.querySelector('[data-refresh-status]');
    const retry = document.querySelector('[data-refresh-retry]');
    const autoRefresh = document.querySelector('[data-auto-refresh]');
    const dateInput = dashboardForm.querySelector('[name="date"]');
    let controller;
    controls.hidden = false;
    async function refresh(manual = false) {
        if (!manual && (document.hidden || dateInput.value !== dashboardContent.dataset.date || dashboardContent.contains(document.activeElement))) return;
        if (!dashboardForm.reportValidity()) return;
        controller?.abort();
        const request = new AbortController();
        controller = request;
        const timeout = setTimeout(() => request.abort(), 12000);
        const target = new URL(dashboardForm.action || location.href);
        target.search = new URLSearchParams({date: dateInput.value}).toString();
        dashboardContent.setAttribute('aria-busy', 'true');
        status.textContent = 'Actualizando datos…';
        retry.hidden = true;
        try {
            const response = await fetch(target, {signal: request.signal, cache: 'no-store', credentials: 'same-origin'});
            if (response.redirected) throw new Error('La sesión venció. Volvé a cargar para ingresar.');
            if (!response.ok) throw new Error(response.status === 422 ? 'Revisá la fecha de consulta.' : 'No se pudo actualizar. Se conservan los últimos datos.');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.querySelector('#dashboard-content');
            if (!next) throw new Error('No se pudo leer el panel. Volvé a cargar.');
            if (controller !== request) return;
            dashboardContent.replaceChildren(...next.childNodes);
            dashboardContent.dataset.date = next.dataset.date;
            history.replaceState(null, '', target);
            retry.href = target.toString();
            status.textContent = 'Actualizado a las ' + new Intl.DateTimeFormat('es-AR', {hour:'2-digit', minute:'2-digit', second:'2-digit'}).format(new Date()) + '.';
        } catch (error) {
            if (controller !== request) return;
            status.textContent = error.name === 'AbortError' ? 'La actualización tardó demasiado. Se conservan los últimos datos.' : error.message;
            retry.hidden = false;
        } finally {
            clearTimeout(timeout);
            if (controller === request) dashboardContent.removeAttribute('aria-busy');
        }
    }
    dashboardForm.addEventListener('submit', (event) => { event.preventDefault(); refresh(true); });
    setInterval(() => { if (autoRefresh.checked) refresh(); }, 60000);
}
