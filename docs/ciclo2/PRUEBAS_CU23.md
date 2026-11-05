# CU23 — Resultados e inventario

Implementación local de perfil clínico básico y alergias, en
`feat/ciclo2-cu23-perfil-clinico-alergias`, desde CU22
`58ab18767a3dc128c03b0fa6c5bf8695318e2cb4`.
Migración 009 autorizada y aplicada una sola vez en clinica_vida_salud_dev.
Sin commit, push, merge, PR, Render o CU24.

## Ejecuciones actuales — 04/11/2026

Se ejecutó de nuevo la regresión CU01–CU22 y después la campaña específica CU23,
en secuencia para no interferir con huellas y fixtures. Los resultados de esta
tabla proceden de esta ejecución, no de campañas históricas.

| Suite CU23 | Aprobadas |
|---|---:|
| perfil_clinico_validation | 18 |
| esquema_cu23_validation, READ ONLY | 164 |
| perfil_clinico_integridad | 36 |
| perfil_clinico_integration | 82 |
| perfil_clinico_concurrency | 15 |
| http_perfil_clinico | 139 |
| perfil_clinico_privacidad | 8 |
| perfil_clinico_browser, Edge real | 42 |
| **Nuevas CU23** | **504** |

| Regresión actual | Aprobadas |
|---|---:|
| regresion_cu22.php, campaña PHP completa CU01–CU22 | 2848 |
| leer_pdf_cu22.py, lectura real de PDF | 51 |
| render_preparacion.ps1, sin red | 34 |
| **Regresión CU01–CU22 y herramientas** | **2933** |
| Nuevas CU23 | 504 |
| **Total actual, sin duplicar intentos** | **3437** |

La campaña PHP CU22 incluye regresion_cu21 (2603) y sus siete suites adicionales
(245). Dentro de CU21 están las 93 comprobaciones del contrato administrativo,
que siguen cubriendo versiones anteriores aisladas en memoria.
Las 164 del contrato clínico CU23 son adicionales.

También se comprobaron 244 PHP de app/scripts/tests y public/index.php (245 PHP),
cinco JavaScript y los inspectores finales, fuera del conteo anterior.
Las 364 de publicación CU22 y los informes de ciclos anteriores son históricos,
no se suman de nuevo.

## Comandos ejecutados

```powershell
# Comprobación previa a 009, antes de su aplicación (ya no corresponde repetirla):
php scripts/inspeccionar-cu23-local.php
# Una sola aplicación, tras autorización expresa (ya completada; no repetir):
php scripts/aplicar-migracion-009.php --autorizado

# Campañas de esta ejecución:
php tests/regresion_cu22.php
php tests/regresion_cu23.php --solo-cu23
$env:PYTHONIOENCODING='utf-8'
python tests/leer_pdf_cu22.py
powershell -NoProfile -File tests/render_preparacion.ps1

# Verificación final READ ONLY:
php scripts/verificar-cu23-local.php
php scripts/verificar-cu15-local.php
php scripts/inspeccionar-cu18-local.php
```

Para una campaña futura completa puede usarse `php tests/regresion_cu23.php`,
que incluye CU22 y CU23, más los complementos PDF/PowerShell. No hace falta
repetir la migración. El runner también admite el nombre de una sola suite CU23.

## Cobertura comprobada

