-- La transacción única serializa este instalador y no permite repetir seed/migraciones.
SELECT pg_advisory_xact_lock(hashtext('clinica-render-inicializacion'));
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
               WHERE n.nspname !~ '^pg_' AND n.nspname <> 'information_schema'
                 AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')) THEN
        RAISE EXCEPTION 'La base no está vacía: no ejecutar migraciones ni seed nuevamente';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_available_extensions WHERE name = 'btree_gist') THEN
        RAISE EXCEPTION 'btree_gist no está disponible';
    END IF;
END;
$$;
