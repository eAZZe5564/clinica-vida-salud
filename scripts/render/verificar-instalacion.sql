-- CU24: contrato 010 completo; no se aceptan instalaciones clínicas parciales.
DO $cu24$
DECLARE clinico jsonb;
BEGIN
    -- CU24_METADATA_INICIO
-- Solo metadatos públicos del contrato CU24; nunca selecciona datos clínicos.
SELECT jsonb_build_object(
 'tablas', COALESCE((SELECT jsonb_object_agg(c.relname,c.relkind::text)
   FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
   WHERE n.nspname='public' AND c.relname IN ('antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial')), '{}'::jsonb),
 'columnas', COALESCE((SELECT jsonb_object_agg(c.relname||'.'||a.attname,jsonb_build_object(
   'tipo',format_type(a.atttypid,a.atttypmod),'obligatoria',a.attnotnull,'identidad',a.attidentity::text,
   'defecto',pg_get_expr(d.adbin,d.adrelid)))
   FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_attribute a ON a.attrelid=c.oid
   LEFT JOIN pg_attrdef d ON d.adrelid=c.oid AND d.adnum=a.attnum
   WHERE n.nspname='public' AND c.relname IN ('antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial')
   AND a.attnum>0 AND NOT a.attisdropped), '{}'::jsonb),
 'restricciones', COALESCE((SELECT jsonb_object_agg(c.relname||'.'||f.conname,jsonb_build_object(
   'tipo',f.contype::text,'valida',f.convalidated,'diferible',f.condeferrable,'definicion',pg_get_constraintdef(f.oid)))
   FROM pg_constraint f JOIN pg_class c ON c.oid=f.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace
   WHERE n.nspname='public' AND c.relname IN ('antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial')), '{}'::jsonb),
 'indices', COALESCE((SELECT jsonb_object_agg(ic.relname,jsonb_build_object(
   'unico',i.indisunique,'valido',i.indisvalid,'listo',i.indisready,'definicion',pg_get_indexdef(i.indexrelid)))
   FROM pg_index i JOIN pg_class c ON c.oid=i.indrelid JOIN pg_namespace n ON n.oid=c.relnamespace
   JOIN pg_class ic ON ic.oid=i.indexrelid
   WHERE n.nspname='public' AND c.relname IN ('antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial')), '{}'::jsonb)
) INTO clinico;
    -- CU24_METADATA_FIN
    IF clinico <> '{"tablas":{},"columnas":{},"restricciones":{},"indices":{}}'::jsonb THEN
        IF clinico IS DISTINCT FROM $cu24_contrato$
{
  "tablas": {
    "antecedentes_clinicos": "r",
    "antecedentes_historial": "r",
    "laboratorios_historial": "r",
    "resultados_laboratorio": "r"
  },
  "indices": {
    "antecedentes_clinicos_pkey": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX antecedentes_clinicos_pkey ON public.antecedentes_clinicos USING btree (id)"
    },
    "antecedentes_historial_pkey": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX antecedentes_historial_pkey ON public.antecedentes_historial USING btree (id)"
    },
    "laboratorios_historial_pkey": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX laboratorios_historial_pkey ON public.laboratorios_historial USING btree (id)"
    },
    "resultados_laboratorio_pkey": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX resultados_laboratorio_pkey ON public.resultados_laboratorio USING btree (id)"
    },
    "antecedentes_historial_version_unica": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX antecedentes_historial_version_unica ON public.antecedentes_historial USING btree (antecedente_id, version)"
    },
    "laboratorios_historial_version_unica": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX laboratorios_historial_version_unica ON public.laboratorios_historial USING btree (resultado_id, version)"
    },
    "antecedentes_clinicos_paciente_fecha_idx": {
      "listo": true,
      "unico": false,
      "valido": true,
      "definicion": "CREATE INDEX antecedentes_clinicos_paciente_fecha_idx ON public.antecedentes_clinicos USING btree (paciente_id, fecha_evento DESC NULLS LAST, id DESC)"
    },
    "resultados_laboratorio_paciente_fecha_idx": {
      "listo": true,
      "unico": false,
      "valido": true,
      "definicion": "CREATE INDEX resultados_laboratorio_paciente_fecha_idx ON public.resultados_laboratorio USING btree (paciente_id, fecha_resultado DESC NULLS LAST, fecha_muestra DESC NULLS LAST, id DESC)"
    }
  },
  "columnas": {
    "antecedentes_clinicos.id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "a",
      "obligatoria": true
    },
    "antecedentes_historial.id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "a",
      "obligatoria": true
    },
    "laboratorios_historial.id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "a",
      "obligatoria": true
    },
    "resultados_laboratorio.id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "a",
      "obligatoria": true
    },
    "antecedentes_clinicos.estado": {
      "tipo": "character varying(10)",
      "defecto": "'activo'::character varying",
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.valor": {
      "tipo": "numeric(18,6)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "resultados_laboratorio.valor": {
      "tipo": "numeric(18,6)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "antecedentes_clinicos.version": {
      "tipo": "integer",
      "defecto": "1",
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_historial.estado": {
      "tipo": "character varying(10)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_historial.evento": {
      "tipo": "character varying(20)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.estado": {
      "tipo": "character varying(10)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.evento": {
      "tipo": "character varying(20)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.unidad": {
      "tipo": "character varying(40)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "resultados_laboratorio.estado": {
      "tipo": "character varying(10)",
      "defecto": "'activo'::character varying",
      "identidad": "",
      "obligatoria": true
    },
    "resultados_laboratorio.unidad": {
      "tipo": "character varying(40)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "antecedentes_historial.version": {
      "tipo": "integer",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.estudio": {
      "tipo": "character varying(120)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.version": {
      "tipo": "integer",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "resultados_laboratorio.estudio": {
      "tipo": "character varying(120)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "resultados_laboratorio.version": {
      "tipo": "integer",
      "defecto": "1",
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.categoria": {
      "tipo": "character varying(20)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.creado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_historial.categoria": {
      "tipo": "character varying(20)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "resultados_laboratorio.creado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.descripcion": {
      "tipo": "character varying(1000)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.paciente_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_historial.usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.fecha_evento": {
      "tipo": "date",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "antecedentes_historial.cambiado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_historial.descripcion": {
      "tipo": "character varying(1000)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.cambiado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "laboratorios_historial.laboratorio": {
      "tipo": "character varying(160)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "resultados_laboratorio.laboratorio": {
      "tipo": "character varying(160)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "resultados_laboratorio.paciente_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.observaciones": {
      "tipo": "character varying(500)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "antecedentes_historial.fecha_evento": {
      "tipo": "date",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "laboratorios_historial.resultado_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.actualizado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_historial.motivo_cambio": {
      "tipo": "character varying(250)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "antecedentes_historial.observaciones": {
      "tipo": "character varying(500)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "laboratorios_historial.fecha_muestra": {
      "tipo": "date",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "laboratorios_historial.motivo_cambio": {
      "tipo": "character varying(250)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "laboratorios_historial.observaciones": {
      "tipo": "character varying(500)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "resultados_laboratorio.fecha_muestra": {
      "tipo": "date",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "resultados_laboratorio.observaciones": {
      "tipo": "character varying(500)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "antecedentes_historial.antecedente_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "resultados_laboratorio.actualizado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.fecha_aproximada": {
      "tipo": "boolean",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "laboratorios_historial.fecha_resultado": {
      "tipo": "date",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "laboratorios_historial.resultado_texto": {
      "tipo": "character varying(2000)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "resultados_laboratorio.fecha_resultado": {
      "tipo": "date",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "resultados_laboratorio.resultado_texto": {
      "tipo": "character varying(2000)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_historial.fecha_aproximada": {
      "tipo": "boolean",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "laboratorios_historial.rango_referencia": {
      "tipo": "character varying(250)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "resultados_laboratorio.rango_referencia": {
      "tipo": "character varying(250)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "antecedentes_clinicos.creado_por_usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "resultados_laboratorio.creado_por_usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "antecedentes_clinicos.actualizado_por_usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "resultados_laboratorio.actualizado_por_usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    }
  },
  "restricciones": {
    "antecedentes_clinicos.antecedentes_clinicos_pkey": {
      "tipo": "p",
      "valida": true,
      "diferible": false,
      "definicion": "PRIMARY KEY (id)"
    },
    "antecedentes_historial.antecedentes_historial_pkey": {
      "tipo": "p",
      "valida": true,
      "diferible": false,
      "definicion": "PRIMARY KEY (id)"
    },
    "laboratorios_historial.laboratorios_historial_pkey": {
      "tipo": "p",
      "valida": true,
      "diferible": false,
      "definicion": "PRIMARY KEY (id)"
    },
    "resultados_laboratorio.resultados_laboratorio_pkey": {
      "tipo": "p",
      "valida": true,
      "diferible": false,
      "definicion": "PRIMARY KEY (id)"
    },
    "antecedentes_clinicos.antecedentes_clinicos_fecha_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((((fecha_evento IS NULL) AND (fecha_aproximada IS NULL)) OR ((fecha_evento IS NOT NULL) AND (fecha_aproximada IS NOT NULL) AND ((fecha_evento >= '0001-01-01'::date) AND (fecha_evento <= '9999-12-31'::date)))))"
    },
    "antecedentes_clinicos.antecedentes_clinicos_estado_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((estado)::text = ANY ((ARRAY['activo'::character varying, 'inactivo'::character varying])::text[])))"
    },
    "antecedentes_clinicos.antecedentes_clinicos_fechas_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((actualizado_en >= creado_en))"
    },
    "antecedentes_clinicos.antecedentes_clinicos_version_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((version > 0))"
    },
    "antecedentes_historial.antecedentes_historial_fecha_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((((fecha_evento IS NULL) AND (fecha_aproximada IS NULL)) OR ((fecha_evento IS NOT NULL) AND (fecha_aproximada IS NOT NULL) AND ((fecha_evento >= '0001-01-01'::date) AND (fecha_evento <= '9999-12-31'::date)))))"
    },
    "laboratorios_historial.laboratorios_historial_rango_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((rango_referencia IS NULL) OR (btrim((rango_referencia)::text) <> ''::text)))"
    },
    "laboratorios_historial.laboratorios_historial_texto_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((btrim((resultado_texto)::text) <> ''::text))"
    },
    "laboratorios_historial.laboratorios_historial_valor_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((valor IS NULL) OR (valor <> 'NaN'::numeric)))"
    },
    "resultados_laboratorio.resultados_laboratorio_rango_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((rango_referencia IS NULL) OR (btrim((rango_referencia)::text) <> ''::text)))"
    },
    "resultados_laboratorio.resultados_laboratorio_texto_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((btrim((resultado_texto)::text) <> ''::text))"
    },
    "resultados_laboratorio.resultados_laboratorio_valor_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((valor IS NULL) OR (valor <> 'NaN'::numeric)))"
    },
    "antecedentes_historial.antecedentes_historial_estado_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((estado)::text = ANY ((ARRAY['activo'::character varying, 'inactivo'::character varying])::text[])))"
    },
    "antecedentes_historial.antecedentes_historial_evento_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((((version = 1) AND ((evento)::text = 'registro'::text) AND ((estado)::text = 'activo'::text)) OR ((version > 1) AND ((evento)::text = 'rectificacion'::text)) OR ((version > 1) AND ((evento)::text = 'inactivacion'::text) AND ((estado)::text = 'inactivo'::text)) OR ((version > 1) AND ((evento)::text = 'reactivacion'::text) AND ((estado)::text = 'activo'::text))))"
    },
    "antecedentes_historial.antecedentes_historial_motivo_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((((evento)::text = 'registro'::text) AND (motivo_cambio IS NULL)) OR (((evento)::text <> 'registro'::text) AND (motivo_cambio IS NOT NULL) AND (btrim((motivo_cambio)::text) <> ''::text))))"
    },
    "antecedentes_historial.antecedentes_historial_version_unica": {
      "tipo": "u",
      "valida": true,
      "diferible": false,
      "definicion": "UNIQUE (antecedente_id, version)"
    },
    "laboratorios_historial.laboratorios_historial_estado_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((estado)::text = ANY ((ARRAY['activo'::character varying, 'inactivo'::character varying])::text[])))"
    },
    "laboratorios_historial.laboratorios_historial_evento_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((((version = 1) AND ((evento)::text = 'registro'::text) AND ((estado)::text = 'activo'::text)) OR ((version > 1) AND ((evento)::text = 'rectificacion'::text)) OR ((version > 1) AND ((evento)::text = 'inactivacion'::text) AND ((estado)::text = 'inactivo'::text)) OR ((version > 1) AND ((evento)::text = 'reactivacion'::text) AND ((estado)::text = 'activo'::text))))"
    },
    "laboratorios_historial.laboratorios_historial_motivo_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((((evento)::text = 'registro'::text) AND (motivo_cambio IS NULL)) OR (((evento)::text <> 'registro'::text) AND (motivo_cambio IS NOT NULL) AND (btrim((motivo_cambio)::text) <> ''::text))))"
    },
    "laboratorios_historial.laboratorios_historial_origen_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((laboratorio IS NULL) OR (btrim((laboratorio)::text) <> ''::text)))"
    },
    "laboratorios_historial.laboratorios_historial_unidad_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((unidad IS NULL) OR ((valor IS NOT NULL) AND (btrim((unidad)::text) <> ''::text))))"
    },
    "laboratorios_historial.laboratorios_historial_version_unica": {
      "tipo": "u",
      "valida": true,
      "diferible": false,
      "definicion": "UNIQUE (resultado_id, version)"
    },
    "resultados_laboratorio.resultados_laboratorio_estado_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((estado)::text = ANY ((ARRAY['activo'::character varying, 'inactivo'::character varying])::text[])))"
    },
    "resultados_laboratorio.resultados_laboratorio_origen_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((laboratorio IS NULL) OR (btrim((laboratorio)::text) <> ''::text)))"
    },
    "resultados_laboratorio.resultados_laboratorio_unidad_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((unidad IS NULL) OR ((valor IS NOT NULL) AND (btrim((unidad)::text) <> ''::text))))"
    },
    "antecedentes_clinicos.antecedentes_clinicos_categoria_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((categoria)::text = ANY ((ARRAY['personal'::character varying, 'quirurgico'::character varying, 'familiar'::character varying, 'otro'::character varying])::text[])))"
    },
    "antecedentes_clinicos.antecedentes_clinicos_paciente_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE RESTRICT"
    },
    "laboratorios_historial.laboratorios_historial_estudio_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((btrim((estudio)::text) <> ''::text))"
    },
    "resultados_laboratorio.resultados_laboratorio_estudio_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((btrim((estudio)::text) <> ''::text))"
    },
    "resultados_laboratorio.resultados_laboratorio_fechas_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((actualizado_en >= creado_en))"
    },
    "resultados_laboratorio.resultados_laboratorio_version_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((version > 0))"
    },
    "antecedentes_historial.antecedentes_historial_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "laboratorios_historial.laboratorios_historial_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "antecedentes_clinicos.antecedentes_clinicos_descripcion_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((btrim((descripcion)::text) <> ''::text))"
    },
    "antecedentes_historial.antecedentes_historial_categoria_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((categoria)::text = ANY ((ARRAY['personal'::character varying, 'quirurgico'::character varying, 'familiar'::character varying, 'otro'::character varying])::text[])))"
    },
    "resultados_laboratorio.resultados_laboratorio_paciente_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE RESTRICT"
    },
    "laboratorios_historial.laboratorios_historial_resultado_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (resultado_id) REFERENCES resultados_laboratorio(id) ON DELETE RESTRICT"
    },
    "antecedentes_historial.antecedentes_historial_descripcion_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((btrim((descripcion)::text) <> ''::text))"
    },
    "antecedentes_clinicos.antecedentes_clinicos_observaciones_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((observaciones IS NULL) OR (btrim((observaciones)::text) <> ''::text)))"
    },
    "antecedentes_historial.antecedentes_historial_antecedente_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (antecedente_id) REFERENCES antecedentes_clinicos(id) ON DELETE RESTRICT"
    },
    "antecedentes_historial.antecedentes_historial_observaciones_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((observaciones IS NULL) OR (btrim((observaciones)::text) <> ''::text)))"
    },
    "laboratorios_historial.laboratorios_historial_observaciones_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((observaciones IS NULL) OR (btrim((observaciones)::text) <> ''::text)))"
    },
    "resultados_laboratorio.resultados_laboratorio_observaciones_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((observaciones IS NULL) OR (btrim((observaciones)::text) <> ''::text)))"
    },
    "laboratorios_historial.laboratorios_historial_fechas_estudio_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((((fecha_muestra IS NULL) OR ((fecha_muestra >= '0001-01-01'::date) AND (fecha_muestra <= '9999-12-31'::date))) AND ((fecha_resultado IS NULL) OR ((fecha_resultado >= '0001-01-01'::date) AND (fecha_resultado <= '9999-12-31'::date))) AND ((fecha_muestra IS NULL) OR (fecha_resultado IS NULL) OR (fecha_muestra <= fecha_resultado))))"
    },
    "resultados_laboratorio.resultados_laboratorio_fechas_estudio_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((((fecha_muestra IS NULL) OR ((fecha_muestra >= '0001-01-01'::date) AND (fecha_muestra <= '9999-12-31'::date))) AND ((fecha_resultado IS NULL) OR ((fecha_resultado >= '0001-01-01'::date) AND (fecha_resultado <= '9999-12-31'::date))) AND ((fecha_muestra IS NULL) OR (fecha_resultado IS NULL) OR (fecha_muestra <= fecha_resultado))))"
    },
    "antecedentes_clinicos.antecedentes_clinicos_creado_por_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "resultados_laboratorio.resultados_laboratorio_creado_por_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "antecedentes_clinicos.antecedentes_clinicos_actualizado_por_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (actualizado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "resultados_laboratorio.resultados_laboratorio_actualizado_por_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (actualizado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    }
  }
}
$cu24_contrato$::jsonb OR to_regclass('public.perfiles_clinicos') IS NULL THEN
            RAISE EXCEPTION 'Esquema incompatible: contrato 010 parcial o alterado';
        END IF;
    END IF;
