const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#main-navigation');

document.querySelectorAll('[data-horario-form]').forEach((form) => {
    const fields = [...form.querySelectorAll('input[type="time"], select:not(:disabled)')];
    const validate = () => {
        fields.forEach((field) => field.setCustomValidity(''));
        const inicio = form.querySelector('[name="hora_inicio"]');
        const fin = form.querySelector('[name="hora_fin"]');
        if (inicio.value && fin.value && fin.value <= inicio.value) fin.setCustomValidity('La hora final debe ser posterior a la inicial, dentro del mismo día.');
        let valid = true;
        fields.forEach((field) => {
            const error = form.querySelector(`#error-${field.id}`);
            if (error) error.textContent = field.validationMessage;
            field.setAttribute('aria-invalid', String(!field.validity.valid));
            valid = field.validity.valid && valid;
        });
        return valid;
    };
    form.noValidate = true;
    fields.forEach((field) => field.addEventListener('input', validate));
    form.addEventListener('submit', (event) => {
        if (!validate()) { event.preventDefault(); fields.find((field) => !field.validity.valid)?.focus(); }
    });
});

if (menuButton && navigation) {
    menuButton.addEventListener('click', () => {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!isOpen));
        navigation.classList.toggle('is-open', !isOpen);
    });
}

// Complementa las validaciones del servidor; nunca decide permisos ni unicidad.
document.querySelectorAll('[data-patient-form]').forEach((form) => {
    const fields = [...form.querySelectorAll('input:not([type="hidden"]), select')];
    const validate = (field) => {
        field.setCustomValidity('');
        if (field.name === 'documento_identidad') {
            field.value = field.value.replace(/\s+/gu, '').toUpperCase();
        }
        if (field.required && !field.value.trim()) {
            field.setCustomValidity('Este campo es obligatorio.');
        }
        const error = document.getElementById(`error-${field.name}`);
        if (error) error.textContent = field.validationMessage;
        field.setAttribute('aria-invalid', String(!field.validity.valid));
        return field.validity.valid;
    };
    // Con JS se presentan los errores junto al campo; sin JS opera HTML5.
    form.noValidate = true;
    fields.forEach((field) => field.addEventListener('input', () => validate(field)));
    form.addEventListener('submit', (event) => {
        const valid = fields.map(validate).every(Boolean);
        if (!valid) {
            event.preventDefault();
            fields.find((field) => !field.validity.valid)?.focus();
        }
    });
});

// Validación auxiliar del catálogo; las reglas definitivas se aplican en PHP.
document.querySelectorAll('[data-catalog-form]').forEach((form) => {
    const fields = [...form.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"])')];
    const checks = [...form.querySelectorAll('input[name="especialidades[]"]')];
    const validate = (field) => {
        field.setCustomValidity('');
        if (field.name === 'numero_colegiatura') field.value = field.value.replace(/\s+/gu, '').toUpperCase();
        if (field.required && !field.value.trim()) field.setCustomValidity('Este campo es obligatorio.');
        if (field.value && field.name === 'telefono' && (!/^\+?[0-9 ()-]+$/.test(field.value) || field.value.replace(/\D/g, '').length < 7 || field.value.replace(/\D/g, '').length > 15)) field.setCustomValidity('Usa entre 7 y 15 dígitos y un formato de teléfono válido.');
        if (field.value && ['nombres', 'apellidos'].includes(field.name) && !/\p{L}/u.test(field.value)) field.setCustomValidity('Incluye al menos una letra.');
        if (field.value && field.name === 'numero_colegiatura' && !/^[\p{L}\p{N}.\/-]+$/u.test(field.value)) field.setCustomValidity('Usa letras, números, punto, guion o barra.');
        const error = document.getElementById(`error-${field.name}`);
        if (error) error.textContent = field.validationMessage;
        field.setAttribute('aria-invalid', String(!field.validity.valid));
        return field.validity.valid;
    };
    fields.forEach((field) => field.addEventListener('input', () => validate(field)));
    form.noValidate = true;
    form.addEventListener('submit', (event) => {
        let valid = fields.map(validate).every(Boolean);
        const error = form.querySelector('#error-especialidades');
        if (error) {
            const hasActive = checks.some((field) => field.checked && field.dataset.inactive !== 'true');
            error.textContent = form.dataset.active === 'true' && !hasActive ? 'Selecciona al menos una especialidad activa.' : '';
            valid = valid && !error.textContent;
        }
        if (!valid) { event.preventDefault(); (fields.find((field) => !field.validity.valid) ?? checks[0])?.focus(); }
    });
});
