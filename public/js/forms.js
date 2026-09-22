'use strict';
const reservationForm = document.querySelector('[data-reservation-form]');
if (reservationForm) {
    const arrival = reservationForm.querySelector('#check_in');
    const departure = reservationForm.querySelector('#check_out');
    const room = reservationForm.querySelector('#room_id');
    const estimate = document.querySelector('#estimate');
    function updateEstimate() {
        const start = Date.parse(arrival.value + 'T00:00:00Z');
        const end = Date.parse(departure.value + 'T00:00:00Z');
        const nights = Math.round((end - start) / 86400000);
        if (Number.isFinite(start)) {
            departure.min = new Date(start + 86400000).toISOString().slice(0, 10);
        } else {
            departure.removeAttribute('min');
        }
        departure.setCustomValidity(departure.value && arrival.value && !(nights > 0) ? 'La salida debe ser posterior a la entrada.' : '');
        const rate = room.selectedOptions[0]?.dataset.rate;
        estimate.textContent = nights > 0 && rate !== undefined
            ? nights + ' noches · Importe orientativo: ' + new Intl.NumberFormat('es-AR', {style: 'currency', currency: 'ARS'}).format(nights * Number(rate))
            : 'Elegí las fechas y la habitación para calcular un importe orientativo.';
    }
    reservationForm.addEventListener('input', updateEstimate);
    updateEstimate();
}
