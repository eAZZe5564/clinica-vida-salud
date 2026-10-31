-- CU16. Aplicar una sola vez, en una transacción y con autorización explícita.
-- Amplía únicamente el historial 005; no cambia citas ni sus restricciones GiST.
ALTER TABLE citas_historial ADD COLUMN motivo_cancelacion VARCHAR(250);
ALTER TABLE citas_historial DROP CONSTRAINT citas_historial_tipo_valido;
ALTER TABLE citas_historial DROP CONSTRAINT citas_historial_reprogramacion_valida;
ALTER TABLE citas_historial ADD CONSTRAINT citas_historial_tipo_valido
    CHECK (tipo IN ('reprogramacion', 'cancelacion'));
ALTER TABLE citas_historial ADD CONSTRAINT citas_historial_reprogramacion_valida CHECK (
    (tipo = 'reprogramacion' AND inicio_anterior <> inicio_nuevo
        AND estado_anterior = 'programada' AND estado_nuevo = 'programada')
    OR (tipo = 'cancelacion' AND inicio_anterior = inicio_nuevo
        AND estado_anterior = 'programada' AND estado_nuevo = 'cancelada')
);
ALTER TABLE citas_historial ADD CONSTRAINT citas_historial_motivo_cancelacion_valido
    CHECK (motivo_cancelacion IS NULL OR (tipo = 'cancelacion' AND btrim(motivo_cancelacion) <> ''));
-- Los eventos existentes de reprogramación siguen siendo válidos, con motivo NULL.
-- Nueve tablas y doce claves foráneas. Se conservan PK, FK, índices y fechas de 005.
