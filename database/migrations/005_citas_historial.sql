-- CU15. Aplicar una sola vez, en transacción y con autorización explícita.
-- Historial administrativo sin motivo clínico, nombres, correos ni credenciales.
-- No modifica citas existentes, estados, triggers ni la exclusión GiST.
CREATE TABLE citas_historial (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    cita_id BIGINT NOT NULL REFERENCES citas(id) ON DELETE RESTRICT,
    tipo VARCHAR(30) NOT NULL,
    inicio_anterior TIMESTAMPTZ NOT NULL,
    inicio_nuevo TIMESTAMPTZ NOT NULL,
    estado_anterior VARCHAR(20) NOT NULL,
    estado_nuevo VARCHAR(20) NOT NULL,
    usuario_id BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
    cambiado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT citas_historial_tipo_valido CHECK (tipo = 'reprogramacion'),
    CONSTRAINT citas_historial_reprogramacion_valida CHECK (
        inicio_anterior <> inicio_nuevo AND estado_anterior = 'programada' AND estado_nuevo = 'programada'
    )
);
CREATE INDEX citas_historial_cita_fecha_idx ON citas_historial(cita_id, cambiado_en, id);
-- Futuras cancelaciones/transiciones ampliarán expresamente los CHECK de tipo/evento.
-- No se implementan esas acciones ni se admiten eventos arbitrarios en CU15.