END;
$cu24$;
-- CU23: contrato completo de 009; se valida antes de proyectar el esquema administrativo.
DO $cu23$
DECLARE clinico jsonb;
BEGIN
    -- CU23_METADATA_INICIO
-- Solo metadatos públicos del contrato CU23; nunca selecciona datos clínicos.
SELECT jsonb_build_object(
 'tablas', COALESCE((SELECT jsonb_object_agg(c.relname,c.relkind::text)
   FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
   WHERE n.nspname='public' AND c.relname IN ('perfiles_clinicos','alergias_paciente','alergias_historial')), '{}'::jsonb),
 'columnas', COALESCE((SELECT jsonb_object_agg(c.relname||'.'||a.attname,jsonb_build_object(
   'tipo',format_type(a.atttypid,a.atttypmod),'obligatoria',a.attnotnull,'identidad',a.attidentity::text,
   'defecto',pg_get_expr(d.adbin,d.adrelid)))
   FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_attribute a ON a.attrelid=c.oid
   LEFT JOIN pg_attrdef d ON d.adrelid=c.oid AND d.adnum=a.attnum
   WHERE n.nspname='public' AND c.relname IN ('perfiles_clinicos','alergias_paciente','alergias_historial')
   AND a.attnum>0 AND NOT a.attisdropped), '{}'::jsonb),
 'restricciones', COALESCE((SELECT jsonb_object_agg(c.relname||'.'||f.conname,jsonb_build_object(
   'tipo',f.contype::text,'valida',f.convalidated,'diferible',f.condeferrable,'definicion',pg_get_constraintdef(f.oid)))
   FROM pg_constraint f JOIN pg_class c ON c.oid=f.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace
   WHERE n.nspname='public' AND c.relname IN ('perfiles_clinicos','alergias_paciente','alergias_historial')), '{}'::jsonb),
 'indices', COALESCE((SELECT jsonb_object_agg(ic.relname,jsonb_build_object(
   'unico',i.indisunique,'valido',i.indisvalid,'listo',i.indisready,'definicion',pg_get_indexdef(i.indexrelid)))
   FROM pg_index i JOIN pg_class c ON c.oid=i.indrelid JOIN pg_namespace n ON n.oid=c.relnamespace
   JOIN pg_class ic ON ic.oid=i.indexrelid
   WHERE n.nspname='public' AND c.relname IN ('perfiles_clinicos','alergias_paciente','alergias_historial')), '{}'::jsonb)
) INTO clinico;
    -- CU23_METADATA_FIN
    IF clinico <> '{"tablas":{},"columnas":{},"restricciones":{},"indices":{}}'::jsonb AND clinico IS DISTINCT FROM $cu23_contrato$
{
  "tablas": {
    "alergias_paciente": "r",
    "perfiles_clinicos": "r",
    "alergias_historial": "r"
  },
  "indices": {
    "alergias_paciente_pkey": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX alergias_paciente_pkey ON public.alergias_paciente USING btree (id)"
    },
    "perfiles_clinicos_pkey": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX perfiles_clinicos_pkey ON public.perfiles_clinicos USING btree (paciente_id)"
    },
    "alergias_historial_pkey": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX alergias_historial_pkey ON public.alergias_historial USING btree (id)"
    },
    "alergias_paciente_estado_idx": {
      "listo": true,
      "unico": false,
      "valido": true,
      "definicion": "CREATE INDEX alergias_paciente_estado_idx ON public.alergias_paciente USING btree (paciente_id, estado, id)"
    },
    "alergias_historial_version_unica": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX alergias_historial_version_unica ON public.alergias_historial USING btree (alergia_id, version)"
    },
    "alergias_paciente_sustancia_activa_uidx": {
      "listo": true,
      "unico": true,
      "valido": true,
      "definicion": "CREATE UNIQUE INDEX alergias_paciente_sustancia_activa_uidx ON public.alergias_paciente USING btree (paciente_id, lower(btrim(regexp_replace((sustancia)::text, '[[:space:]]+'::text, ' '::text, 'g'::text)))) WHERE ((estado)::text = 'activa'::text)"
    }
  },
  "columnas": {
    "alergias_paciente.id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "a",
      "obligatoria": true
    },
    "alergias_historial.id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "a",
      "obligatoria": true
    },
    "alergias_paciente.estado": {
      "tipo": "character varying(10)",
      "defecto": "'activa'::character varying",
      "identidad": "",
      "obligatoria": true
    },
    "alergias_historial.estado": {
      "tipo": "character varying(10)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "alergias_historial.evento": {
      "tipo": "character varying(20)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "alergias_paciente.version": {
      "tipo": "integer",
      "defecto": "1",
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.version": {
      "tipo": "integer",
      "defecto": "1",
      "identidad": "",
      "obligatoria": true
    },
    "alergias_historial.version": {
      "tipo": "integer",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "alergias_paciente.reaccion": {
      "tipo": "character varying(250)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "alergias_historial.reaccion": {
      "tipo": "character varying(250)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "alergias_paciente.creado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "alergias_paciente.sustancia": {
      "tipo": "character varying(120)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.creado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "alergias_historial.sustancia": {
      "tipo": "character varying(120)",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "alergias_historial.alergia_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "alergias_historial.usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "alergias_paciente.paciente_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.paciente_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "alergias_historial.cambiado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "alergias_paciente.observaciones": {
      "tipo": "character varying(500)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "perfiles_clinicos.observaciones": {
      "tipo": "character varying(500)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "alergias_historial.motivo_cambio": {
      "tipo": "character varying(250)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "alergias_historial.observaciones": {
      "tipo": "character varying(500)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "alergias_paciente.actualizado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.actualizado_en": {
      "tipo": "timestamp with time zone",
      "defecto": "clock_timestamp()",
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.estado_alergias": {
      "tipo": "character varying(20)",
      "defecto": "'no_registrado'::character varying",
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.grupo_sanguineo": {
      "tipo": "character varying(3)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "alergias_paciente.creado_por_usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.creado_por_usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.fuente_grupo_sanguineo": {
      "tipo": "character varying(120)",
      "defecto": null,
      "identidad": "",
      "obligatoria": false
    },
    "alergias_paciente.actualizado_por_usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    },
    "perfiles_clinicos.actualizado_por_usuario_id": {
      "tipo": "bigint",
      "defecto": null,
      "identidad": "",
      "obligatoria": true
    }
  },
  "restricciones": {
    "alergias_paciente.alergias_paciente_pkey": {
      "tipo": "p",
      "valida": true,
      "diferible": false,
      "definicion": "PRIMARY KEY (id)"
    },
    "perfiles_clinicos.perfiles_clinicos_pkey": {
      "tipo": "p",
      "valida": true,
      "diferible": false,
      "definicion": "PRIMARY KEY (paciente_id)"
    },
    "alergias_historial.alergias_historial_pkey": {
      "tipo": "p",
      "valida": true,
      "diferible": false,
      "definicion": "PRIMARY KEY (id)"
    },
    "perfiles_clinicos.perfiles_clinicos_grupo_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((grupo_sanguineo IS NULL) OR ((grupo_sanguineo)::text = ANY ((ARRAY['A+'::character varying, 'A-'::character varying, 'B+'::character varying, 'B-'::character varying, 'AB+'::character varying, 'AB-'::character varying, 'O+'::character varying, 'O-'::character varying])::text[]))))"
    },
    "alergias_paciente.alergias_paciente_estado_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((estado)::text = ANY ((ARRAY['activa'::character varying, 'inactiva'::character varying])::text[])))"
    },
    "alergias_paciente.alergias_paciente_fechas_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((actualizado_en >= creado_en))"
    },
    "alergias_paciente.alergias_paciente_version_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((version > 0))"
    },
    "perfiles_clinicos.perfiles_clinicos_fechas_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((actualizado_en >= creado_en))"
    },
    "perfiles_clinicos.perfiles_clinicos_version_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((version > 0))"
    },
    "alergias_historial.alergias_historial_estado_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((estado)::text = ANY ((ARRAY['activa'::character varying, 'inactiva'::character varying])::text[])))"
    },
    "alergias_historial.alergias_historial_evento_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((((version = 1) AND ((evento)::text = 'registro'::text) AND ((estado)::text = 'activa'::text)) OR ((version > 1) AND ((evento)::text = 'actualizacion'::text)) OR ((version > 1) AND ((evento)::text = 'inactivacion'::text) AND ((estado)::text = 'inactiva'::text)) OR ((version > 1) AND ((evento)::text = 'reactivacion'::text) AND ((estado)::text = 'activa'::text))))"
    },
    "alergias_historial.alergias_historial_motivo_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((((evento)::text = 'registro'::text) AND (motivo_cambio IS NULL)) OR (((evento)::text <> 'registro'::text) AND (motivo_cambio IS NOT NULL) AND (btrim((motivo_cambio)::text) <> ''::text))))"
    },
    "alergias_historial.alergias_historial_version_unica": {
      "tipo": "u",
      "valida": true,
      "diferible": false,
      "definicion": "UNIQUE (alergia_id, version)"
    },
    "alergias_paciente.alergias_paciente_reaccion_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((reaccion IS NULL) OR (btrim((reaccion)::text) <> ''::text)))"
    },
    "alergias_paciente.alergias_paciente_paciente_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE RESTRICT"
    },
    "alergias_paciente.alergias_paciente_sustancia_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((btrim(regexp_replace((sustancia)::text, '[[:space:]]+'::text, ' '::text, 'g'::text)) <> ''::text) AND ((sustancia)::text = btrim(regexp_replace((sustancia)::text, '[[:space:]]+'::text, ' '::text, 'g'::text)))))"
    },
    "perfiles_clinicos.perfiles_clinicos_paciente_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE RESTRICT"
    },
    "alergias_historial.alergias_historial_alergia_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (alergia_id) REFERENCES alergias_paciente(id) ON DELETE RESTRICT"
    },
    "alergias_historial.alergias_historial_reaccion_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((reaccion IS NULL) OR (btrim((reaccion)::text) <> ''::text)))"
    },
    "alergias_historial.alergias_historial_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "perfiles_clinicos.perfiles_clinicos_grupo_documentado": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK ((((grupo_sanguineo IS NULL) AND (fuente_grupo_sanguineo IS NULL)) OR ((grupo_sanguineo IS NOT NULL) AND (fuente_grupo_sanguineo IS NOT NULL) AND (btrim((fuente_grupo_sanguineo)::text) <> ''::text))))"
    },
    "alergias_historial.alergias_historial_sustancia_valida": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((btrim(regexp_replace((sustancia)::text, '[[:space:]]+'::text, ' '::text, 'g'::text)) <> ''::text) AND ((sustancia)::text = btrim(regexp_replace((sustancia)::text, '[[:space:]]+'::text, ' '::text, 'g'::text)))))"
    },
    "alergias_paciente.alergias_paciente_observaciones_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((observaciones IS NULL) OR (btrim((observaciones)::text) <> ''::text)))"
    },
    "perfiles_clinicos.perfiles_clinicos_observaciones_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((observaciones IS NULL) OR (btrim((observaciones)::text) <> ''::text)))"
    },
    "perfiles_clinicos.perfiles_clinicos_estado_alergias_valido": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((estado_alergias)::text = ANY ((ARRAY['no_registrado'::character varying, 'sin_conocidas'::character varying, 'con_registros'::character varying])::text[])))"
    },
    "alergias_historial.alergias_historial_observaciones_validas": {
      "tipo": "c",
      "valida": true,
      "diferible": false,
      "definicion": "CHECK (((observaciones IS NULL) OR (btrim((observaciones)::text) <> ''::text)))"
    },
    "alergias_paciente.alergias_paciente_creado_por_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "perfiles_clinicos.perfiles_clinicos_creado_por_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "alergias_paciente.alergias_paciente_actualizado_por_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (actualizado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    },
    "perfiles_clinicos.perfiles_clinicos_actualizado_por_usuario_id_fkey": {
      "tipo": "f",
      "valida": true,
      "diferible": false,
      "definicion": "FOREIGN KEY (actualizado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT"
    }
  }
}
$cu23_contrato$::jsonb THEN
        RAISE EXCEPTION 'Esquema incompatible: contrato clinico 009 parcial o alterado';
    END IF;
