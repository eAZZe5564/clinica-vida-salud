# CU24 — Pruebas de antecedentes clínicos y laboratorio

## Estado de implementación y migración

CU24 implementado localmente en `feat/ciclo2-cu24-antecedentes-laboratorio`,
desde CU23 `f3d275c3da6125438f9f577b699fe2c2d5b3bb78`. Sin commit/push de CU24.
010 se aplicó exactamente una vez, después de autorización expresa, mediante
`php scripts/aplicar-migracion-010.php --autorizado` y una sola transacción.
No hubo error ni reintento. PostgreSQL 17.10 local, base clinica_vida_salud_dev.
El esquema real final tiene **16 tablas y 30 FK**. No volver a ejecutar 010.

Las imágenes históricas son instantáneas de campos tipados, no fotografías,
PDF ni archivos adjuntos. No se carga ningún archivo ni se envía información
clínica a servicios externos. Las tablas nuevas se crearon vacías; el asistente
comparó registros/metadatos anteriores antes de confirmar, sin cambios de las
doce tablas, GiST, triggers o migraciones 001–009.

## Comprobaciones anteriores de preparación

Antes de la autorización se ejecutaron 164 pruebas del contrato CU23 y 93 del
administrativo, **257 de esquema en READ ONLY**. Son de la preparación previa;
no se suman de nuevo a la campaña final ni son pruebas funcionales CU24.
Se confirmó 12/20, PK BIGINT, ausencia de objetos 010, cero conflictos GiST y
cero eventos duplicados CU18. La revisión estática contó cuatro tablas, 60
columnas, diez FK RESTRICT, 36 CHECK, cuatro PK, dos UNIQUE y ocho índices.

## Pruebas específicas ejecutadas durante esta implementación

| Suite | Aprobadas | Cobertura |
|---|---:|---|
| registros_clinicos_validation | 44 | Fechas reales/no futuras, desconocidos, decimales exactos, tipos y precisión |
| esquema_cu24_validation | 268 | Contrato real y mutaciones aisladas de tablas, columnas, defaults, CHECK, FK, PK/UNIQUE e índices, todo READ ONLY |
| registros_clinicos_integridad | 50 | FK, no borrado de padres, CHECK, NaN, unidad, fecha/precisión, unicidad histórica y parámetros SQL |
| registros_clinicos_integration | 97 | Altas múltiples, cualitativo/numérico, fechas desconocidas/aproximadas, correcciones, versiones previas, estados, responsables, roles/IDOR, errores y rollback |
| registros_clinicos_concurrency | 14 | Procesos PDO independientes con barreras de bloqueo reales: una corrección exitosa/otra 409 en ambos módulos; revocación durante espera; coordinación CU23/CU24 |
| http_registros_clinicos | 241 | HTTP real, seis cuentas ficticias, métodos, CSRF, XSS, URL manipuladas, PRG, versiones, no caché/CSP, logs y sesiones |
| registros_clinicos_privacidad | 12 | Cifras CU21 sin cambios, CU19/CU20 sin campos clínicos, PDF/CSV reales sin datos CU24, errores seguros y separación de perfiles/alergias |
| registros_clinicos_browser | 67 | Edge real a 1440, 820 y 390 px: altas/correcciones de ambos módulos, versiones anterior/actual, DOM escapado, botones táctiles y sin desbordamiento/capturas/solicitudes externas |
| **Total específico CU24** | **793** | No se duplican los intentos iniciales ni las repeticiones |

Se ejecutó además la campaña completa `php tests/regresion_cu24.php`, con todos
los subprocesos en salida 0: **4.145 comprobaciones PHP aprobadas**. Incluye
3.352 de regresión CU01–CU23 (2.848 CU01–CU22 + 504 específicas CU23), más las
793 de CU24. Se complementó ahora con **51** comprobaciones de lectura real de
PDF mediante pypdf y **34** PowerShell sin red ni conexión PostgreSQL.

**Total de la campaña final ejecutada ahora: 4.230 aprobadas.** La regresión
CU01–CU23 representa 3.437 de ese total y CU24 las otras 793. No se suman otra
vez ejecuciones individuales, los 257 controles de preparación ni los resultados
históricos de CU23. No quedan suites fallidas en los resultados finales.

Sintaxis final correcta: **266 archivos PHP y 6 JavaScript/MJS**. Los cuatro
verificadores de CU24/CU23/CU15/CU18 pasaron en READ ONLY. Confirman 16/30,
contratos completos, cero conflictos de intervalos y cero duplicados CU18.
No se ha compilado ni ejecutado Docker; su comprobación sigue siendo estática.

## Incidencias corregidas durante desarrollo

- La prueba HTTP detectó el import de RegistroClinico ausente en el formulario;
  se añadió y se repitieron las 241 comprobaciones, aprobadas.
- La generación inicial de algunos textos de prueba a través de PowerShell
  alteró acentos; se corrigió UTF-8 y el verificador de cifras CU23 para 16/30.
  Se conservan los conteos estrictos y la comprobación integral de contratos.
- Edge notificó un recurso data: interno con origen null. Se distinguió de una
  conexión de red: cualquier otro origen sigue fallando, y también se comprueba
  que ninguna URL contenga valores clínicos ficticios. Los flujos finales pasaron.
