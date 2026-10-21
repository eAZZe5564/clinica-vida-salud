-- Esquema inicial del Ciclo 1. Aplicar solo después de autorizarlo.
-- La extensión btree_gist permite combinar igualdad de médico y rangos de tiempo.
CREATE EXTENSION IF NOT EXISTS btree_gist;

CREATE TABLE roles (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(160),
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT roles_nombre_valido CHECK (nombre IN ('administrador', 'recepcionista', 'medico', 'paciente'))
);

CREATE TABLE usuarios (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    rol_id BIGINT NOT NULL REFERENCES roles(id),
    correo VARCHAR(254) NOT NULL,
    hash_contrasena VARCHAR(255) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    ultimo_acceso TIMESTAMPTZ,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT usuarios_correo_minusculas CHECK (correo = LOWER(correo))
);

CREATE UNIQUE INDEX usuarios_correo_unico_idx ON usuarios (LOWER(correo));

CREATE TABLE pacientes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id BIGINT UNIQUE REFERENCES usuarios(id) ON DELETE RESTRICT,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    documento_identidad VARCHAR(40) NOT NULL UNIQUE,
    telefono VARCHAR(30),
    correo_contacto VARCHAR(254),
    fecha_nacimiento DATE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medicos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id BIGINT UNIQUE REFERENCES usuarios(id) ON DELETE RESTRICT,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    numero_colegiatura VARCHAR(60) NOT NULL UNIQUE,
    telefono VARCHAR(30),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE especialidades (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(250),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medicos_especialidades (
    medico_id BIGINT NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    especialidad_id BIGINT NOT NULL REFERENCES especialidades(id) ON DELETE RESTRICT,
    asignada_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (medico_id, especialidad_id)
);

CREATE TABLE horarios_atencion (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    medico_id BIGINT NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    dia_semana SMALLINT NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT horarios_dia_semana_valido CHECK (dia_semana BETWEEN 1 AND 7),
    CONSTRAINT horarios_intervalo_valido CHECK (hora_inicio < hora_fin),
    CONSTRAINT horarios_medico_dia_inicio_unico UNIQUE (medico_id, dia_semana, hora_inicio)
);

CREATE TABLE citas (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    paciente_id BIGINT NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    medico_id BIGINT NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    fecha_hora_inicio TIMESTAMPTZ NOT NULL,
    duracion_minutos SMALLINT NOT NULL DEFAULT 30,
    estado VARCHAR(20) NOT NULL DEFAULT 'programada',
    motivo VARCHAR(500),
    creada_por_usuario_id BIGINT REFERENCES usuarios(id) ON DELETE RESTRICT,
    creada_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT citas_duracion_ciclo1 CHECK (duracion_minutos = 30),
    CONSTRAINT citas_estado_valido CHECK (estado IN ('programada', 'cancelada', 'atendida'))
);

CREATE INDEX pacientes_apellidos_idx ON pacientes (apellidos, nombres);
CREATE INDEX medicos_apellidos_idx ON medicos (apellidos, nombres);
CREATE INDEX horarios_medico_disponibilidad_idx ON horarios_atencion (medico_id, dia_semana, hora_inicio) WHERE activo;
CREATE INDEX citas_paciente_fecha_idx ON citas (paciente_id, fecha_hora_inicio DESC);
CREATE INDEX citas_medico_fecha_idx ON citas (medico_id, fecha_hora_inicio);

-- PostgreSQL impide de forma atómica cruces de citas del mismo médico,
-- incluso si dos transacciones intentan reservar el mismo intervalo a la vez.
ALTER TABLE citas
    ADD CONSTRAINT citas_medico_sin_superposicion
    EXCLUDE USING gist (
        medico_id WITH =,
        tsrange(
            fecha_hora_inicio AT TIME ZONE 'UTC',
            (fecha_hora_inicio AT TIME ZONE 'UTC') + make_interval(mins => duracion_minutos),
            '[)'
        ) WITH &&
    )
    WHERE (estado = 'programada');

-- Reglas críticas al reservar; la validación del horario exacto también corresponde
-- al servicio del servidor al construir el flujo de programación.
CREATE FUNCTION validar_cita_ciclo1() RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    medico_activo BOOLEAN;
    paciente_activo BOOLEAN;
BEGIN
    IF NEW.estado <> 'programada' THEN
        RETURN NEW;
    END IF;

    IF NEW.fecha_hora_inicio <= clock_timestamp() THEN
        RAISE EXCEPTION 'La cita debe programarse en una fecha futura'
            USING ERRCODE = '23514';
    END IF;

    SELECT activo INTO medico_activo
    FROM medicos
    WHERE id = NEW.medico_id
    FOR SHARE;

    IF NOT FOUND OR NOT medico_activo THEN
        RAISE EXCEPTION 'No se pueden reservar citas con un médico inactivo o inexistente'
            USING ERRCODE = '23514';
    END IF;

    SELECT activo INTO paciente_activo
    FROM pacientes
    WHERE id = NEW.paciente_id
    FOR SHARE;

    IF NOT FOUND OR NOT paciente_activo THEN
        RAISE EXCEPTION 'No se pueden reservar citas para un paciente inactivo o inexistente'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER citas_validar_reserva
    BEFORE INSERT OR UPDATE OF paciente_id, medico_id, fecha_hora_inicio, estado
    ON citas
    FOR EACH ROW
    EXECUTE FUNCTION validar_cita_ciclo1();
