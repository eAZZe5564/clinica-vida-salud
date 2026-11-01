# CU19 — Informe de implementación y pruebas

Fecha: 01/11/2026. PHP 8.4.22, PostgreSQL 17.10 local, America/La_Paz.
Base exclusiva: `clinica_vida_salud_dev`. Rama
`feat/ciclo2-cu19-agenda-medica`, creada desde CU18
`171c61936765367c1320c3ef6d1a5bab4eb8172f`, que contiene CU15–CU17.
Sin commit, push, merge, Pull Request o despliegue. No se implementa CU20.

## Resultado funcional

GET `/agenda` (alias diaria), `/agenda/diaria` y `/agenda/semanal`.
Consulta por `fecha`, `medico_id` y `estado`. Administrador/recepción seleccionan
profesional; el médico usa exclusivamente su vínculo de cuenta, sin selector ajeno.
Paciente recibe 403 y sin sesión se redirige a login. Un médico que fuerza otro
ID recibe 403; sin vínculo se muestra un mensaje sin datos de otras agendas.

Diaria muestra franjas reales de CU11, paciente, especialidad, código, inicio/fin
y etiqueta textual de estado; semanal agrupa lunes a domingo. Se puede cambiar
vista, fecha, día/semana anterior o siguiente y volver a hoy/semana actual,
manteniendo médico y estado. Escritorio: siete columnas; tablet: dos; celular:
días en una columna sin tabla horizontal. Se usan estilos propios del proyecto
y CSS específico, sin dependencias nuevas ni JavaScript de calendario.

Estados: programada, confirmada, presente, atendida, inasistencia y cancelada.
Filtro predeterminado `operativa` excluye canceladas; `todas` o un estado exacto
permiten consultarlas como historia, con indicación de que no reservan el turno.
No se cambian estados o fechas desde el calendario. Los enlaces al detalle
pasan por la autorización existente de CU14. CU19 no ofrece botones de reserva,
reprogramación, cancelación, confirmación o asistencia.

Los períodos fuera de atención, intervalos de treinta minutos sin cita y citas
existentes se diferencian. Un filtro que omite una cita no convierte su intervalo
ocupado en un hueco vacío. «Sin cita registrada» es descriptivo, no disponibilidad
CU12 ni promesa de reserva. Las citas fuera de la configuración actual siguen
visibles con una advertencia. Profesionales inactivos conservan su historia, sin
franjas operativas activas, conforme al alcance administrativo de CU14.

## Consultas, privacidad y esquema

La consulta completa administrativa usa cuatro SELECT agrupados: opciones,
especialidades, horarios y citas; el médico añade la resolución de su vínculo.
No hay consulta por celda o turno. Las citas se restringen siempre a un médico y
al día o semana solicitado. Se reutilizan índices de médico/fecha, sin aplicar
DATE() a la columna indexada. No se carga todo el historial de citas.

Rangos `[desde,hasta)` en America/La_Paz, convertidos a instantes UTC para PDO.
Las citas se asignan al día de inicio. Se consulta un margen anterior máximo
de treinta minutos para detectar arrastres, sin mostrar el paciente/detalle de
una cita de otro día. Esto impide marcar falsamente vacío el primer intervalo.
Una cita cuyo fin cruza medianoche muestra el fin real con `(+1 día)`.
Se comprueban medianoche local/UTC, mes, año, semana, bisiesto y fechas extremas.

Solo se proyectan datos administrativos necesarios; no se seleccionan CI,
contactos, dirección, motivo clínico, diagnósticos ni historial de responsables.
HTML escapado en paciente, médico, especialidad, filtros y enlaces; SQL con
parámetros PDO. Fechas, estados, tamaños y tipos malformados se rechazan con 422;
médico inexistente: 404; métodos distintos de GET para roles permitidos: 405.
CU19 no requiere CSRF de escritura porque no ofrece operaciones de escritura;
el logout global y los demás casos conservan su protección existente.

**No requiere migración.** No se crearon o aplicaron migraciones, índices, CHECK,
FK, tablas o triggers. El esquema 001–008 permanece con nueve tablas y doce FK.
No se modificaron los servicios CU11–CU18. Los cambios que esos casos confirman
se reflejan al volver a consultar: reserva CU13, inicio CU15, cancelación CU16
y estados CU17/CU18. Llegada y atención continúan siendo conceptos distintos.

## Pruebas nuevas ejecutadas ahora

| Comando | Aprobadas |
|---|---:|
| `php tests/agenda_integration.php` | 77 |
| `php tests/http_agenda.php` | 101 |
| `php tests/agenda_browser.php` | 21 |
| **CU19 nuevas** | **199** |

