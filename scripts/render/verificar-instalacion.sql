DO $$
BEGIN
    IF (SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
        AND table_name IN ('roles','usuarios','pacientes','medicos','especialidades','medicos_especialidades','horarios_atencion','citas')) <> 8 THEN
        RAISE EXCEPTION 'Faltan tablas del Ciclo 1';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'btree_gist')
       OR NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'public.citas'::regclass AND conname = 'citas_medico_sin_superposicion' AND contype = 'x')
       OR NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = 'public.citas'::regclass AND tgname = 'citas_validar_reserva' AND NOT tgisinternal) THEN
        RAISE EXCEPTION 'Falta la integridad transaccional de citas';
    END IF;
    IF (SELECT count(*) FROM pg_constraint WHERE connamespace = 'public'::regnamespace AND contype = 'f') <> 10 THEN
        RAISE EXCEPTION 'No coinciden las diez claves foráneas esperadas';
    END IF;
    IF (SELECT count(*) FROM information_schema.columns WHERE table_schema = 'public'
        AND ((table_name = 'pacientes' AND column_name IN ('sexo','direccion'))
          OR (table_name = 'medicos' AND column_name = 'correo_contacto')
          OR (table_name = 'citas' AND column_name = 'especialidad_id'))) <> 4 THEN
        RAISE EXCEPTION 'Faltan columnas de las migraciones 002–004';
    END IF;
    IF (SELECT count(*) FROM public.roles WHERE nombre IN ('administrador','recepcionista','medico','paciente')) <> 4
       OR (SELECT count(*) FROM public.especialidades WHERE nombre IN ('Medicina general','Pediatría','Cardiología')) <> 3 THEN
        RAISE EXCEPTION 'Faltan catálogos iniciales';
    END IF;
END;
$$;
SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;
SELECT conrelid::regclass AS tabla, conname, confrelid::regclass AS referencia
FROM pg_constraint WHERE connamespace = 'public'::regnamespace AND contype = 'f' ORDER BY conname;
SELECT extname, extversion FROM pg_extension WHERE extname = 'btree_gist';
SELECT count(*) AS cuentas_existentes FROM public.usuarios;
