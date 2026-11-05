# CU22 — Pruebas e inventario

Desarrollo local desde CU21 `3415743bf7b9a1f8edf3b5e791520dbfec4df586`.
Rama `feat/ciclo2-cu22-exportar-reportes`. Dompdf/Composer/Docker autorizados
expresamente por el usuario; no hay autorización implícita de commit o despliegue.

## Alcance comprobado

Las dos rutas GET de PDF/CSV reutilizan exactamente ReporteCitaService, sin
modificar servicio, repositorio, fórmulas, filtros o esquema CU21. Solo
administrador/recepcionista; médico/paciente 403 y sin sesión login. Otros
métodos 405; errores de filtros 422 sin attachment ni archivo parcial.

CSV: BOM UTF-8, punto y coma, CRLF, comillas/saltos internos, metadatos, secciones,
nombres conservados, conteos numéricos, períodos vacíos y fórmulas neutralizadas.
Se probaron =/+/-/@, variantes de ancho completo, espacios Unicode, TAB/CR/LF,
NUL/DEL y caracteres invisibles iniciales. Los números legítimos no cambian.

PDF: Dompdf real A4 horizontal, nueve páginas con 130 días, acentos/ñ/Ñ, nombres
largos, texto escapado, encabezados repetidos, numeración y vacío válido.
Pypdf 6.14.2 preinstalado abre los binarios en modo estricto; compara los siete
conteos del resumen PDF HTTP con los valores del servicio CU21.
No se agregó Python a la aplicación ni al contenedor.

Edge real: cuatro descargas (PDF/CSV, escritorio 1440 y celular 390), nombres
seguros, formatos binarios correctos, filtros idénticos a los cinco controles,
pantalla conservada tras descargar, botones táctiles y sin desbordamiento.
El PDF descargado se abrió y renderizó en el lector integrado de Edge; se
inspeccionó visualmente su captura, además de las pantallas del módulo.

## Comandos y campaña actual

```powershell
php tests/regresion_cu22.php
$env:PYTHONIOENCODING='utf-8'
python tests/leer_pdf_cu22.py
powershell -NoProfile -File tests/render_preparacion.ps1
php scripts/verificar-cu15-local.php
php scripts/inspeccionar-cu18-local.php
node --check tests/citas_browser.mjs
```

El runner ejecutó ahora la campaña CU21 completa (incluidas CU01–CU20) y las
suites nuevas. No se copiaron aprobaciones históricas ni se suman intentos repetidos.

| Suite nueva ejecutada en la campaña final | Aprobadas |
|---|---:|
| exportacion_csv_validation | 60 |
| exportacion_pdf_validation | 10 |
| http_exportacion_csv | 63 |
| http_exportacion_pdf | 48 |
| exportaciones_integration | 16 |
| exportaciones_browser | 25 |
| exportaciones_docker_validation (estática) | 23 |
| leer_pdf_cu22.py, pypdf real | 51 |
| **Nuevas CU22** | **296** |

| Regresión ejecutada ahora | Aprobadas |
|---|---:|
| CU01–CU14 y mejoras 6A (26 suites) | 1003 |
| CU15: integración/concurrencia/HTTP/Edge | 121 |
| CU16: integración/HTTP/concurrencia/Edge | 183 |
| CU17: integración/HTTP/concurrencia/Edge | 211 |
| CU18: integración/HTTP/concurrencia/Edge | 310 |
| CU19: integración/HTTP/Edge | 199 |
| CU20: integración/HTTP/Edge | 212 |
| CU21: integración 138, HTTP 90, Edge 16 | 244 |
| **Regresión funcional CU01–CU21** | **2483** |
| Esquema READ ONLY | 93 |
| Herramientas locales PHP (3 + 24) | 27 |
| Herramienta PowerShell, sin red | 34 |
| **Regresión y herramientas** | **2637** |
| Nuevas CU22 | 296 |
| **Total final ejecutado ahora** | **2933** |

El runner PHP completo informa 2848; el lector Python añade 51 y PowerShell 34.
Todas las suites finales terminaron con código cero. Se comprobaron 221 archivos
PHP, JavaScript y sintaxis Python, fuera del conteo. Las dos comprobaciones CU21
que antes exigían ausencia de exportaciones ahora admiten las acciones CU22;
mantienen sus conteos y las mismas verificaciones de agregados/privacidad.

