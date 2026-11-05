-- CU24. PROPUESTA PENDIENTE DE AUTORIZACIÓN: no ejecutar todavía, ni en local.
-- Aplicación única en una transacción externa con rollback completo ante errores.
-- Solo crea cuatro tablas vacías y sus índices; no modifica objetos de 001–009.

CREATE TABLE public.antecedentes_clinicos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    paciente_id BIGINT NOT NULL REFERENCES public.pacientes(id) ON DELETE RESTRICT,
    categoria VARCHAR(20) NOT NULL,
    descripcion VARCHAR(1000) NOT NULL,
    fecha_evento DATE,
    fecha_aproximada BOOLEAN,
    observaciones VARCHAR(500),
    estado VARCHAR(10) NOT NULL DEFAULT 'activo',
    version INTEGER NOT NULL DEFAULT 1,
    creado_por_usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    actualizado_por_usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT antecedentes_clinicos_categoria_valida CHECK (
        categoria IN ('personal', 'quirurgico', 'familiar', 'otro')
    ),
    CONSTRAINT antecedentes_clinicos_descripcion_valida CHECK (btrim(descripcion) <> ''),
    CONSTRAINT antecedentes_clinicos_fecha_valida CHECK (
        (fecha_evento IS NULL AND fecha_aproximada IS NULL)
        OR (fecha_evento IS NOT NULL AND fecha_aproximada IS NOT NULL
            AND fecha_evento BETWEEN DATE '0001-01-01' AND DATE '9999-12-31')
    ),
    CONSTRAINT antecedentes_clinicos_observaciones_validas CHECK (
        observaciones IS NULL OR btrim(observaciones) <> ''
    ),
    CONSTRAINT antecedentes_clinicos_estado_valido CHECK (estado IN ('activo', 'inactivo')),
    CONSTRAINT antecedentes_clinicos_version_valida CHECK (version > 0),
    CONSTRAINT antecedentes_clinicos_fechas_validas CHECK (actualizado_en >= creado_en)
);

CREATE INDEX antecedentes_clinicos_paciente_fecha_idx
    ON public.antecedentes_clinicos (paciente_id, fecha_evento DESC NULLS LAST, id DESC);

-- Imagen posterior de cada versión, incluida la inicial; no almacena datos de alergias.
CREATE TABLE public.antecedentes_historial (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    antecedente_id BIGINT NOT NULL REFERENCES public.antecedentes_clinicos(id) ON DELETE RESTRICT,
    version INTEGER NOT NULL,
    evento VARCHAR(20) NOT NULL,
    categoria VARCHAR(20) NOT NULL,
    descripcion VARCHAR(1000) NOT NULL,
    fecha_evento DATE,
    fecha_aproximada BOOLEAN,
    observaciones VARCHAR(500),
    estado VARCHAR(10) NOT NULL,
    motivo_cambio VARCHAR(250),
    usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    cambiado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT antecedentes_historial_version_unica UNIQUE (antecedente_id, version),
    CONSTRAINT antecedentes_historial_categoria_valida CHECK (
        categoria IN ('personal', 'quirurgico', 'familiar', 'otro')
    ),
    CONSTRAINT antecedentes_historial_descripcion_valida CHECK (btrim(descripcion) <> ''),
    CONSTRAINT antecedentes_historial_fecha_valida CHECK (
        (fecha_evento IS NULL AND fecha_aproximada IS NULL)
        OR (fecha_evento IS NOT NULL AND fecha_aproximada IS NOT NULL
            AND fecha_evento BETWEEN DATE '0001-01-01' AND DATE '9999-12-31')
    ),
    CONSTRAINT antecedentes_historial_observaciones_validas CHECK (
        observaciones IS NULL OR btrim(observaciones) <> ''
    ),
    CONSTRAINT antecedentes_historial_estado_valido CHECK (estado IN ('activo', 'inactivo')),
    CONSTRAINT antecedentes_historial_evento_valido CHECK (
        (version = 1 AND evento = 'registro' AND estado = 'activo')
        OR (version > 1 AND evento = 'rectificacion')
        OR (version > 1 AND evento = 'inactivacion' AND estado = 'inactivo')
        OR (version > 1 AND evento = 'reactivacion' AND estado = 'activo')
    ),
    CONSTRAINT antecedentes_historial_motivo_valido CHECK (
        (evento = 'registro' AND motivo_cambio IS NULL)
        OR (evento <> 'registro' AND motivo_cambio IS NOT NULL AND btrim(motivo_cambio) <> '')
    )
);