| Área | Evidencia actual |
|---|---|
| Perfil | Alta, consulta, actualización, perfil ausente, opcionales NULL, grupo/fuente, versión y autor reales |
| Alergias | Varias, unicidad normalizada activa, rectificación, inactivación, reactivación y antecedentes equivalentes |
| Conocimiento | Vacío no confirma ausencia; declaración sin conocidas exige confirmación y ninguna entrada activa |
| Auditoría | Imagen inicial y versiones anteriores conservadas, responsable, motivo y fechas; sin eventos en citas_historial |
| Relación asistencial | Médico vinculado/activo, cuatro estados pertinentes en su día, cuenta/rol revalidados; ayer/mañana, cancelada e inasistencia rechazados |
| Permisos/IDOR | Administrador, recepcionista, paciente, anónimo, médico ajeno/sin vínculo, paciente/alergia ajenos y desbordamientos |
| HTTP | GET sin modificaciones, POST con CSRF/confirmación, PRG, métodos alternativos, 403/404/409/419/422 y cabeceras privadas |
| XSS/validación | Texto escapado, UTF-8, tipos inesperados, longitudes, controles y texto con apariencia de inyección guardado como parámetro |
| PostgreSQL | 12 tablas/20 FK reales; 32 columnas, 30 restricciones y seis índices clínicos; restricciones/índices alterados rechazados en memoria |
| Integridad | FK reales, una PK de perfil por paciente, CHECK, límite varchar, unicidad parcial y borrado restringido de alergias con revisiones |
| Atomicidad | Fallo de revisión simulado sin DDL revierte alta/rectificación y resumen; fallo anidado conserva la transacción del llamador |
| Concurrencia | Procesos PDO independientes y espera comprobada en pg_stat_activity; una alta/edición gana y otra recibe 409; revocación tras espera impide escribir |
| Ventana temporal | Cambio del día simulado por reloj interno de pruebas revierte una operación; no existe reloj manipulable por HTTP |
| Privacidad | CSV/PDF/resultado CU21 no reciben marcadores clínicos; mensajes sin valores/PDO adjunto; logs HTTP reales y sesiones de fixtures sin datos clínicos |
| Interfaz | Edge real 1440, 820 y 390 px: guardar perfil, alta, inactivación, historial, escape, controles táctiles y ausencia de desbordamiento |
| Navegación | Agenda propia ofrece enlace pertinente sin valores clínicos; administrador/recepcionista no ven acceso clínico |
| Regresión | CU01–CU22, reservas/GiST, agenda, historial y exportaciones conservan sus comprobaciones |

No se generaron capturas ni PDFs con contenido clínico. La prueba de privacidad
usa un PDF administrativo en memoria, cuya plantilla no contiene esos valores.
Los eventos de red del navegador de pruebas solo registran en memoria las URL:
se comprobó que las páginas clínicas no solicitan recursos a terceros ni
transportan los valores de formulario en URL.

Los intentos iniciales que detectaron imports de vistas faltantes y la ruta
temporal por defecto de sesiones en Windows se corrigieron y se repitieron en
la campaña final. No se contabilizan intentos fallidos ni se duplican aprobaciones.
Una orden inicial de envoltura PHP en PowerShell no llegó a ejecutar pruebas;
el runner final emplea argumentos estructurados desde PHP.

## Base local, seguridad de la migración y preservación

La inspección previa confirmó PostgreSQL 17.10 en loopback y la base exacta.
009 creó solo tres tablas vacías y ocho FK nuevas. Un índice parcial aplica a
alergias activas, permitiendo conservar inactivas equivalentes. Desconocido no
asigna un grupo ni confirma ausencia de alergias.

El asistente usó una sola transacción y bloqueó las nueve tablas previas. Comparó
filas, columnas, restricciones, índices y triggers antes/después, sin cambios;
solo confirmó después de comprobar 12/20 y tablas clínicas vacías.
No se volvió a aplicar 009 ni se ejecutaron migraciones adicionales.

Los fixtures se retiraron por sus IDs, con revisiones antes que sus padres,
sin desactivar restricciones. Al cierre las nueve tablas previas conservaron
exactamente su huella inicial; las tres clínicas volvieron a estar vacías.
Las secuencias pueden avanzar por los ensayos.
GiST conserva cero conflictos de intervalos y CU18 cero eventos duplicados.

Los cinco archivos pendientes anteriores, .env y las migraciones 001–008
conservan sus SHA256 iniciales. No se publican huellas privadas ni credenciales.
El índice permanece vacío, HEAD sigue en CU22 y main conserva su referencia.
Vendor, sesiones, logs, artefactos, capturas y temporales siguen excluidos.