El lector PDF se ejecutó tras regenerar los casos HTTP. Durante el
desarrollo se corrigió la serialización del orden esperado de columnas en esa
prueba, para compararlo con los encabezados reales del PDF; no se modificaron
cálculos de CU21. Los intentos fallidos no son aprobaciones ni se suman.

## Preservación y destino

Solo clinica_vida_salud_dev, PostgreSQL 17 local, con guardas de Cu15Local.
Se reutiliza ReportesPrueba y los auxiliares anteriores con IDs ficticios propios.
Para el límite se insertan 2001 citas canceladas de legado con el médico fixture:
el PDF supera 500 grupos y se rechaza con mensaje; CSV conserva el período
completo. La limpieza usa los IDs rastreados, sin tocar registros preexistentes.

Las exportaciones se ejecutan en READ ONLY con PDO vigilado: tres SELECT de CU21
y ninguna consulta adicional al serializar. HTTP compara las nueve tablas tras
los logins; Edge compara las ocho de dominio porque login registra último acceso.
La huella inicial de las nueve tablas y metadatos coincidió exactamente al cierre.
Las secuencias pueden avanzar por fixtures. No se desactivan restricciones,
triggers, sesiones, autenticación o CSRF. No se crean eventos por descargar.

Los PDF/capturas ficticios de revisión quedan ignorados en tests/artifacts/cu22,
fuera de public. Los temporales del generador y las descargas GUID se retiran.
No se genera ningún descargable permanente público. Los logs no se publican.
Los verificadores finales READ ONLY confirmaron esquema 001–008, nueve tablas,
doce claves foráneas, cero conflictos de intervalos retenidos y cero duplicados
de eventos CU18. No se modificaron migraciones, índices, restricciones o trigger.

## Dependencias, compatibilidad y límites

Dompdf 3.1.6; font-lib 1.0.2, svg-lib 1.0.2, html5 2.12.0 y css-parser 9.5.0
fijados en composer.lock. Composer 2.11.1 oficial local con SHA256 verificado.
Install desde lock y check-platform-reqs correctos (PHP 8.4.22, DOM, MBString,
iconv); auditoría del lock sin avisos de vulnerabilidad conocidos.

Composer validate --strict: correcto sin advertencias tras los ajustes autorizados
de publicación. Se comprobó ausencia de LICENSE/declaraciones contradictorias:
el usuario indicó proprietary para el proyecto, sin crear LICENSE ni autores.
La restricción es ^3.1.6 y el lock conserva 3.1.6. Update --lock --no-install
cambió solo content-hash: las cinco versiones y referencias de paquetes no cambian.
Vendor y la herramienta/caché local no se versionan; sin plugins, scripts o
transporte inseguro habilitados.

Docker no está instalado: la comprobación es estática. Conserva PHP/Apache,
PostgreSQL, sesiones y arranque; incorpora Composer/lock/unzip y aserciones de
extensiones en build. No se declara un build real o despliegue probado.
La etiqueta oficial composer:2.11.1 se comprobó en el registro para Linux amd64;
esa comprobación no equivale a ejecutar un contenedor.
Microsoft Excel tampoco está instalado: BOM/separador y round-trip probados,
sin afirmar una ejecución real en Excel. Fuentes, seguridad y límites: [CU22.md](CU22.md).

El límite PDF es de 500 filas agregadas (médicos + especialidades + días);
no se truncan resultados ni se cambia el período de CU21. Para conjuntos mayores
se ofrece CSV o reducir filtros. Entre pantalla y descarga puede cambiar el
estado por otro usuario: se mantienen filtros, no un snapshot entre solicitudes.

## Inventario exclusivo de CU22

## Comprobaciones para publicación tras corregir Composer

Esta revisión es posterior a la campaña de 2933 comprobaciones anterior; no se
repitió esa campaña completa. Se ejecutaron ahora:

