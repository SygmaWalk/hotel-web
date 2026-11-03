'use strict';
const rescheduleDates = document.querySelector('[data-reschedule-dates]');
const reschedulePreview = document.querySelector('[data-reschedule-preview]');
if (rescheduleDates && reschedulePreview) {
    const verifyDates = () => {
        const changed = ['check_in', 'check_out'].some(key => rescheduleDates.elements[key].value !== reschedulePreview.elements[key].value);
        reschedulePreview.querySelector('button').disabled = changed;
        reschedulePreview.querySelector('[data-reschedule-dirty]').hidden = !changed;
        return changed;
    };
    rescheduleDates.addEventListener('input', verifyDates);
    rescheduleDates.addEventListener('change', verifyDates);
    window.addEventListener('pageshow', verifyDates);
    reschedulePreview.addEventListener('submit', event => { if (verifyDates()) event.preventDefault(); });
    verifyDates();
}
const reservationForm = document.querySelector('[data-reservation-form]');
if (reservationForm) {
    const arrival = reservationForm.elements.check_in;
    const departure = reservationForm.elements.check_out;
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
            ? nights + (nights === 1 ? ' noche' : ' noches') + ' · Importe orientativo: ' + new Intl.NumberFormat('es-AR', {style: 'currency', currency: 'ARS'}).format(nights * Number(rate))
            : 'Elegí las fechas y la habitación para calcular un importe orientativo.';
    }
    reservationForm.addEventListener('input', updateEstimate);
    updateEstimate();
    const datesForm = document.querySelector('[data-booking-dates]');
    if (datesForm) {
        const save = reservationForm.querySelector('button[type="submit"]');
        const notice = reservationForm.querySelector('[data-dates-changed]');
        const unavailable = save.disabled;
        const checkDates = () => {
            const changed = datesForm.elements.check_in.value !== arrival.value || datesForm.elements.check_out.value !== departure.value;
            save.disabled = unavailable || changed;
            notice.hidden = !changed;
            return changed;
        };
        datesForm.addEventListener('input', checkDates);
        datesForm.addEventListener('change', checkDates);
        window.addEventListener('pageshow', checkDates);
        reservationForm.addEventListener('submit', event => { if (checkDates()) event.preventDefault(); });
        checkDates();
    }
}
