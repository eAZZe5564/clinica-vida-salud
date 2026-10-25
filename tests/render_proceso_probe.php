<?php
declare(strict_types=1);
// Solo datos ficticios del test PowerShell; no conecta a PostgreSQL.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$linea = rtrim((string)fgets(STDIN), "\r\n");
if ($linea === 'fallar') { fwrite(STDERR, 'No reflejar: SoloFixture!91'); exit(2); }
if (getenv('PGHOST') !== 'dpg-fixture.oregon-postgres.render.com'
    || getenv('PGDATABASE') !== 'clinica_vida_salud_render' || getenv('PGSSLMODE') !== 'verify-full'
    || getenv('PGPASSWORD') !== 'SoloFixture!91' || getenv('PGHOSTADDR') !== false
    || getenv('PGSERVICE') !== false || $linea !== 'Árbol١!xx') { exit(3); }
echo 'OK: entorno aislado y UTF-8';
