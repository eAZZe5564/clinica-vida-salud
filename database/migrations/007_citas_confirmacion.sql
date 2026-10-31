-- CU17. Aplicación única y transaccional, solo después de autorización explícita.
-- Sin tablas nuevas ni cambios de datos. El bloqueo evita una ventana sin GiST.
LOCK TABLE citas, citas_historial IN ACCESS EXCLUSIVE MODE;
ALTER TABLE citas DROP CONSTRAINT citas_estado_valido;
ALTER TABLE citas ADD CONSTRAINT citas_estado_valido
    CHECK (estado IN ('programada', 'cancelada', 'atendida', 'confirmada'));
ALTER TABLE citas DROP CONSTRAINT citas_medico_sin_superposicion;
ALTER TABLE citas ADD CONSTRAINT citas_medico_sin_superposicion
    EXCLUDE USING gist (
        medico_id WITH =,
        tsrange(fecha_hora_inicio AT TIME ZONE 'UTC',
            (fecha_hora_inicio AT TIME ZONE 'UTC') + make_interval(mins => duracion_minutos), '[)') WITH &&
    ) WHERE (estado IN ('programada', 'confirmada'));

ALTER TABLE citas_historial DROP CONSTRAINT citas_historial_tipo_valido;
ALTER TABLE citas_historial DROP CONSTRAINT citas_historial_reprogramacion_valida;
ALTER TABLE citas_historial ADD CONSTRAINT citas_historial_tipo_valido
    CHECK (tipo IN ('reprogramacion', 'cancelacion', 'confirmacion'));
ALTER TABLE citas_historial ADD CONSTRAINT citas_historial_reprogramacion_valida CHECK (
    (tipo = 'reprogramacion' AND inicio_anterior <> inicio_nuevo
        AND estado_anterior = 'programada' AND estado_nuevo = 'programada')
    OR (tipo = 'cancelacion' AND inicio_anterior = inicio_nuevo
        AND estado_anterior = 'programada' AND estado_nuevo = 'cancelada')
    OR (tipo = 'confirmacion' AND inicio_anterior = inicio_nuevo
        AND estado_anterior = 'programada' AND estado_nuevo = 'confirmada')
);
-- El CHECK de motivo de 006 ya impone NULL para cualquier evento no cancelación.

CREATE OR REPLACE FUNCTION validar_cita_ciclo1() RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    medico_activo BOOLEAN;
    paciente_activo BOOLEAN;
BEGIN
    IF NEW.estado NOT IN ('programada', 'confirmada') THEN
        RETURN NEW;
    END IF;

    -- Confirmar solo el estado no vuelve a reservar ni cambia las reglas del turno.
    -- Permite conservar reservas previas aunque haya pasado la hora o un catálogo esté inactivo.
    IF TG_OP = 'UPDATE' THEN
        IF OLD.estado = 'programada' AND NEW.estado = 'confirmada'
            AND (to_jsonb(NEW) - 'estado') = (to_jsonb(OLD) - 'estado') THEN
            PERFORM id FROM medicos WHERE id = NEW.medico_id FOR SHARE;
            RETURN NEW;
        END IF;
    END IF;

    IF NEW.fecha_hora_inicio <= clock_timestamp() THEN
        RAISE EXCEPTION 'La cita debe programarse en una fecha futura'
            USING ERRCODE = '23514';
    END IF;
    SELECT activo INTO medico_activo FROM medicos WHERE id = NEW.medico_id FOR SHARE;
    IF NOT FOUND OR NOT medico_activo THEN
        RAISE EXCEPTION 'No se pueden reservar citas con un médico inactivo o inexistente'
            USING ERRCODE = '23514';
    END IF;
    SELECT activo INTO paciente_activo FROM pacientes WHERE id = NEW.paciente_id FOR SHARE;
    IF NOT FOUND OR NOT paciente_activo THEN
        RAISE EXCEPTION 'No se pueden reservar citas para un paciente inactivo o inexistente'
            USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
-- Se conserva el trigger existente, su nombre, eventos, filas y enlace a la función.
-- Nueve tablas, doce claves foráneas; migraciones 001–006 intactas.