| Comprobación actual | Resultado |
|---|---|
| Composer validate --strict, audit y check-platform-reqs | Correctos, sin advertencias de validación ni avisos de vulnerabilidad |
| Update --lock --no-install | Solo cambió content-hash; cinco versiones y paquetes idénticos |
| Install desde lock en vendor temporal vacío | Cinco instalaciones, cero actualizaciones; mismas versiones y plataforma válida |
| CSV: formato y HTTP real | 60 + 63 aprobadas |
| PDF: generación y HTTP real | 10 + 48 aprobadas |
| PDF/CSV comparten resultado CU21 en READ ONLY | 16 aprobadas |
| Lector PDF real, Unicode, páginas y cifras HTTP | 51 aprobadas |
| Docker/Composer estático ajustado a la licencia/restricción autorizadas | 23 aprobadas |
| Esquema compatible y base local real | 93 aprobadas; nueve tablas/doce FK |
| Sintaxis | 221 PHP, JavaScript y Python correctos |

Son 271 comprobaciones focalizadas de exportaciones/lector/Docker estático y 93
de esquema: **364 actuales**, sin sumar otra vez las 2933 anteriores. Las tablas
y los metadatos locales conservaron su huella inicial tras retirar fixtures.
Docker continúa sin estar instalado: su construcción/ejecución real sigue pendiente.
El directorio de instalación aislada se conserva en tmp ignorado, fuera de public;
no forma parte del inventario ni cambia vendor instalado o las versiones bloqueadas.

## Inventario exclusivo de CU22 para el commit

**30 archivos: 18 nuevos y 12 modificados.**
Los cinco archivos pendientes anteriores están separados y excluidos.

| Estado | Archivo |
|---|---|
| Modificado | `.dockerignore` |
| Modificado | `Dockerfile` |
| Modificado | `README.md` |
| Modificado | `app/Controllers/ReportesCitasController.php` |
| Modificado | `app/Views/reportes/citas.php` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Modificado | `docs/ciclo2/README.md` |
| Modificado | `public/assets/css/reportes.css` |
| Modificado | `public/index.php` |
| Modificado | `tests/README.md` |
| Modificado | `tests/citas_browser.mjs` |
| Modificado | `tests/http_reportes.php` |
| Nuevo | `app/Exports/ReporteCitaCsv.php` |
| Nuevo | `app/Exports/ReporteCitaDocumento.php` |
| Nuevo | `app/Exports/ReporteCitaPdf.php` |
| Nuevo | `app/Exports/ReportePdfLimiteException.php` |
| Nuevo | `app/Views/reportes/pdf.php` |
| Nuevo | `composer.json` |
| Nuevo | `composer.lock` |
| Nuevo | `docs/ciclo2/CU22.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU22.md` |
| Nuevo | `tests/exportacion_csv_validation.php` |
| Nuevo | `tests/exportacion_pdf_validation.php` |
| Nuevo | `tests/exportaciones_browser.php` |
| Nuevo | `tests/exportaciones_docker_validation.php` |
| Nuevo | `tests/exportaciones_integration.php` |
| Nuevo | `tests/http_exportacion_csv.php` |
| Nuevo | `tests/http_exportacion_pdf.php` |
| Nuevo | `tests/leer_pdf_cu22.py` |
| Nuevo | `tests/regresion_cu22.php` |

Excluidos y protegidos: app/Config/Database.php, app/Config/DatabaseDiagnostic.php,
scripts/render-postgres.ps1, tests/render_preparacion.ps1,
tests/database_render_validation.php y .env. No se modifica main ni Render.
No se ejecutan migraciones, commit, push o PR. No se desarrolla CU23.

Revisión final: inventario contrastado con Git (30 archivos, 18 nuevos/12
modificados), índice vacío y HEAD aún en CU21. Los cinco archivos previos, .env
y el servicio/repositorio CU21 conservan sus SHA256 iniciales exactos, sin
publicar huellas privadas. No se detectaron coincidencias de secretos de .env,
claves privadas, tokens GitHub o URL PostgreSQL con credenciales en el inventario.
Formato y git diff --check correctos. Vendor, caché/Composer local, sesiones,
logs y artefactos permanecen ignorados. Main conserva su referencia inicial.