## Inventario exclusivo de CU23

**43 archivos: 30 nuevos y 13 modificados.**
No se incluyen los cinco archivos pendientes anteriores.

| Estado | Archivo |
|---|---|
| Modificado | `README.md` |
| Modificado | `app/Controllers/AgendaController.php` |
| Modificado | `app/Repositories/AgendaRepository.php` |
| Modificado | `app/Views/agenda/index.php` |
| Modificado | `app/Views/layout.php` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Modificado | `docs/ciclo2/README.md` |
| Modificado | `public/index.php` |
| Modificado | `scripts/render/verificar-instalacion.sql` |
| Modificado | `scripts/verificar-cu15-local.php` |
| Modificado | `tests/HttpCatalogo.php` |
| Modificado | `tests/README.md` |
| Modificado | `tests/esquema_cu15_validation.php` |
| Nuevo | `app/Controllers/PerfilesClinicosController.php` |
| Nuevo | `app/Models/PerfilClinico.php` |
| Nuevo | `app/Repositories/PerfilClinicoRepository.php` |
| Nuevo | `app/Services/PerfilClinicoException.php` |
| Nuevo | `app/Services/PerfilClinicoService.php` |
| Nuevo | `app/Views/clinico/alergias.php` |
| Nuevo | `app/Views/clinico/cabecera.php` |
| Nuevo | `app/Views/clinico/error.php` |
| Nuevo | `app/Views/clinico/historial.php` |
| Nuevo | `app/Views/clinico/perfil.php` |
| Nuevo | `database/migrations/009_perfil_clinico_alergias.sql` |
| Nuevo | `docs/ciclo2/CU23.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU23.md` |
| Nuevo | `public/assets/css/clinico.css` |
| Nuevo | `scripts/Cu23Esquema.php` |
| Nuevo | `scripts/aplicar-migracion-009.php` |
| Nuevo | `scripts/cu23/catalogo.sql` |
| Nuevo | `scripts/inspeccionar-cu23-local.php` |
| Nuevo | `scripts/verificar-cu23-local.php` |
| Nuevo | `tests/PerfilClinicoPrueba.php` |
| Nuevo | `tests/esquema_cu23_validation.php` |
| Nuevo | `tests/http_perfil_clinico.php` |
| Nuevo | `tests/perfil_clinico_browser.mjs` |
| Nuevo | `tests/perfil_clinico_browser.php` |
| Nuevo | `tests/perfil_clinico_concurrency.php` |
| Nuevo | `tests/perfil_clinico_integration.php` |
| Nuevo | `tests/perfil_clinico_integridad.php` |
| Nuevo | `tests/perfil_clinico_privacidad.php` |
| Nuevo | `tests/perfil_clinico_validation.php` |
| Nuevo | `tests/regresion_cu23.php` |

Excluidos y protegidos: app/Config/Database.php, app/Config/DatabaseDiagnostic.php,
scripts/render-postgres.ps1, tests/render_preparacion.ps1,
tests/database_render_validation.php y .env. Las herramientas de prueba locales,
dependencias instaladas y artefactos ignorados no forman parte de este inventario.

## Limitaciones y pasos pendientes

- No hay cuentas Médico vinculadas permanentes en el estado inicial: para probar
  manualmente se necesita una cuenta/vínculo válidos y una cita pertinente del día,
  usando los módulos existentes. Los fixtures no quedan como datos de demostración.
- El perfil conserva creador/último editor; solo las alergias tienen imágenes de
  todas las revisiones. La bitácora no es inmutable frente al propietario SQL.
- Acceso limitado al día de inicio de la cita, incluso si cruza medianoche.
  No se habilita excepción administrativa, portal personal ni historia completa.
- Docker continúa revisado estáticamente; compilación/ejecución real pendiente.
  No se añadieron dependencias, cambios de Docker, configuración o acceso a Render.
- CU23 queda local sin guardar en Git; se requiere una instrucción posterior
  para commit/push. No se implementa CU24.
