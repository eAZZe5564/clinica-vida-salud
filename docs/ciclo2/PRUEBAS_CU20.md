# CU20 — Informe de implementación y pruebas

Fecha: 01/11/2026. PHP 8.4.22, PostgreSQL 17.10 local, America/La_Paz.
Base exclusiva: `clinica_vida_salud_dev`. Rama
`feat/ciclo2-cu20-historial-paciente`, desde CU19
`09de4d9bf1a7aea0cd4b1ef5561201b949e7f921`, con CU15–CU18 conservados.
No se hace commit, push, merge, Pull Request o despliegue. CU21 no implementado.

## Funcionalidad y permisos

Solo GET: `/historial-citas` para buscar; `/pacientes/{id}/historial-citas`
para consultar; `/pacientes/{id}/historial-citas/{cita}/eventos` para auditoría.
Acceso desde listado/detalle CU04–CU06, navegación y panel. El médico accede al
buscador mínimo CU20, sin habilitarle el CRUD/detalle personal de pacientes.

Administrador y recepción consultan el historial administrativo del paciente.
Médico consulta exclusivamente sus pacientes y sus propias citas de ellos:
el ID deriva del vínculo medicos.usuario_id de CU14. No obtiene catálogos o
eventos de otro profesional. Una cita ajena del mismo paciente y una cita de otro
paciente se rechazan con 404. Médico sin vínculo: búsqueda sin datos y mensaje;
un ID directo no da acceso. La manipulación del filtro médico se rechaza con 422.

El rol Paciente continúa denegado con 403, incluso con ficha vinculada. Existe
la FK pacientes.usuario_id, pero CU14 aún no tiene lectura propia para ese rol:
el enunciado condiciona esa función a que ya exista soporte seguro. Se verifica
el bloqueo de historial propio/ajeno y de CU14. **No se afirma que el caso
condicional de lectura personal esté implementado o haya aprobado como acceso
exitoso.** La propuesta de endpoint propio derivado de sesión, sin IDs arbitrarios,
está en CU20.md; no se implementa CU25 ni se improvisa identidad.

La pantalla principal muestra nombres/código PAC mínimo, cantidad visible, código
CM, inicio/fin local, duración, médico, especialidad y estado. Orden descendente
por inicio e ID; una fila por cita, aunque existan múltiples reprogramaciones.
Incluye los seis estados, canceladas e inasistencias sin excluirlas por defecto.
Cada enlace CU14 vuelve a pasar por la autorización existente.

Filtros SQL: desde/hasta inclusive, estado, médico permitido, especialidad y
período todas/proximas/historicas (Todas/Futuras/Anteriores según inicio). Opciones
rápidas Mostrar todas/futuras/anteriores/canceladas. Páginas de veinte citas,
preservando filtros. Datos anteriores/futuros, vacíos e historia con especialidad
NULL son respuestas normales. Límites locales `[desde,hasta+1 día)` convertidos
a UTC; prueba de medianoche UTC/La Paz e inclusión/exclusión exacta del día.

## Auditoría y consultas

Se reutiliza citas_historial. Seis tipos reales: reprogramacion, cancelacion,
confirmacion, llegada, atencion e inasistencia. Vista independiente con veinte
eventos por página, orden cambiado_en/id ascendente, estados anterior/posterior,
inicios anterior/nuevo y momento. Responsable por correo solo para administrador;
recepción/médico consultan cambios administrativos autorizados sin ese campo.
No se seleccionan motivos clínicos o de cancelación, contactos, documentos en
resultados, diagnósticos, antecedentes, hashes ni datos de autenticación.
El buscador administrativo puede usar CI ya autorizado en CU05, sin mostrarlo.
El buscador médico solo usa nombres/apellidos. No se inventan eventos de legado.

Cada método del repositorio es SELECT preparado. Seis consultas agrupadas para
historial (paciente, count, página de citas, conteos de eventos de esas veinte
citas, dos opciones), cuatro para una página de eventos y dos para búsqueda.
El vínculo médico se resuelve mediante CU14 al construir el servicio. No hay
consulta por fila/celda, carga de auditoría global ni eventos completos de todas
las citas de una página. SQL siempre contiene paciente/alcance y los filtros;
la paginación ocurre en PostgreSQL, con orden estable.

CU20 no cambia estados, horarios, reservas, disponibilidad ni concurrencia.
CU13–CU18 generan los registros que se presentan y CU19 conserva consistencia
con la cita vigente. No necesita migración 009: reutiliza nueve tablas/doce FK
y los índices existentes de paciente/fecha, médico y eventos por cita.

## Pruebas nuevas ejecutadas ahora

| Comando | Aprobadas |
|---|---:|
| `php tests/historial_paciente_integration.php` | 76 |
| `php tests/http_historial_paciente.php` | 114 |
| `php tests/historial_paciente_browser.php` | 22 |
| **CU20 nuevas** | **212** |