CREATE TABLE public.resultados_laboratorio (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    paciente_id BIGINT NOT NULL REFERENCES public.pacientes(id) ON DELETE RESTRICT,
    estudio VARCHAR(120) NOT NULL,
    fecha_muestra DATE,
    fecha_resultado DATE,
    resultado_texto VARCHAR(2000) NOT NULL,
    valor NUMERIC(18,6),
    unidad VARCHAR(40),
    rango_referencia VARCHAR(250),
    laboratorio VARCHAR(160),
    observaciones VARCHAR(500),
    estado VARCHAR(10) NOT NULL DEFAULT 'activo',
    version INTEGER NOT NULL DEFAULT 1,
    creado_por_usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    actualizado_por_usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT resultados_laboratorio_estudio_valido CHECK (btrim(estudio) <> ''),
    CONSTRAINT resultados_laboratorio_texto_valido CHECK (btrim(resultado_texto) <> ''),
    CONSTRAINT resultados_laboratorio_fechas_estudio_validas CHECK (
        (fecha_muestra IS NULL OR fecha_muestra BETWEEN DATE '0001-01-01' AND DATE '9999-12-31')
        AND (fecha_resultado IS NULL OR fecha_resultado BETWEEN DATE '0001-01-01' AND DATE '9999-12-31')
        AND (fecha_muestra IS NULL OR fecha_resultado IS NULL OR fecha_muestra <= fecha_resultado)
    ),
    CONSTRAINT resultados_laboratorio_valor_valido CHECK (valor IS NULL OR valor <> 'NaN'::numeric),
    CONSTRAINT resultados_laboratorio_unidad_valida CHECK (
        unidad IS NULL OR (valor IS NOT NULL AND btrim(unidad) <> '')
    ),
    CONSTRAINT resultados_laboratorio_rango_valido CHECK (
        rango_referencia IS NULL OR btrim(rango_referencia) <> ''
    ),
    CONSTRAINT resultados_laboratorio_origen_valido CHECK (
        laboratorio IS NULL OR btrim(laboratorio) <> ''
    ),
    CONSTRAINT resultados_laboratorio_observaciones_validas CHECK (
        observaciones IS NULL OR btrim(observaciones) <> ''
    ),
    CONSTRAINT resultados_laboratorio_estado_valido CHECK (estado IN ('activo', 'inactivo')),
    CONSTRAINT resultados_laboratorio_version_valida CHECK (version > 0),
    CONSTRAINT resultados_laboratorio_fechas_validas CHECK (actualizado_en >= creado_en)
);

CREATE INDEX resultados_laboratorio_paciente_fecha_idx
    ON public.resultados_laboratorio (paciente_id, fecha_resultado DESC NULLS LAST, fecha_muestra DESC NULLS LAST, id DESC);

CREATE TABLE public.laboratorios_historial (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    resultado_id BIGINT NOT NULL REFERENCES public.resultados_laboratorio(id) ON DELETE RESTRICT,
    version INTEGER NOT NULL,
    evento VARCHAR(20) NOT NULL,
    estudio VARCHAR(120) NOT NULL,
    fecha_muestra DATE,
    fecha_resultado DATE,
    resultado_texto VARCHAR(2000) NOT NULL,
    valor NUMERIC(18,6),
    unidad VARCHAR(40),
    rango_referencia VARCHAR(250),
    laboratorio VARCHAR(160),
    observaciones VARCHAR(500),
    estado VARCHAR(10) NOT NULL,
    motivo_cambio VARCHAR(250),
    usuario_id BIGINT NOT NULL REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    cambiado_en TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT laboratorios_historial_version_unica UNIQUE (resultado_id, version),
    CONSTRAINT laboratorios_historial_estudio_valido CHECK (btrim(estudio) <> ''),
    CONSTRAINT laboratorios_historial_texto_valido CHECK (btrim(resultado_texto) <> ''),
    CONSTRAINT laboratorios_historial_fechas_estudio_validas CHECK (
        (fecha_muestra IS NULL OR fecha_muestra BETWEEN DATE '0001-01-01' AND DATE '9999-12-31')
        AND (fecha_resultado IS NULL OR fecha_resultado BETWEEN DATE '0001-01-01' AND DATE '9999-12-31')
        AND (fecha_muestra IS NULL OR fecha_resultado IS NULL OR fecha_muestra <= fecha_resultado)
    ),
    CONSTRAINT laboratorios_historial_valor_valido CHECK (valor IS NULL OR valor <> 'NaN'::numeric),
    CONSTRAINT laboratorios_historial_unidad_valida CHECK (
        unidad IS NULL OR (valor IS NOT NULL AND btrim(unidad) <> '')
    ),
    CONSTRAINT laboratorios_historial_rango_valido CHECK (
        rango_referencia IS NULL OR btrim(rango_referencia) <> ''
    ),
    CONSTRAINT laboratorios_historial_origen_valido CHECK (
        laboratorio IS NULL OR btrim(laboratorio) <> ''
    ),
    CONSTRAINT laboratorios_historial_observaciones_validas CHECK (
        observaciones IS NULL OR btrim(observaciones) <> ''
    ),
    CONSTRAINT laboratorios_historial_estado_valido CHECK (estado IN ('activo', 'inactivo')),
    CONSTRAINT laboratorios_historial_evento_valido CHECK (
        (version = 1 AND evento = 'registro' AND estado = 'activo')
        OR (version > 1 AND evento = 'rectificacion')
        OR (version > 1 AND evento = 'inactivacion' AND estado = 'inactivo')
        OR (version > 1 AND evento = 'reactivacion' AND estado = 'activo')
    ),
    CONSTRAINT laboratorios_historial_motivo_valido CHECK (
        (evento = 'registro' AND motivo_cambio IS NULL)
        OR (evento <> 'registro' AND motivo_cambio IS NOT NULL AND btrim(motivo_cambio) <> '')
    )
);

-- Diez FK nuevas: 3 antecedentes + 2 revisiones + 3 resultados + 2 revisiones.
-- Esquema propuesto después de 010: 16 tablas y 30 FK; actualmente sigue en 12/20.
-- Sin unicidad por descripción/estudio: pueden existir episodios y estudios repetidos.
-- El servicio verificará autorización, fecha no futura, versión esperada y coherencia
-- entre fila actual e historial bajo bloqueo del paciente en una sola transacción.
-- No se permite interpretar los estados de registro como conclusiones médicas.