Integración usa PostgreSQL real. Una transacción READ ONLY ejecuta consultas
completas diaria/semanal y todos los filtros; un PDO vigilado admite únicamente
SELECT preparados y comprueba cuatro consultas agrupadas para día y semana.
Comprueba proyección mínima, orden, permisos, vacío/horarios, seis estados,
filtros canceladas/todas, límites de día/semana, cruces de mes/año, 29 de febrero,
0001/9999, UTC/La Paz, arrastre y ausencia de escrituras mediante huellas de las
nueve tablas. Prueba además, sobre fixtures, reserva/reprogramación/cancelación
por los servicios anteriores y lectura de los estados generados por CU17/CU18.

HTTP verifica tres rutas y roles, GET/POST sin sesión, paciente rechazado,
POST/PUT/DELETE rechazados para los roles autorizados, IDOR, médico sin vínculo,
selección administrativa, estados, vacíos, 403/404/405/422, privacidad, XSS
en nombres/especialidad y enlaces CU14 autorizados. Toma huellas después de los
logins y confirma que todos los GET y métodos rechazados conservan las nueve
tablas; el login existente registra último acceso y no forma parte de CU19.

Edge real recorre diaria/semanal, navegación, semana vacía, filtro con canceladas,
alternancia manteniendo selección, detalle CU14, médico propio y rechazo de
agenda ajena por URL. Comprueba siete/dos/una columnas a 1440/900/390px y límites
reales de las tarjetas, además del ancho del documento. Verificación visual de
capturas de escritorio y celular. Seis capturas ficticias bajo
`tests/artifacts/cu19/`, ignoradas por Git; perfil temporal separado y credenciales
ficticias por stdin, sin usar el perfil personal del usuario.

El primer recorrido Edge detectó una colisión entre la variable de modo y
`$vista`, reservada por el renderizador común. Se corrigió solo CU19 usando
`modoAgenda` y se agregaron dos aserciones HTTP de modo/acción de formulario.
La ejecución completa posterior aprobó; los intentos anteriores no se suman.
No se presentan esas cifras iniciales como aprobaciones finales adicionales.

Las escrituras de preparación usan únicamente registros ficticios y servicios
existentes CU11–CU18. Se incluyen fixtures de legado para fechas pasadas y límites,
sin inventar eventos de historial. Cada prueba retira sus propios IDs en finally;
no vacía tablas ni altera permanentemente registros anteriores. Las secuencias
pueden avanzar incluso tras rollback. No se desactivan triggers o restricciones.

## Regresión ejecutada ahora

Se reejecutaron las 26 suites del Ciclo 1 y las dieciséis suites de CU15–CU18,
además del esquema y las herramientas. Todos los resultados siguientes se
obtuvieron ahora con CU19 presente; no se copian aprobaciones históricas ni se
suman dos veces los intentos repetidos de las pruebas nuevas.

| Conjunto ejecutado ahora | Aprobadas |
|---|---:|
| CU01–CU14 y mejoras 6A: `php tests/regresion_cu15.php` | 1003 |
| CU15: integración 36, concurrencia 34, HTTP 35, Edge 16 | 121 |
| CU16: integración 53, HTTP 41, concurrencia 64, Edge 25 | 183 |
| CU17: integración 53, HTTP 43, concurrencia 86, Edge 29 | 211 |
| CU18: integración 74, HTTP 57, concurrencia 141, Edge 38 | 310 |
| **Regresión funcional CU01–CU18** | **1828** |
| `php tests/esquema_cu15_validation.php`, READ ONLY | 93 |
| `php tests/render_sql_local.php`, READ ONLY local | 3 |
| `php tests/render_preparacion.php`, sin red | 24 |
| `powershell -NoProfile -File tests/render_preparacion.ps1`, sin red | 34 |
| **Regresión y herramientas** | **1982** |
| **Total con las 199 nuevas de CU19** | **2181** |

La regresión conserva sus pruebas de concurrencia reales y reglas CU11–CU18.
No se requiere una operación concurrente de escritura nueva para CU19: es una
proyección SELECT, no modifica ni adquiere reservas. La campaña terminó sin
fallos pendientes. Los informes CU15–CU18 anteriores siguen siendo históricos;
esta tabla registra la ejecución actual.

Comandos de la campaña:

