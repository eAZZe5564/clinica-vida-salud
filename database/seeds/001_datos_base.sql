-- Datos de catálogo no sensibles. Reejecutable sin sobrescribir cambios existentes.
INSERT INTO roles (nombre, descripcion) VALUES
    ('administrador', 'Administra la configuración y los usuarios del sistema.'),
    ('recepcionista', 'Gestiona registros y citas desde recepción.'),
    ('medico', 'Accede a las funciones asignadas al profesional.'),
    ('paciente', 'Accede a las funciones asignadas al paciente.')
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO especialidades (nombre, descripcion) VALUES
    ('Medicina general', 'Atención médica general.'),
    ('Pediatría', 'Atención de salud para niñas y niños.'),
    ('Cardiología', 'Atención de enfermedades del corazón.')
ON CONFLICT (nombre) DO NOTHING;