- El runner CU23 necesitó aislar argv al ejecutar el runner CU22 anidado, para
  no interpretar la ruta del script como una suite. Es un ajuste del ejecutor,
  no de reglas funcionales. Los intentos fallidos no suman aprobaciones.

## Reproducción local

Solo con 001–010 ya aplicado y dependencias CU22 instaladas; no aplicar ninguna
migración para ejecutar estas pruebas:

```powershell
php scripts/verificar-cu24-local.php
php scripts/verificar-cu23-local.php
php scripts/verificar-cu15-local.php
php scripts/inspeccionar-cu18-local.php
php tests/regresion_cu24.php --solo-cu24
php tests/regresion_cu24.php
python tests/leer_pdf_cu22.py
powershell -NoProfile -File tests/render_preparacion.ps1
```

El inspector previo `scripts/inspeccionar-cu24-local.php` rechaza intencionalmente
la base ya migrada. Los scripts de verificación actuales son READ ONLY; los
funcionales crean únicamente fixtures inventados rastreados por ID, y retiran
revisiones antes que sus padres sin desactivar restricciones. Las secuencias
pueden avanzar. Los logs/resultados quedan en tests/artifacts/ ignorado.

## Inventario exclusivo de CU24

**42 archivos: 28 nuevos y 14 modificados.**

Incluye los cambios anteriores de preparación y los ajustes necesarios de
verificadores/CU23 para compartir permisos y conservar regresión bajo 010.

| Estado | Archivo |
|---|---|
| Modificado | `README.md` |
| Nuevo | `app/Controllers/RegistrosClinicosController.php` |
| Nuevo | `app/Models/RegistroClinico.php` |
| Nuevo | `app/Repositories/RegistroClinicoRepository.php` |
| Nuevo | `app/Services/AccesoClinicoService.php` |
| Modificado | `app/Services/PerfilClinicoService.php` |
| Nuevo | `app/Services/RegistroClinicoService.php` |
| Modificado | `app/Views/clinico/cabecera.php` |
| Nuevo | `app/Views/clinico/registro-formulario.php` |
| Nuevo | `app/Views/clinico/registros.php` |
| Nuevo | `database/migrations/010_antecedentes_laboratorio.sql` |
| Nuevo | `docs/ciclo2/CU24.md` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU24.md` |
| Modificado | `docs/ciclo2/README.md` |
| Modificado | `public/index.php` |
| Modificado | `scripts/Cu23Esquema.php` |
| Nuevo | `scripts/Cu24Esquema.php` |
| Nuevo | `scripts/aplicar-migracion-010.php` |
| Nuevo | `scripts/cu24/catalogo.sql` |
| Nuevo | `scripts/cu24/contrato.json` |
| Nuevo | `scripts/cu24/propuesta.json` |
| Nuevo | `scripts/inspeccionar-cu24-local.php` |
| Modificado | `scripts/render/verificar-instalacion.sql` |
| Modificado | `scripts/verificar-cu15-local.php` |
| Modificado | `scripts/verificar-cu23-local.php` |
| Nuevo | `scripts/verificar-cu24-local.php` |
| Modificado | `tests/README.md` |
| Nuevo | `tests/RegistroClinicoPrueba.php` |
| Modificado | `tests/esquema_cu23_validation.php` |
| Nuevo | `tests/esquema_cu24_validation.php` |
| Nuevo | `tests/http_registros_clinicos.php` |
| Modificado | `tests/perfil_clinico_integration.php` |
| Nuevo | `tests/registros_clinicos_browser.mjs` |
| Nuevo | `tests/registros_clinicos_browser.php` |
| Nuevo | `tests/registros_clinicos_concurrency.php` |
| Nuevo | `tests/registros_clinicos_integration.php` |
| Nuevo | `tests/registros_clinicos_integridad.php` |
| Nuevo | `tests/registros_clinicos_privacidad.php` |
| Nuevo | `tests/registros_clinicos_validation.php` |
| Modificado | `tests/regresion_cu23.php` |
| Nuevo | `tests/regresion_cu24.php` |

## Preservación y límites

Los cinco archivos anteriores protegidos permanecen fuera del inventario:
app/Config/Database.php, app/Config/DatabaseDiagnostic.php,
scripts/render-postgres.ps1, tests/render_preparacion.ps1 y
tests/database_render_validation.php. .env tampoco se modifica ni se publica.
Se comprobaron sus huellas privadas y las de 001–009, Composer y Docker al cierre:
todas coinciden con el estado inicial. Las doce tablas anteriores conservan
exactamente sus registros; las cuatro nuevas y las tres clínicas de CU23 están
vacías tras retirar fixtures. Los verificadores siguen confirmando 16/30 y GiST
sin conflictos. El índice Git permanece vacío y HEAD sigue en CU23 `f3d275c`.
No se publican huellas, secretos, sesiones, logs, capturas o dependencias vendor.

No se ejecutan commit, push, merge ni PR. main sigue en su referencia inicial y
no hay acceso a Render ni otras bases. No se crean cuentas Médicas permanentes:
para uso manual se necesita una cuenta vinculada activa y cita pertinente del día.
Sin portal Paciente, excepción administrativa, acceso clínico indefinido,
adjuntos ni interpretación automática. Docker mantiene su construcción real
pendiente; no se modificó su configuración. No se desarrolla CU25.