```powershell
php tests/regresion_cu15.php
$cu19Suites = @(
    'reprogramacion_integration','reprogramacion_concurrency','http_reprogramacion','reprogramacion_browser',
    'cancelacion_integration','http_cancelacion','cancelacion_concurrency','cancelacion_browser',
    'confirmacion_integration','http_confirmacion','confirmacion_concurrency','confirmacion_browser',
    'asistencia_integration','http_asistencia','asistencia_concurrency','asistencia_browser',
    'esquema_cu15_validation','render_sql_local','render_preparacion'
)
foreach ($cu19Suite in $cu19Suites) {
    php ('tests/' + $cu19Suite + '.php')
    if ($LASTEXITCODE -ne 0) { throw 'Detener la campaña y revisar el fallo localmente.' }
}
powershell -NoProfile -File tests/render_preparacion.ps1
```

Los scripts de preparación Render de esta lista son pruebas locales sin red;
`render_sql_local.php` es READ ONLY y la guarda apunta a desarrollo local.
No ejecutan asistentes de inicialización, migraciones o usuarios remotos.
La suite histórica de primera cuenta que exige usuarios vacíos no se ejecuta;
la regresión conservada utiliza `http_auth_integration.php`.

Sintaxis ejecutada ahora: **187 archivos PHP correctos** y
`node --check tests/citas_browser.mjs` correcto. No se suman al total de aserciones.
No se construyó Docker ni se probaron Apache o servicios de producción.

## Inventario exclusivo de CU19

**19 archivos: 12 nuevos y 7 modificados.** No se incluyen los cinco cambios previos.

| Estado | Archivo |
|---|---|
| Nuevo | `app/Controllers/AgendaController.php` |
| Nuevo | `app/Repositories/AgendaRepository.php` |
| Nuevo | `app/Services/AgendaService.php` |
| Nuevo | `app/Views/agenda/error.php` |
| Nuevo | `app/Views/agenda/index.php` |
| Modificado | `app/Views/dashboard.php` |
| Modificado | `app/Views/layout.php` |
| Nuevo | `docs/ciclo2/CU19.md` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU19.md` |
| Modificado | `docs/ciclo2/README.md` |
| Nuevo | `public/assets/css/agenda.css` |
| Modificado | `public/index.php` |
| Nuevo | `tests/agenda_browser.php` |
| Nuevo | `tests/agenda_integration.php` |
| Nuevo | `tests/AgendaPrueba.php` |
| Modificado | `tests/citas_browser.mjs` |
| Nuevo | `tests/http_agenda.php` |
| Modificado | `tests/README.md` |

## Protección y cierre

Archivos anteriores excluidos del inventario, que se preservan:

- `app/Config/Database.php`
- `app/Config/DatabaseDiagnostic.php`
- `scripts/render-postgres.ps1`
- `tests/render_preparacion.ps1`
- `tests/database_render_validation.php`

Comprobaciones finales ejecutadas:

- Los cinco archivos anteriores y .env conservan exactamente sus SHA256 iniciales.
  No se publican las huellas ni contenido privado.
- Conteos y huellas de las filas de las nueve tablas son idénticos a la captura
  previa al desarrollo/pruebas, sin fixtures residuales ni datos anteriores alterados.
  Los metadatos capturados también coinciden; las secuencias pueden avanzar.
- `php scripts/verificar-cu15-local.php`: contrato 001–008 válido, READ ONLY,
  nueve tablas/doce FK. `php scripts/inspeccionar-cu18-local.php`: cero conflictos
  de intervalos retenidos y cero eventos CU18 duplicados, READ ONLY.
- Migraciones 001–008, SQL del verificador y servicios CU11–CU18 idénticos a HEAD.
  No se crearon ni ejecutaron migraciones, ni se conectó a PostgreSQL externo.
- Inventario comparado con Git: exactamente 19 archivos (12 nuevos/7 modificados),
  fuera del índice. Los cinco cambios anteriores permanecen separados.
- Sin coincidencias de secretos privados de .env, claves privadas, tokens GitHub
  o URL PostgreSQL con credenciales en los archivos de CU19 revisados.
- `.env`, capturas y logs ignorados por Git; sesiones, temporales y respaldos
  continúan excluidos por `.gitignore`. `git diff --check` correcto.
- Rama `feat/ciclo2-cu19-agenda-medica`, HEAD todavía CU18
  `171c61936765367c1320c3ef6d1a5bab4eb8172f`; main conserva
  `1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37`. Índice vacío. No se ejecutó
  git add, commit, push, merge, stash, reset o clean; no se creó Pull Request.
- Render, producción y Panadería Santiago no se consultaron ni modificaron.

CU19 está implementado y probado localmente, sin problemas conocidos pendientes
dentro del alcance descrito. Queda listo para revisión y futura autorización
de commit; no se publica ni despliega automáticamente. No se desarrolló CU20.

Diseño, decisiones, reglas RN-AG01–16 y UML: [CU19.md](CU19.md).
Mapa del código: [MAPA_DEL_CODIGO.md](MAPA_DEL_CODIGO.md).