END;
$cu23$;
-- Esquema 001-004 (8/10), 001-005/006/007/008 (9/12, contratos estrictos).
-- Solo catalogos y comprobaciones. No instala ni modifica objetos o registros.
DO $$
DECLARE
    esquema jsonb;
    tablas_esperadas jsonb := '["roles","usuarios","pacientes","medicos","especialidades","medicos_especialidades","horarios_atencion","citas"]';
    columnas_esperadas jsonb := '{
      "roles.id":"bigint","roles.nombre":"character varying(30)","roles.descripcion":"character varying(160)","roles.creado_en":"timestamp with time zone",
      "usuarios.id":"bigint","usuarios.rol_id":"bigint","usuarios.correo":"character varying(254)","usuarios.hash_contrasena":"character varying(255)","usuarios.activo":"boolean","usuarios.ultimo_acceso":"timestamp with time zone","usuarios.creado_en":"timestamp with time zone","usuarios.actualizado_en":"timestamp with time zone",
      "pacientes.id":"bigint","pacientes.usuario_id":"bigint","pacientes.nombres":"character varying(100)","pacientes.apellidos":"character varying(100)","pacientes.documento_identidad":"character varying(40)","pacientes.telefono":"character varying(30)","pacientes.correo_contacto":"character varying(254)","pacientes.fecha_nacimiento":"date","pacientes.activo":"boolean","pacientes.creado_en":"timestamp with time zone","pacientes.actualizado_en":"timestamp with time zone","pacientes.sexo":"character varying(20)","pacientes.direccion":"character varying(250)",
      "medicos.id":"bigint","medicos.usuario_id":"bigint","medicos.nombres":"character varying(100)","medicos.apellidos":"character varying(100)","medicos.numero_colegiatura":"character varying(60)","medicos.telefono":"character varying(30)","medicos.activo":"boolean","medicos.creado_en":"timestamp with time zone","medicos.actualizado_en":"timestamp with time zone","medicos.correo_contacto":"character varying(254)",
      "especialidades.id":"bigint","especialidades.nombre":"character varying(100)","especialidades.descripcion":"character varying(250)","especialidades.activo":"boolean","especialidades.creado_en":"timestamp with time zone",
      "medicos_especialidades.medico_id":"bigint","medicos_especialidades.especialidad_id":"bigint","medicos_especialidades.asignada_en":"timestamp with time zone",
      "horarios_atencion.id":"bigint","horarios_atencion.medico_id":"bigint","horarios_atencion.dia_semana":"smallint","horarios_atencion.hora_inicio":"time without time zone","horarios_atencion.hora_fin":"time without time zone","horarios_atencion.activo":"boolean","horarios_atencion.creado_en":"timestamp with time zone",
      "citas.id":"bigint","citas.paciente_id":"bigint","citas.medico_id":"bigint","citas.fecha_hora_inicio":"timestamp with time zone","citas.duracion_minutos":"smallint","citas.estado":"character varying(20)","citas.motivo":"character varying(500)","citas.creada_por_usuario_id":"bigint","citas.creada_en":"timestamp with time zone","citas.especialidad_id":"bigint"
    }';
    referencias_esperadas jsonb := '{
      "usuarios.rol_id":["public.roles.id","a"],
      "pacientes.usuario_id":["public.usuarios.id","r"],
      "medicos.usuario_id":["public.usuarios.id","r"],
      "medicos_especialidades.medico_id":["public.medicos.id","r"],
      "medicos_especialidades.especialidad_id":["public.especialidades.id","r"],
      "horarios_atencion.medico_id":["public.medicos.id","r"],
      "citas.paciente_id":["public.pacientes.id","r"],
      "citas.medico_id":["public.medicos.id","r"],
      "citas.creada_por_usuario_id":["public.usuarios.id","r"],
      "citas.especialidad_id":["public.especialidades.id","r"]
    }';
    columnas_opcionales jsonb := '["roles.descripcion","usuarios.ultimo_acceso","pacientes.usuario_id","pacientes.telefono","pacientes.correo_contacto","pacientes.fecha_nacimiento","pacientes.sexo","pacientes.direccion","medicos.usuario_id","medicos.telefono","medicos.correo_contacto","especialidades.descripcion","citas.motivo","citas.creada_por_usuario_id","citas.especialidad_id"]';
    campo record;
    referencia record;
    indice jsonb;
    ciclo2 boolean;
    cancelaciones boolean;
    confirmaciones boolean;
    asistencias boolean;
    indices_esperados jsonb := '{"citas_historial_pkey":["id"],"citas_historial_cita_fecha_idx":["cita_id","cambiado_en","id"]}';
    predicado_asistencia text := '((tipo)::text = ANY ((ARRAY[''llegada''::character varying, ''atencion''::character varying, ''inasistencia''::character varying])::text[]))';
    estados_citas text := 'CHECK (((estado)::text = ANY ((ARRAY[''programada''::character varying, ''cancelada''::character varying, ''atendida''::character varying])::text[])))';
    exclusion_citas text := 'EXCLUDE USING gist (medico_id WITH =, tsrange((fecha_hora_inicio AT TIME ZONE ''UTC''::text), ((fecha_hora_inicio AT TIME ZONE ''UTC''::text) + make_interval(mins => (duracion_minutos)::integer)), ''[)''::text) WITH &&) WHERE (((estado)::text = ''programada''::text))';
    tipo_historial text := 'CHECK (((tipo)::text = ''reprogramacion''::text))';
    evento_historial text := 'CHECK (((inicio_anterior <> inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''programada''::text)))';
