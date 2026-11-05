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
);
