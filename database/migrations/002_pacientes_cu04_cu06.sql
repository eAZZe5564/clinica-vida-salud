-- CU04–CU06. Ejecutar en una transacción, solo después de autorización.
-- No reescribe documentos existentes: si hay colisiones normalizadas se revierte.
ALTER TABLE pacientes ADD COLUMN sexo VARCHAR(20), ADD COLUMN direccion VARCHAR(250);
ALTER TABLE pacientes ADD CONSTRAINT pacientes_sexo_valido
    CHECK (sexo IS NULL OR sexo IN ('femenino', 'masculino', 'no_especificado'));
ALTER TABLE pacientes ADD CONSTRAINT pacientes_nombres_no_vacios CHECK (btrim(nombres) <> '') NOT VALID;
ALTER TABLE pacientes ADD CONSTRAINT pacientes_apellidos_no_vacios CHECK (btrim(apellidos) <> '') NOT VALID;
ALTER TABLE pacientes ADD CONSTRAINT pacientes_documento_no_vacio
    CHECK (regexp_replace(documento_identidad, '[[:space:]]+', '', 'g') <> '') NOT VALID;
-- El índice es la garantía atómica, incluso con solicitudes concurrentes.
CREATE UNIQUE INDEX pacientes_documento_normalizado_uidx
    ON pacientes (upper(regexp_replace(documento_identidad, '[[:space:]]+', '', 'g')));
ALTER TABLE pacientes VALIDATE CONSTRAINT pacientes_nombres_no_vacios;
ALTER TABLE pacientes VALIDATE CONSTRAINT pacientes_apellidos_no_vacios;
ALTER TABLE pacientes VALIDATE CONSTRAINT pacientes_documento_no_vacio;
