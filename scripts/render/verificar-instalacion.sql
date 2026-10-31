-- Esquema 001-004 (8/10), 001-005 (9/12) o 001-006 (9/12 con cancelaciones).
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
    tipo_historial text := 'CHECK (((tipo)::text = ''reprogramacion''::text))';
    evento_historial text := 'CHECK (((inicio_anterior <> inicio_nuevo) AND ((estado_anterior)::text = ''programada''::text) AND ((estado_nuevo)::text = ''programada''::text)))';
BEGIN
    -- CU15_METADATA_INICIO: unico punto de lectura, sustituido solo por fixtures aislados en tests.
    SELECT jsonb_build_object(
      'tablas', COALESCE((SELECT jsonb_agg(c.relname ORDER BY c.relname) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relkind IN ('r','p')),'[]'::jsonb),
      'historial_tipo', (SELECT c.relkind::text FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname='citas_historial'),
      'columnas', COALESCE((SELECT jsonb_object_agg(c.relname||'.'||a.attname,jsonb_build_object('tipo',format_type(a.atttypid,a.atttypmod),'obligatoria',a.attnotnull,'identidad',a.attidentity::text,'defecto',pg_get_expr(d.adbin,d.adrelid)))
        FROM pg_attribute a JOIN pg_class c ON c.oid=a.attrelid JOIN pg_namespace n ON n.oid=c.relnamespace LEFT JOIN pg_attrdef d ON d.adrelid=a.attrelid AND d.adnum=a.attnum
        WHERE n.nspname='public' AND c.relkind IN ('r','p') AND a.attnum>0 AND NOT a.attisdropped),'{}'::jsonb),
      'referencias', COALESCE((SELECT jsonb_agg(jsonb_build_object(
        'origen',c.relname||'.'||(SELECT string_agg(a.attname,',' ORDER BY k.orden) FROM unnest(f.conkey) WITH ORDINALITY k(numero,orden) JOIN pg_attribute a ON a.attrelid=c.oid AND a.attnum=k.numero),
        'destino',rn.nspname||'.'||rc.relname||'.'||(SELECT string_agg(a.attname,',' ORDER BY k.orden) FROM unnest(f.confkey) WITH ORDINALITY k(numero,orden) JOIN pg_attribute a ON a.attrelid=rc.oid AND a.attnum=k.numero),
        'borrado',f.confdeltype::text,'actualizacion',f.confupdtype::text,'valida',f.convalidated,'diferible',f.condeferrable))
        FROM pg_constraint f JOIN pg_class c ON c.oid=f.conrelid JOIN pg_namespace n ON n.oid=f.connamespace JOIN pg_class rc ON rc.oid=f.confrelid JOIN pg_namespace rn ON rn.oid=rc.relnamespace WHERE n.nspname='public' AND f.contype='f'),'[]'::jsonb),
      'indices_historial', COALESCE((SELECT jsonb_object_agg(ic.relname,jsonb_build_object(
        'metodo',am.amname,'unico',i.indisunique,'valido',i.indisvalid,'listo',i.indisready,'definicion',pg_get_indexdef(i.indexrelid),
        'atributos',i.indnatts,'claves',i.indnkeyatts,'predicado',pg_get_expr(i.indpred,i.indrelid),
        'columnas',(SELECT jsonb_agg(a.attname ORDER BY k.orden) FROM unnest(i.indkey) WITH ORDINALITY k(numero,orden) LEFT JOIN pg_attribute a ON a.attrelid=t.oid AND a.attnum=k.numero)))
        FROM pg_index i JOIN pg_class t ON t.oid=i.indrelid JOIN pg_namespace n ON n.oid=t.relnamespace JOIN pg_class ic ON ic.oid=i.indexrelid JOIN pg_am am ON am.oid=ic.relam WHERE n.nspname='public' AND t.relname='citas_historial'),'{}'::jsonb),
      'restricciones', COALESCE((SELECT jsonb_object_agg(c.relname||'.'||f.conname,jsonb_build_object('tipo',f.contype::text,'valida',f.convalidated,'definicion',pg_get_constraintdef(f.oid)))
        FROM pg_constraint f JOIN pg_class c ON c.oid=f.conrelid JOIN pg_namespace n ON n.oid=f.connamespace WHERE n.nspname='public' AND c.relname IN ('citas','citas_historial')),'{}'::jsonb),
      'btree_gist', EXISTS(SELECT 1 FROM pg_extension WHERE extname='btree_gist'),
      'trigger_citas', EXISTS(SELECT 1 FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_proc p ON p.oid=t.tgfoid JOIN pg_namespace pn ON pn.oid=p.pronamespace WHERE n.nspname='public' AND c.relname='citas' AND t.tgname='citas_validar_reserva' AND NOT t.tgisinternal AND t.tgenabled IN ('O','A') AND t.tgtype=23 AND pn.nspname='public' AND p.proname='validar_cita_ciclo1'),
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
       OR esquema->'restricciones'->'citas.citas_medico_sin_superposicion'->>'definicion' IS DISTINCT FROM
          'EXCLUDE USING gist (medico_id WITH =, tsrange((fecha_hora_inicio AT TIME ZONE ''UTC''::text), ((fecha_hora_inicio AT TIME ZONE ''UTC''::text) + make_interval(mins => (duracion_minutos)::integer)), ''[)''::text) WITH &&) WHERE (((estado)::text = ''programada''::text))' THEN
        RAISE EXCEPTION 'Esquema incompatible: falta integridad GiST, extension o trigger activo de citas';
    END IF;
    IF esquema->>'roles' IS DISTINCT FROM '4' OR esquema->>'especialidades' IS DISTINCT FROM '3' THEN
        RAISE EXCEPTION 'Esquema incompatible: faltan catalogos iniciales';
    END IF;
    IF esquema->'restricciones'->'citas.citas_estado_valido'->>'valida' IS DISTINCT FROM 'true'
       OR esquema->'restricciones'->'citas.citas_estado_valido'->>'definicion' IS DISTINCT FROM
        'CHECK (((estado)::text = ANY ((ARRAY[''programada''::character varying, ''cancelada''::character varying, ''atendida''::character varying])::text[])))'
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
        IF (SELECT count(*) FROM jsonb_object_keys(esquema->'indices_historial')) <> 2 THEN
            RAISE EXCEPTION 'Esquema incompatible: se esperan los dos indices de historial';
        END IF;
        FOR campo IN SELECT key,value FROM jsonb_each('{"citas_historial_pkey":["id"],"citas_historial_cita_fecha_idx":["cita_id","cambiado_en","id"]}'::jsonb) LOOP
            indice := esquema->'indices_historial'->campo.key;
            IF indice IS NULL OR indice->>'metodo' IS DISTINCT FROM 'btree' OR indice->>'valido' IS DISTINCT FROM 'true' OR indice->>'listo' IS DISTINCT FROM 'true'
               OR indice->'columnas' IS DISTINCT FROM campo.value
               OR (indice->>'atributos')::int IS DISTINCT FROM jsonb_array_length(campo.value)
               OR (indice->>'claves')::int IS DISTINCT FROM jsonb_array_length(campo.value)
               OR indice->>'predicado' IS NOT NULL
               OR indice->>'definicion' IS DISTINCT FROM (CASE WHEN campo.key='citas_historial_pkey'
                  THEN 'CREATE UNIQUE INDEX citas_historial_pkey ON public.citas_historial USING btree (id)'
                  ELSE 'CREATE INDEX citas_historial_cita_fecha_idx ON public.citas_historial USING btree (cita_id, cambiado_en, id)' END)
               OR (indice->>'unico')::boolean IS DISTINCT FROM (campo.key='citas_historial_pkey') THEN
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