BEGIN
    -- CU15_METADATA_INICIO: unico punto de lectura, sustituido solo por fixtures aislados en tests.
    SELECT jsonb_build_object(
      'tablas', COALESCE((SELECT jsonb_agg(c.relname ORDER BY c.relname) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relkind IN ('r','p') AND c.relname NOT IN ('perfiles_clinicos','alergias_paciente','alergias_historial','antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial')),'[]'::jsonb),
      'historial_tipo', (SELECT c.relkind::text FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname='citas_historial'),
      'columnas', COALESCE((SELECT jsonb_object_agg(c.relname||'.'||a.attname,jsonb_build_object('tipo',format_type(a.atttypid,a.atttypmod),'obligatoria',a.attnotnull,'identidad',a.attidentity::text,'defecto',pg_get_expr(d.adbin,d.adrelid)))
        FROM pg_attribute a JOIN pg_class c ON c.oid=a.attrelid JOIN pg_namespace n ON n.oid=c.relnamespace LEFT JOIN pg_attrdef d ON d.adrelid=a.attrelid AND d.adnum=a.attnum
        WHERE n.nspname='public' AND c.relkind IN ('r','p') AND c.relname NOT IN ('perfiles_clinicos','alergias_paciente','alergias_historial','antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial') AND a.attnum>0 AND NOT a.attisdropped),'{}'::jsonb),
      'referencias', COALESCE((SELECT jsonb_agg(jsonb_build_object(
        'origen',c.relname||'.'||(SELECT string_agg(a.attname,',' ORDER BY k.orden) FROM unnest(f.conkey) WITH ORDINALITY k(numero,orden) JOIN pg_attribute a ON a.attrelid=c.oid AND a.attnum=k.numero),
        'destino',rn.nspname||'.'||rc.relname||'.'||(SELECT string_agg(a.attname,',' ORDER BY k.orden) FROM unnest(f.confkey) WITH ORDINALITY k(numero,orden) JOIN pg_attribute a ON a.attrelid=rc.oid AND a.attnum=k.numero),
        'borrado',f.confdeltype::text,'actualizacion',f.confupdtype::text,'valida',f.convalidated,'diferible',f.condeferrable))
        FROM pg_constraint f JOIN pg_class c ON c.oid=f.conrelid JOIN pg_namespace n ON n.oid=f.connamespace JOIN pg_class rc ON rc.oid=f.confrelid JOIN pg_namespace rn ON rn.oid=rc.relnamespace WHERE n.nspname='public' AND f.contype='f' AND c.relname NOT IN ('perfiles_clinicos','alergias_paciente','alergias_historial','antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial')),'[]'::jsonb),
      'indices_historial', COALESCE((SELECT jsonb_object_agg(ic.relname,jsonb_build_object(
        'metodo',am.amname,'unico',i.indisunique,'valido',i.indisvalid,'listo',i.indisready,'definicion',pg_get_indexdef(i.indexrelid),
        'atributos',i.indnatts,'claves',i.indnkeyatts,'predicado',pg_get_expr(i.indpred,i.indrelid),
        'columnas',(SELECT jsonb_agg(a.attname ORDER BY k.orden) FROM unnest(i.indkey) WITH ORDINALITY k(numero,orden) LEFT JOIN pg_attribute a ON a.attrelid=t.oid AND a.attnum=k.numero)))
        FROM pg_index i JOIN pg_class t ON t.oid=i.indrelid JOIN pg_namespace n ON n.oid=t.relnamespace JOIN pg_class ic ON ic.oid=i.indexrelid JOIN pg_am am ON am.oid=ic.relam WHERE n.nspname='public' AND t.relname='citas_historial'),'{}'::jsonb),
      'restricciones', COALESCE((SELECT jsonb_object_agg(c.relname||'.'||f.conname,jsonb_build_object('tipo',f.contype::text,'valida',f.convalidated,'definicion',pg_get_constraintdef(f.oid)))
        FROM pg_constraint f JOIN pg_class c ON c.oid=f.conrelid JOIN pg_namespace n ON n.oid=f.connamespace WHERE n.nspname='public' AND c.relname IN ('citas','citas_historial')),'{}'::jsonb),
      'btree_gist', EXISTS(SELECT 1 FROM pg_extension WHERE extname='btree_gist'),
      'trigger_citas', EXISTS(SELECT 1 FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_proc p ON p.oid=t.tgfoid JOIN pg_namespace pn ON pn.oid=p.pronamespace WHERE n.nspname='public' AND c.relname='citas' AND t.tgname='citas_validar_reserva' AND NOT t.tgisinternal AND t.tgenabled IN ('O','A') AND t.tgtype=23 AND pn.nspname='public' AND p.proname='validar_cita_ciclo1'),
      'funcion_citas', (SELECT jsonb_build_object('huella',md5(btrim(regexp_replace(p.prosrc,'[[:space:]]+',' ','g'))),'lenguaje',l.lanname,'tipo',p.prorettype::regtype::text,'definidor',p.prosecdef)
        FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace JOIN pg_language l ON l.oid=p.prolang WHERE n.nspname='public' AND p.proname='validar_cita_ciclo1' AND p.pronargs=0),
      'roles', (SELECT count(*) FROM public.roles WHERE nombre IN ('administrador','recepcionista','medico','paciente')),
      'especialidades', (SELECT count(*) FROM public.especialidades WHERE nombre IN ('Medicina general','Pediatría','Cardiología'))
    ) INTO esquema;
    -- CU15_METADATA_FIN

    IF esquema->>'historial_tipo' IS NOT NULL AND esquema->>'historial_tipo'<>'r' THEN
        RAISE EXCEPTION 'Esquema incompatible: historial no es una tabla ordinaria';
    END IF;
    ciclo2 := esquema->>'historial_tipo'='r';
    ciclo2 := COALESCE(ciclo2,false);
    cancelaciones := esquema->'columnas' ? 'citas_historial.motivo_cancelacion';
    confirmaciones := position('confirmada' IN COALESCE(esquema->'restricciones'->'citas.citas_estado_valido'->>'definicion',''))>0;
    asistencias := position('presente' IN COALESCE(esquema->'restricciones'->'citas.citas_estado_valido'->>'definicion',''))>0;
    IF ciclo2 THEN
        tablas_esperadas := tablas_esperadas || '["citas_historial"]'::jsonb;
        columnas_esperadas := columnas_esperadas || '{
          "citas_historial.id":"bigint","citas_historial.cita_id":"bigint","citas_historial.tipo":"character varying(30)",
          "citas_historial.inicio_anterior":"timestamp with time zone","citas_historial.inicio_nuevo":"timestamp with time zone",
          "citas_historial.estado_anterior":"character varying(20)","citas_historial.estado_nuevo":"character varying(20)",
          "citas_historial.usuario_id":"bigint","citas_historial.cambiado_en":"timestamp with time zone"
        }'::jsonb;
        referencias_esperadas := referencias_esperadas || '{
          "citas_historial.cita_id":["public.citas.id","r"],
          "citas_historial.usuario_id":["public.usuarios.id","r"]
        }'::jsonb;
    END IF;
    IF cancelaciones THEN
        IF NOT ciclo2 THEN RAISE EXCEPTION 'Esquema incompatible: cancelacion sin historial'; END IF;
        columnas_esperadas := columnas_esperadas || '{"citas_historial.motivo_cancelacion":"character varying(250)"}'::jsonb;
        columnas_opcionales := columnas_opcionales || '["citas_historial.motivo_cancelacion"]'::jsonb;
        tipo_historial := 'CHECK (((tipo)::text = ANY ((ARRAY[''reprogramacion''::character varying, ''cancelacion''::character varying])::text[])))';
        evento_historial := 'CHECK (((((tipo)::text = ''reprogramacion''::text) AND (inicio_anterior <> inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''programada''::text)) OR (((tipo)::text = ''cancelacion''::text) AND (inicio_anterior = inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''cancelada''::text))))';
        IF esquema->'restricciones'->'citas_historial.citas_historial_motivo_cancelacion_valido'->>'tipo' IS DISTINCT FROM 'c'
           OR esquema->'restricciones'->'citas_historial.citas_historial_motivo_cancelacion_valido'->>'valida' IS DISTINCT FROM 'true'
           OR esquema->'restricciones'->'citas_historial.citas_historial_motivo_cancelacion_valido'->>'definicion' IS DISTINCT FROM
            'CHECK (((motivo_cancelacion IS NULL) OR (((tipo)::text = ''cancelacion''::text) AND (btrim((motivo_cancelacion)::text) <> ''''::text))))' THEN
            RAISE EXCEPTION 'Esquema incompatible: restriccion de motivo de cancelacion';
        END IF;
    ELSIF esquema->'restricciones' ? 'citas_historial.citas_historial_motivo_cancelacion_valido' THEN
        RAISE EXCEPTION 'Esquema incompatible: migracion 006 parcial';
    END IF;
    IF confirmaciones THEN
        IF NOT cancelaciones THEN RAISE EXCEPTION 'Esquema incompatible: 007 requiere 006 completa'; END IF;
        estados_citas := 'CHECK (((estado)::text = ANY ((ARRAY[''programada''::character varying, ''cancelada''::character varying, ''atendida''::character varying, ''confirmada''::character varying])::text[])))';
        exclusion_citas := 'EXCLUDE USING gist (medico_id WITH =, tsrange((fecha_hora_inicio AT TIME ZONE ''UTC''::text), ((fecha_hora_inicio AT TIME ZONE ''UTC''::text) + make_interval(mins => (duracion_minutos)::integer)), ''[)''::text) WITH &&) WHERE (((estado)::text = ANY ((ARRAY[''programada''::character varying, ''confirmada''::character varying])::text[])))';
        tipo_historial := 'CHECK (((tipo)::text = ANY ((ARRAY[''reprogramacion''::character varying, ''cancelacion''::character varying, ''confirmacion''::character varying])::text[])))';
        evento_historial := 'CHECK (((((tipo)::text = ''reprogramacion''::text) AND (inicio_anterior <> inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''programada''::text)) OR (((tipo)::text = ''cancelacion''::text) AND (inicio_anterior = inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''cancelada''::text)) OR (((tipo)::text = ''confirmacion''::text) AND (inicio_anterior = inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''confirmada''::text))))';
        IF esquema->'funcion_citas'->>'huella' IS DISTINCT FROM 'b35f6332097bc2f3ae6a0c8f07af19d6'
           OR esquema->'funcion_citas'->>'lenguaje' IS DISTINCT FROM 'plpgsql'
           OR esquema->'funcion_citas'->>'tipo' IS DISTINCT FROM 'trigger'
           OR esquema->'funcion_citas'->>'definidor' IS DISTINCT FROM 'false' THEN
            RAISE EXCEPTION 'Esquema incompatible: funcion del trigger no corresponde a 007';
        END IF;
    END IF;
    IF asistencias THEN
        IF NOT confirmaciones THEN RAISE EXCEPTION 'Esquema incompatible: 008 requiere 007 completa'; END IF;
        estados_citas := 'CHECK (((estado)::text = ANY ((ARRAY[''programada''::character varying, ''cancelada''::character varying, ''atendida''::character varying, ''confirmada''::character varying, ''presente''::character varying, ''inasistencia''::character varying])::text[])))';
        exclusion_citas := 'EXCLUDE USING gist (medico_id WITH =, tsrange((fecha_hora_inicio AT TIME ZONE ''UTC''::text), ((fecha_hora_inicio AT TIME ZONE ''UTC''::text) + make_interval(mins => (duracion_minutos)::integer)), ''[)''::text) WITH &&) WHERE (((estado)::text = ANY ((ARRAY[''programada''::character varying, ''confirmada''::character varying, ''presente''::character varying, ''atendida''::character varying, ''inasistencia''::character varying])::text[])))';
        tipo_historial := 'CHECK (((tipo)::text = ANY ((ARRAY[''reprogramacion''::character varying, ''cancelacion''::character varying, ''confirmacion''::character varying, ''llegada''::character varying, ''atencion''::character varying, ''inasistencia''::character varying])::text[])))';
        evento_historial := 'CHECK (((((tipo)::text = ''reprogramacion''::text) AND (inicio_anterior <> inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''programada''::text)) OR (((tipo)::text = ''cancelacion''::text) AND (inicio_anterior = inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''cancelada''::text)) OR (((tipo)::text = ''confirmacion''::text) AND (inicio_anterior = inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''confirmada''::text)) OR (((tipo)::text = ''llegada''::text) AND (inicio_anterior = inicio_nuevo) AND ((estado_anterior)::text = ANY ((ARRAY[''programada''::character varying, ''confirmada''::character varying])::text[])) AND ((estado_nuevo)::text = ''presente''::text)) OR (((tipo)::text = ''atencion''::text) AND (inicio_anterior = inicio_nuevo) AND ((estado_anterior)::text = ''presente''::text) AND ((estado_nuevo)::text = ''atendida''::text)) OR (((tipo)::text = ''inasistencia''::text) AND (inicio_anterior = inicio_nuevo) AND ((estado_anterior)::text = ANY ((ARRAY[''programada''::character varying, ''confirmada''::character varying])::text[])) AND ((estado_nuevo)::text = ''inasistencia''::text))))';
        indices_esperados := indices_esperados || '{"citas_historial_asistencia_unica_idx":["cita_id","tipo"]}'::jsonb;
        IF esquema->'restricciones'->'citas_historial.citas_historial_asistencia_tiempo_valido'->>'tipo' IS DISTINCT FROM 'c'
           OR esquema->'restricciones'->'citas_historial.citas_historial_asistencia_tiempo_valido'->>'valida' IS DISTINCT FROM 'true'
           OR esquema->'restricciones'->'citas_historial.citas_historial_asistencia_tiempo_valido'->>'definicion' IS DISTINCT FROM
            'CHECK (((((tipo)::text <> ''atencion''::text) OR (cambiado_en >= inicio_nuevo)) AND (((tipo)::text <> ''inasistencia''::text) OR (cambiado_en >= (inicio_nuevo + ''00:30:00''::interval)))))' THEN
            RAISE EXCEPTION 'Esquema incompatible: limites temporales de asistencia';
        END IF;
    ELSIF esquema->'restricciones' ? 'citas_historial.citas_historial_asistencia_tiempo_valido' THEN
        RAISE EXCEPTION 'Esquema incompatible: instalacion parcial de 008';
    END IF;
    IF NOT ((esquema->'tablas') @> tablas_esperadas AND tablas_esperadas @> (esquema->'tablas'))
       OR jsonb_array_length(esquema->'tablas') <> (CASE WHEN ciclo2 THEN 9 ELSE 8 END) THEN
        RAISE EXCEPTION 'Esquema incompatible: se esperan las ocho tablas originales y solo el historial cuando existe 005';
    END IF;
    IF (SELECT count(*) FROM jsonb_object_keys(esquema->'columnas')) <>
       (SELECT count(*) FROM jsonb_object_keys(columnas_esperadas)) THEN
        RAISE EXCEPTION 'Esquema incompatible: cantidad de columnas';
    END IF;
    FOR campo IN SELECT key,value FROM jsonb_each_text(columnas_esperadas) LOOP
        IF esquema->'columnas'->campo.key->>'tipo' IS DISTINCT FROM campo.value THEN
            RAISE EXCEPTION 'Esquema incompatible: columna ausente o tipo distinto del esperado';
        END IF;
        IF (esquema->'columnas'->campo.key->>'obligatoria')::boolean IS DISTINCT FROM (NOT (columnas_opcionales ? campo.key)) THEN
            RAISE EXCEPTION 'Esquema incompatible: nulabilidad distinta de la definida por las migraciones';
        END IF;
    END LOOP;
    IF jsonb_array_length(esquema->'referencias') <> (CASE WHEN ciclo2 THEN 12 ELSE 10 END) THEN
        RAISE EXCEPTION 'Esquema incompatible: se esperan diez o doce claves foraneas segun la presencia del historial';
    END IF;
    FOR referencia IN SELECT key,value FROM jsonb_each(referencias_esperadas) LOOP
        IF (SELECT count(*) FROM jsonb_array_elements(esquema->'referencias') f
            WHERE f->>'origen'=referencia.key AND f->>'destino'=referencia.value->>0
            AND f->>'borrado'=referencia.value->>1 AND f->>'actualizacion'='a'
            AND f->>'valida'='true' AND f->>'diferible'='false') <> 1 THEN
            RAISE EXCEPTION 'Esquema incompatible: referencia, accion o validez de clave foranea';
        END IF;
    END LOOP;
    IF NOT COALESCE((esquema->>'btree_gist')::boolean,false)
       OR NOT COALESCE((esquema->>'trigger_citas')::boolean,false)
       OR esquema->'restricciones'->'citas.citas_medico_sin_superposicion'->>'tipo' IS DISTINCT FROM 'x'
       OR esquema->'restricciones'->'citas.citas_medico_sin_superposicion'->>'valida' IS DISTINCT FROM 'true'
       OR esquema->'restricciones'->'citas.citas_medico_sin_superposicion'->>'definicion' IS DISTINCT FROM exclusion_citas THEN
        RAISE EXCEPTION 'Esquema incompatible: falta integridad GiST, extension o trigger activo de citas';
    END IF;
    IF esquema->>'roles' IS DISTINCT FROM '4' OR esquema->>'especialidades' IS DISTINCT FROM '3' THEN
        RAISE EXCEPTION 'Esquema incompatible: faltan catalogos iniciales';
    END IF;
    IF esquema->'restricciones'->'citas.citas_estado_valido'->>'valida' IS DISTINCT FROM 'true'
       OR esquema->'restricciones'->'citas.citas_estado_valido'->>'definicion' IS DISTINCT FROM estados_citas
       OR esquema->'restricciones'->'citas.citas_duracion_ciclo1'->>'valida' IS DISTINCT FROM 'true'
       OR esquema->'restricciones'->'citas.citas_duracion_ciclo1'->>'definicion' IS DISTINCT FROM 'CHECK ((duracion_minutos = 30))' THEN
        RAISE EXCEPTION 'Esquema incompatible: estados o duracion de citas';
    END IF;
    IF ciclo2 THEN
        IF esquema->'columnas'->'citas_historial.id'->>'identidad' IS DISTINCT FROM 'a'
           OR esquema->'columnas'->'citas_historial.cambiado_en'->>'defecto' IS DISTINCT FROM 'clock_timestamp()'
           OR esquema->'restricciones'->'citas_historial.citas_historial_pkey'->>'tipo' IS DISTINCT FROM 'p'
           OR esquema->'restricciones'->'citas_historial.citas_historial_pkey'->>'definicion' IS DISTINCT FROM 'PRIMARY KEY (id)'
           OR esquema->'restricciones'->'citas_historial.citas_historial_tipo_valido'->>'tipo' IS DISTINCT FROM 'c'
           OR esquema->'restricciones'->'citas_historial.citas_historial_tipo_valido'->>'valida' IS DISTINCT FROM 'true'
           OR esquema->'restricciones'->'citas_historial.citas_historial_tipo_valido'->>'definicion' IS DISTINCT FROM tipo_historial
           OR esquema->'restricciones'->'citas_historial.citas_historial_reprogramacion_valida'->>'tipo' IS DISTINCT FROM 'c'
           OR esquema->'restricciones'->'citas_historial.citas_historial_reprogramacion_valida'->>'valida' IS DISTINCT FROM 'true'
           OR esquema->'restricciones'->'citas_historial.citas_historial_reprogramacion_valida'->>'definicion' IS DISTINCT FROM evento_historial THEN
            RAISE EXCEPTION 'Esquema incompatible: identidad, PK, fecha o restricciones de historial';
        END IF;
        IF (SELECT count(*) FROM jsonb_object_keys(esquema->'indices_historial')) <> (CASE WHEN asistencias THEN 3 ELSE 2 END) THEN
            RAISE EXCEPTION 'Esquema incompatible: indices de historial segun la version';
        END IF;
        FOR campo IN SELECT key,value FROM jsonb_each(indices_esperados) LOOP
            indice := esquema->'indices_historial'->campo.key;
            IF indice IS NULL OR indice->>'metodo' IS DISTINCT FROM 'btree' OR indice->>'valido' IS DISTINCT FROM 'true' OR indice->>'listo' IS DISTINCT FROM 'true'
               OR indice->'columnas' IS DISTINCT FROM campo.value
               OR (indice->>'atributos')::int IS DISTINCT FROM jsonb_array_length(campo.value)
               OR (indice->>'claves')::int IS DISTINCT FROM jsonb_array_length(campo.value)
               OR indice->>'predicado' IS DISTINCT FROM (CASE WHEN campo.key='citas_historial_asistencia_unica_idx' THEN predicado_asistencia ELSE NULL END)
               OR indice->>'definicion' IS DISTINCT FROM (CASE campo.key
                  WHEN 'citas_historial_pkey' THEN 'CREATE UNIQUE INDEX citas_historial_pkey ON public.citas_historial USING btree (id)'
                  WHEN 'citas_historial_asistencia_unica_idx' THEN 'CREATE UNIQUE INDEX citas_historial_asistencia_unica_idx ON public.citas_historial USING btree (cita_id, tipo) WHERE ' || predicado_asistencia
                  ELSE 'CREATE INDEX citas_historial_cita_fecha_idx ON public.citas_historial USING btree (cita_id, cambiado_en, id)' END)
               OR (indice->>'unico')::boolean IS DISTINCT FROM (campo.key IN ('citas_historial_pkey','citas_historial_asistencia_unica_idx')) THEN
                RAISE EXCEPTION 'Esquema incompatible: definicion o validez de indices de historial';
            END IF;
        END LOOP;
    ELSE
        IF esquema->'indices_historial'<>'{}'::jsonb THEN
            RAISE EXCEPTION 'Esquema incompatible: instalacion parcial de historial';
        END IF;
    END IF;
END;
$$;
-- Resultados administrativos sin datos personales ni credenciales.
SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename;
SELECT conrelid::regclass AS tabla,conname,confrelid::regclass AS referencia
FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f' ORDER BY conname;
SELECT extname,extversion FROM pg_extension WHERE extname='btree_gist';
SELECT count(*) AS cuentas_existentes FROM public.usuarios;
