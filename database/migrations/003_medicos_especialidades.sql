-- CU07–CU10. Aplicar solo con autorización, dentro de una transacción.
-- No modifica registros existentes ni reemplaza especialidades base.
ALTER TABLE medicos ADD COLUMN correo_contacto VARCHAR(254);
ALTER TABLE medicos ADD CONSTRAINT medicos_nombres_no_vacios CHECK (btrim(nombres) <> '');
ALTER TABLE medicos ADD CONSTRAINT medicos_apellidos_no_vacios CHECK (btrim(apellidos) <> '');
ALTER TABLE medicos ADD CONSTRAINT medicos_registro_no_vacio
    CHECK (regexp_replace(numero_colegiatura, '[[:space:]]+', '', 'g') <> '');
CREATE UNIQUE INDEX medicos_registro_normalizado_uidx
    ON medicos (upper(regexp_replace(numero_colegiatura, '[[:space:]]+', '', 'g')));
ALTER TABLE especialidades ADD CONSTRAINT especialidades_nombre_no_vacio
    CHECK (btrim(regexp_replace(nombre, '[[:space:]]+', ' ', 'g')) <> '');
-- Ignora mayúsculas y espacios repetidos, sin eliminar acentos.
CREATE UNIQUE INDEX especialidades_nombre_normalizado_uidx
    ON especialidades (lower(btrim(regexp_replace(nombre, '[[:space:]]+', ' ', 'g'))));