Integración: PostgreSQL real con historial de 41 citas, estados generados por
servicios CU13–CU18, 23 reprogramaciones de una misma cita, tres páginas de citas
y dos de eventos. Comprueba unicidad/horario actual, orden con fechas iguales,
seis estados/tipos de evento, paciente vacío/único/múltiple/inexistente/inaccesible,
rol/vínculo, filtros, paginación, legado NULL/sin eventos, mínimos datos, fechas,
búsqueda por términos/comodines/CI administrativo y coincidencia con CU19.
Las lecturas completas se ejecutan en una transacción READ ONLY con PDO vigilado
que admite solo SELECT; se comparan conteos y huellas de las nueve tablas.

HTTP: las tres rutas con roles permitidos, sin sesión y paciente rechazado;
POST/PUT/DELETE no escriben (405 para autorizados); IDOR de paciente/cita/médico,
422 de filtros y 404 de inexistencia/alcance; tabla/contador/páginas/filtros,
auditoría con responsable solo administrativo, XSS en paciente/médico/especialidad,
ausencia de contactos/motivos/hashes, cuenta paciente vinculada sin portal,
enlaces CU04–CU06 y detalle CU14. Tras los logins toma huellas de las nueve
tablas y comprueba que los GET y métodos rechazados no alteran ninguna.

Edge real: escritorio 1440px, tablet 900px y celular 390px. Entrada CU04 al
historial, páginas de citas y eventos, filtros desde móvil, restablecer todas,
detalle CU14, búsqueda/atención propia del médico, eventos sin responsables y
rechazo de cita ajena. Comprueba bordes reales de tablas/celdas y documento,
sin desplazamiento horizontal. Se revisaron visualmente capturas de escritorio,
tablet y eventos móviles. Los estados se mantienen legibles sin cortar palabras;
tablet/celular muestran campos con etiquetas y eventos en tarjetas.

Siete capturas ficticias ignoradas en `tests/artifacts/cu20/`. El navegador
usa perfil temporal separado y credenciales ficticias por stdin. Su comparación
de dominio excluye usuarios, porque cambia de cuenta y el login existente
registra último acceso; las otras ocho tablas deben permanecer idénticas.
HTTP toma la huella después de autenticar y sí compara las nueve tablas.

Solo se cuentan ejecuciones finales aprobadas. Se repitió Edge tras mejorar
la presentación y corregir una expectativa de prueba que buscaba «404» en el
texto: la plantilla real dice «Página no encontrada»; HTTP verificó el estado
404 por separado. No se modificó la plantilla ni se relajó la autorización.

Preparación de datos: todos ficticios, servicios anteriores para transiciones
auténticas y fechas/estados de legado para probar bordes y NULL. La cuenta
Paciente vinculada se crea únicamente como fixture para comprobar denegación.
Los fixtures compuestos de CU18 usan reloj simulado por constructor para generar
atención/inasistencia sin esperar treinta minutos. CU20, HTTP y Edge consultan
con el reloj real; no existe un parámetro HTTP o de entorno para alterar la hora.
Limpieza por sus propios IDs en finally, sin vaciar tablas o cambiar registros
anteriores. Las secuencias pueden avanzar. No se desactivan triggers, CHECK, GiST,
sesiones, autenticación o CSRF. No se construyó Docker ni se probó producción.

## Regresión actual

Se ejecutaron ahora las 26 suites del Ciclo 1 y las diecinueve de CU15–CU19:
45 suites de regresión funcional. También se ejecutaron esquema y herramientas.
No se copian aprobaciones de entregas anteriores ni se duplican los intentos.

| Conjunto ejecutado ahora | Aprobadas |
|---|---:|
| CU01–CU14 y mejoras 6A: `php tests/regresion_cu15.php` | 1003 |
| CU15: integración 36, concurrencia 34, HTTP 35, Edge 16 | 121 |
| CU16: integración 53, HTTP 41, concurrencia 64, Edge 25 | 183 |
| CU17: integración 53, HTTP 43, concurrencia 86, Edge 29 | 211 |
| CU18: integración 74, HTTP 57, concurrencia 141, Edge 38 | 310 |
| CU19: integración 77, HTTP 101, Edge 21 | 199 |
| **Regresión funcional CU01–CU19** | **2027** |
| `php tests/esquema_cu15_validation.php`, READ ONLY | 93 |
| `php tests/render_sql_local.php`, READ ONLY local | 3 |
| `php tests/render_preparacion.php`, sin red | 24 |
| `powershell -NoProfile -File tests/render_preparacion.ps1`, sin red | 34 |
| **Regresión y herramientas actuales** | **2181** |
| **Total con las 212 nuevas de CU20** | **2393** |

Toda la campaña terminó sin fallos pendientes, incluidas las carreras reales de
los casos anteriores. CU20 no necesita una transición concurrente nueva: solo
lee. Se conservan las pruebas de reserva/GiST y los servicios anteriores.
Los informes CU15–CU19 conservan sus cifras históricas; esta tabla acredita la
ejecución actual con CU20 presente.

Comandos de la campaña:

```powershell
php tests/regresion_cu15.php
$cu20Suites = @(
    'reprogramacion_integration','reprogramacion_concurrency','http_reprogramacion','reprogramacion_browser',
    'cancelacion_integration','http_cancelacion','cancelacion_concurrency','cancelacion_browser',
    'confirmacion_integration','http_confirmacion','confirmacion_concurrency','confirmacion_browser',
    'asistencia_integration','http_asistencia','asistencia_concurrency','asistencia_browser',
    'agenda_integration','http_agenda','agenda_browser',
    'esquema_cu15_validation','render_sql_local','render_preparacion'
)
foreach ($cu20Suite in $cu20Suites) {
    php ('tests/' + $cu20Suite + '.php')
    if ($LASTEXITCODE -ne 0) { throw 'Detener y revisar el fallo localmente.' }
}
powershell -NoProfile -File tests/render_preparacion.ps1
```

Las suites llamadas render son comprobaciones locales sin conexión externa:
SQL READ ONLY y simulaciones de guardas/procesos. No ejecutan asistentes
remotos de migración, inicialización o administrador. No se ejecuta la suite
histórica que exige usuarios vacíos; el runner conserva http_auth_integration.

Sintaxis ejecutada: **198 archivos PHP correctos** y
`node --check tests/citas_browser.mjs` correcto, fuera del conteo de aserciones.

## Inventario exclusivo de CU20

**23 archivos: 14 nuevos y 9 modificados.** Los cinco cambios anteriores no pertenecen a este inventario.

| Estado | Archivo |
|---|---|
| Nuevo | `app/Controllers/HistorialPacientesController.php` |
| Nuevo | `app/Repositories/HistorialPacienteRepository.php` |
| Nuevo | `app/Services/HistorialPacienteService.php` |
| Modificado | `app/Views/dashboard.php` |
| Nuevo | `app/Views/historial/buscar.php` |
| Nuevo | `app/Views/historial/error.php` |
| Nuevo | `app/Views/historial/eventos.php` |
| Nuevo | `app/Views/historial/index.php` |
| Modificado | `app/Views/layout.php` |
| Modificado | `app/Views/pacientes/index.php` |
| Modificado | `app/Views/pacientes/show.php` |
| Nuevo | `docs/ciclo2/CU20.md` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU20.md` |
| Modificado | `docs/ciclo2/README.md` |
| Nuevo | `public/assets/css/historial.css` |
| Modificado | `public/index.php` |
| Modificado | `tests/citas_browser.mjs` |
| Nuevo | `tests/historial_paciente_browser.php` |
| Nuevo | `tests/historial_paciente_integration.php` |
| Nuevo | `tests/HistorialPacientePrueba.php` |
| Nuevo | `tests/http_historial_paciente.php` |
| Modificado | `tests/README.md` |

## Protección y estado final

Archivos anteriores que se preservan y quedan excluidos:

- `app/Config/Database.php`
- `app/Config/DatabaseDiagnostic.php`
- `scripts/render-postgres.ps1`
- `tests/render_preparacion.ps1`
- `tests/database_render_validation.php`

Comprobaciones finales ejecutadas:

- Los cinco archivos anteriores y .env conservan sus SHA256 iniciales exactos;
  no se publican huellas ni valores privados.
- Las nueve tablas mantienen sus conteos y huellas de filas iniciales, sin
  fixtures residuales o registros preexistentes alterados. Los metadatos capturados
  también coinciden. Las secuencias pueden haber avanzado por las pruebas.
- `php scripts/verificar-cu15-local.php`: contrato 001–008 válido en READ ONLY,
  nueve tablas/doce FK. `php scripts/inspeccionar-cu18-local.php`: cero conflictos
  de intervalos retenidos y cero eventos CU18 duplicados, READ ONLY.
- Migraciones 001–008, SQL del verificador y servicios CU11–CU19 idénticos a HEAD;
  no se crearon ni ejecutaron migraciones o índices nuevos.
- Inventario contrastado con Git: 23 archivos, 14 nuevos/9 modificados. Cinco
  cambios anteriores separados. Índice vacío; no se ejecutó git add, commit, push,
  merge, reset, clean o stash, ni se creó Pull Request.
- HEAD sigue en CU19 `09de4d9bf1a7aea0cd4b1ef5561201b949e7f921` y main conserva
  `1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37`.
- No se detectaron coincidencias de secretos privados de .env, claves privadas,
  tokens GitHub o URL PostgreSQL con credenciales en los archivos CU20 revisados.
- .env, sesiones, logs, capturas y temporales ignorados por Git; respaldos siguen
  excluidos. `git diff --check` y la revisión de formato de los archivos nuevos
  completaron sin errores.
- No se consultaron ni modificaron Render, PostgreSQL externo o Panadería Santiago.
  No hubo despliegue ni desarrollo de CU21.

CU20 está implementado y probado localmente para administración, recepción y el
alcance propio del médico, sin fallos conocidos pendientes. El acceso personal
del rol Paciente sigue condicionado al portal seguro aún inexistente, con
propuesta documentada; no se presenta esa capacidad como implementada. Queda
listo para revisión, sin autorización implícita de commit/push o despliegue.

Diseño, permisos, reglas RN-H01–20, limitación Paciente, consultas y UML:
[CU20.md](CU20.md). Mapa: [MAPA_DEL_CODIGO.md](MAPA_DEL_CODIGO.md).
