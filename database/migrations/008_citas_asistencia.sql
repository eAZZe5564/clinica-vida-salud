-- CU18. Aplicación única, en transacción y tras autorización expresa.
-- Conserva las nueve tablas, doce FK, columnas y todos los registros existentes.
LOCK TABLE citas, citas_historial IN ACCESS EXCLUSIVE MODE;
ALTER TABLE citas DROP CONSTRAINT citas_estado_valido;
ALTER TABLE citas ADD CONSTRAINT citas_estado_valido CHECK (
    estado IN ('programada', 'cancelada', 'atendida', 'confirmada', 'presente', 'inasistencia')
);
ALTER TABLE citas DROP CONSTRAINT citas_medico_sin_superposicion;
ALTER TABLE citas ADD CONSTRAINT citas_medico_sin_superposicion
    EXCLUDE USING gist (
        medico_id WITH =,
        tsrange(fecha_hora_inicio AT TIME ZONE 'UTC',
            (fecha_hora_inicio AT TIME ZONE 'UTC') + make_interval(mins => duracion_minutos), '[)') WITH &&
    ) WHERE (estado IN ('programada', 'confirmada', 'presente', 'atendida', 'inasistencia'));

ALTER TABLE citas_historial DROP CONSTRAINT citas_historial_tipo_valido;
ALTER TABLE citas_historial DROP CONSTRAINT citas_historial_reprogramacion_valida;
ALTER TABLE citas_historial ADD CONSTRAINT citas_historial_tipo_valido CHECK (
    tipo IN ('reprogramacion', 'cancelacion', 'confirmacion', 'llegada', 'atencion', 'inasistencia')
);
ALTER TABLE citas_historial ADD CONSTRAINT citas_historial_reprogramacion_valida CHECK (
    (tipo = 'reprogramacion' AND inicio_anterior <> inicio_nuevo AND estado_anterior = 'programada' AND estado_nuevo = 'programada')
    OR (tipo = 'cancelacion' AND inicio_anterior = inicio_nuevo AND estado_anterior = 'programada' AND estado_nuevo = 'cancelada')
    OR (tipo = 'confirmacion' AND inicio_anterior = inicio_nuevo AND estado_anterior = 'programada' AND estado_nuevo = 'confirmada')
    OR (tipo = 'llegada' AND inicio_anterior = inicio_nuevo AND estado_anterior IN ('programada', 'confirmada') AND estado_nuevo = 'presente')
    OR (tipo = 'atencion' AND inicio_anterior = inicio_nuevo AND estado_anterior = 'presente' AND estado_nuevo = 'atendida')
    OR (tipo = 'inasistencia' AND inicio_anterior = inicio_nuevo AND estado_anterior IN ('programada', 'confirmada') AND estado_nuevo = 'inasistencia')
);
ALTER TABLE citas_historial ADD CONSTRAINT citas_historial_asistencia_tiempo_valido CHECK (
    (tipo <> 'atencion' OR cambiado_en >= inicio_nuevo)
    AND (tipo <> 'inasistencia' OR cambiado_en >= inicio_nuevo + INTERVAL '30 minutes')
);
CREATE UNIQUE INDEX citas_historial_asistencia_unica_idx ON citas_historial(cita_id, tipo)
    WHERE tipo IN ('llegada', 'atencion', 'inasistencia');
-- El CHECK de motivo de 006 exige NULL en los tres eventos nuevos.
-- El trigger de 007 se conserva: valida nuevas reservas y no confunde presencia
-- o estados terminales con una nueva programación. El servicio valida la transición.
-- GiST y la actualización se cambian atómicamente, sin ventana libre visible.
