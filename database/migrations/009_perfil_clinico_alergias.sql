-- CU23. PROPUESTA: no aplicar sin autorización expresa, incluso en local.
-- Aplicación única mediante un asistente local y una sola transacción externa.
-- No altera objetos ni registros de 001–008, citas, GiST o citas_historial.
-- Las tres tablas se crean vacías. No se infieren valores clínicos.

CREATE TABLE public.perfiles_clinicos (
    paciente_id BIGINT PRIMARY KEY REFERENCES public.pacientes(id) ON DELETE RESTRICT,
    grupo_sanguineo VARCHAR(3),
    fuente_grupo_sanguineo VARCHAR(120),
    observaciones VARCHAR(500),
    estado_alergias VARCHAR(20) NOT NULL DEFAULT 'no_registrado',
    version INTEGER NOT NULL DEFAULT 1,
    creado_por_usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    actualizado_por_usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT perfiles_clinicos_grupo_valido CHECK (
        grupo_sanguineo IS NULL OR grupo_sanguineo IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')
    ),
    CONSTRAINT perfiles_clinicos_observaciones_validas CHECK (
        observaciones IS NULL OR btrim(observaciones) <> ''
    ),
    CONSTRAINT perfiles_clinicos_grupo_documentado CHECK (
        (grupo_sanguineo IS NULL AND fuente_grupo_sanguineo IS NULL)
        OR (grupo_sanguineo IS NOT NULL AND fuente_grupo_sanguineo IS NOT NULL
            AND btrim(fuente_grupo_sanguineo) <> '')
    ),
    CONSTRAINT perfiles_clinicos_estado_alergias_valido CHECK (
        estado_alergias IN ('no_registrado', 'sin_conocidas', 'con_registros')
    ),
    CONSTRAINT perfiles_clinicos_version_valida CHECK (version > 0),
    CONSTRAINT perfiles_clinicos_fechas_validas CHECK (actualizado_en >= creado_en)
);

CREATE TABLE public.alergias_paciente (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    paciente_id BIGINT NOT NULL REFERENCES public.pacientes(id) ON DELETE RESTRICT,
    sustancia VARCHAR(120) NOT NULL,
    reaccion VARCHAR(250),
    observaciones VARCHAR(500),
    estado VARCHAR(10) NOT NULL DEFAULT 'activa',
    version INTEGER NOT NULL DEFAULT 1,
    creado_por_usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    actualizado_por_usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT alergias_paciente_sustancia_valida CHECK (
        btrim(regexp_replace(sustancia, '[[:space:]]+', ' ', 'g')) <> ''
        AND sustancia = btrim(regexp_replace(sustancia, '[[:space:]]+', ' ', 'g'))
    ),
    CONSTRAINT alergias_paciente_reaccion_valida CHECK (reaccion IS NULL OR btrim(reaccion) <> ''),
    CONSTRAINT alergias_paciente_observaciones_validas CHECK (
        observaciones IS NULL OR btrim(observaciones) <> ''
    ),
    CONSTRAINT alergias_paciente_estado_valido CHECK (estado IN ('activa', 'inactiva')),
    CONSTRAINT alergias_paciente_version_valida CHECK (version > 0),
    CONSTRAINT alergias_paciente_fechas_validas CHECK (actualizado_en >= creado_en)
);

-- Una misma sustancia no puede aparecer dos veces activa para el mismo paciente.
-- Se ignoran mayúsculas/espacios repetidos; no se infieren equivalencias médicas.
CREATE UNIQUE INDEX alergias_paciente_sustancia_activa_uidx
    ON public.alergias_paciente (
        paciente_id, lower(btrim(regexp_replace(sustancia, '[[:space:]]+', ' ', 'g')))
    ) WHERE estado = 'activa';
CREATE INDEX alergias_paciente_estado_idx ON public.alergias_paciente (paciente_id, estado, id);

-- Cada fila conserva la imagen posterior a una versión, incluido el registro inicial.
-- Las rectificaciones nunca sobrescriben las versiones anteriores mediante el servicio.
CREATE TABLE public.alergias_historial (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    alergia_id BIGINT NOT NULL REFERENCES public.alergias_paciente(id) ON DELETE RESTRICT,
    version INTEGER NOT NULL,
    evento VARCHAR(20) NOT NULL,
    sustancia VARCHAR(120) NOT NULL,
    reaccion VARCHAR(250),
    observaciones VARCHAR(500),
    estado VARCHAR(10) NOT NULL,
    motivo_cambio VARCHAR(250),
    usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    cambiado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT alergias_historial_version_unica UNIQUE (alergia_id, version),
    CONSTRAINT alergias_historial_sustancia_valida CHECK (
        btrim(regexp_replace(sustancia, '[[:space:]]+', ' ', 'g')) <> ''
        AND sustancia = btrim(regexp_replace(sustancia, '[[:space:]]+', ' ', 'g'))
    ),
    CONSTRAINT alergias_historial_reaccion_valida CHECK (reaccion IS NULL OR btrim(reaccion) <> ''),
    CONSTRAINT alergias_historial_observaciones_validas CHECK (
        observaciones IS NULL OR btrim(observaciones) <> ''
    ),
    CONSTRAINT alergias_historial_estado_valido CHECK (estado IN ('activa', 'inactiva')),
    CONSTRAINT alergias_historial_evento_valido CHECK (
        (version = 1 AND evento = 'registro' AND estado = 'activa')
        OR (version > 1 AND evento = 'actualizacion')
        OR (version > 1 AND evento = 'inactivacion' AND estado = 'inactiva')
        OR (version > 1 AND evento = 'reactivacion' AND estado = 'activa')
    ),
    CONSTRAINT alergias_historial_motivo_valido CHECK (
        (evento = 'registro' AND motivo_cambio IS NULL)
        OR (evento <> 'registro' AND motivo_cambio IS NOT NULL AND btrim(motivo_cambio) <> '')
    )
);

-- Ocho FK nuevas: 3 perfil + 3 alergia + 2 historial. Total propuesto: 12 tablas/20 FK.
-- La autorización asistencial, la versión esperada, la concordancia entre alergia
-- e historial y la declaración sin_conocidas se verifican también en el servicio,
-- bajo bloqueo del paciente y dentro de la misma transacción. No son reglas GiST.
