-- psql recibe los identificadores públicos por -v; nunca la contraseña.
SELECT set_config('clinica.base_esperada', :'base_esperada', true);
SELECT set_config('clinica.usuario_esperado', :'usuario_esperado', true);
DO $$
BEGIN
    IF current_database() <> current_setting('clinica.base_esperada')
       OR current_database() !~ '^clinica_vida_salud(_[a-z0-9]+)*$'
       OR current_database() = 'clinica_vida_salud_dev'
       OR current_user <> current_setting('clinica.usuario_esperado') THEN
        RAISE EXCEPTION 'Destino no autorizado: se cancela la operación';
    END IF;
    IF current_setting('server_version_num')::int NOT BETWEEN 170000 AND 179999 THEN
        RAISE EXCEPTION 'Esta instalación requiere PostgreSQL 17';
    END IF;
    IF NOT COALESCE((SELECT ssl FROM pg_stat_ssl WHERE pid = pg_backend_pid()), false) THEN
        RAISE EXCEPTION 'Se requiere una conexión TLS';
    END IF;
END;
$$;
SELECT current_database() AS base, current_user AS usuario,
       current_setting('server_version') AS version,
       (SELECT ssl FROM pg_stat_ssl WHERE pid = pg_backend_pid()) AS tls;
SELECT name, default_version, installed_version FROM pg_available_extensions WHERE name = 'btree_gist';
SELECT count(*) AS relaciones_de_usuario FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname !~ '^pg_' AND n.nspname <> 'information_schema' AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f');
