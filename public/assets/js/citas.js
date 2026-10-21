// Los cambios se envían al servidor: no se calculan opciones ni turnos en JS.
document.querySelectorAll('[data-booking-options]').forEach((form) => {
    form.querySelectorAll('[data-booking-refresh]').forEach((field) => {
        field.addEventListener('change', () => {
            if (field.name === 'especialidad_id') form.querySelector('[name="medico_id"]').value = '';
            form.querySelectorAll('[name="hora_inicio"]').forEach((radio) => { radio.checked = false; });
            const loading = form.querySelector('[data-booking-loading]');
            if (loading) loading.hidden = false;
            form.requestSubmit(form.querySelector('[data-booking-update]'));
        });
    });
});
// Complemento de interfaz. PostgreSQL sigue siendo la defensa contra duplicados.
document.querySelectorAll('[data-booking-confirm]').forEach((form) => {
    let sending = false;
    form.addEventListener('submit', (event) => {
        if (sending) { event.preventDefault(); return; }
        sending = true;
        form.querySelector('[data-booking-submit]').disabled = true;
        form.querySelector('[data-booking-loading]').hidden = false;
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        sending = false;
        form.querySelector('[data-booking-submit]').disabled = false;
        form.querySelector('[data-booking-loading]').hidden = true;
        form.removeAttribute('aria-busy');
    });
});
