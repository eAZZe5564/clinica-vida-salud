# Checklist de aceptación y cierre de despliegue

Preparado 2026-11-06. **Todas las casillas están pendientes**, salvo la evidencia
local expresamente identificada en el plan. No constituyen pruebas de producción
ya ejecutadas. Campaña histórica: 5.137 verificaciones; voz simulada. No repetida
en esta preparación.

Referencias: [plan](PLAN_RENDER_CICLO2.md),
[respaldo](RESPALDO_RECUPERACION_POSTGRES.md),
[propuesta de CI](PROPUESTA_GITHUB_ACTIONS_DOCKER.md).

20B autoriza publicar el workflow mínimo sin PostgreSQL. Su resultado debe
comprobarse en Actions antes de completar casillas de build/arranque; no completa
las de integración con BD, login ni despliegue de producción.

## Antes de tocar producción

- [ ] Revisar y aprobar el candidato exacto y la propuesta de CI; autorizar
  creación/publicación solo de la rama de preparación.
- [ ] Confirmar rama conectada main y Auto-Deploy actual en Render, sin secretos.
- [ ] Build real exitoso y registro de SHA/digest; no aceptar revisión estática.
- [ ] Integración real con PostgreSQL17 sintético y TLS verificado: Apache,
  /login, sesiones, permisos, descargas y reinicio sin cambios de BD.
- [ ] Revisión de Composer sin cambios de versiones y sin avisos pendientes;
  vendor y fuentes locales presentes en la imagen.
- [ ] Autorizar lectura remota. Confirmar cuenta/recurso/host externo/base
  actuales; PostgreSQL17, verify-full y contrato original exacto 001–004.
- [ ] Inventariar conteos y secuencias originales en registro privado; comprobar
  administrador existente y catálogos semilla sin volver a cargarlos.
- [ ] Preflight de conflictos históricos de GiST, constraints y duplicados
  según el plan; cero conflictos o detener sin modificar datos.
- [ ] Autorizar backup privado y nueva base aislada; restauración completa
  verificada del archivo y checksum, no solo listado TOC.
- [ ] Ensayo 005–010 en copia privada, en una transacción; validar cada paso,
  duración/bloqueos/espacio, conservación de datos y combinación código/esquema.
- [ ] Acordar mantenimiento real, responsables, duración y recuperación.
- [ ] Autorizar Auto-Deploy Off **antes** de actualizar main; confirmar sin
  builds/despliegues pendientes ni escritores antiguos activos.
- [ ] Detener/drenar tráfico y escrituras; backup final y restauración
  comprobada de ese mismo archivo durante la ventana.
- [ ] Autorizar migraciones remotas y hashes/host/base exactos. Validar 9/12
  tras 005–008, 12/20 tras009 y 16/30 tras010, con contratos y datos conservados.
- [ ] Autorizar integración/publicación a main y despliegue del SHA validado,
  sin los cinco cambios previos. Si cambia SHA/árbol, volver a revisar.

## Arranque y lecturas posteriores

Registrar solo fecha, SHA, esquema y éxito/fallo; sin PII, cookies, contraseñas,
CSRF, DSN, datos médicos o transcripciones. No adjuntar logs completos.

| Comprobación | Resultado esperado | Evidencia pendiente |
|---|---|---|
| Build y release | Render identifica el SHA aprobado; mismo árbol probado | [ ] |
| Health check /login | HTTP200; sin bucle de500, excepciones PDO ni trazas | [ ] |
| Apache/estáticos | CSS/JS propios200; raíz privada, .env, vendor y SQL no se sirven | [ ] |
| Entorno | PHP8.4, extensiones, zona America/La_Paz; puerto correcto | [ ] |
| Conexión | DB_* individuales correctos, host/base autorizados, TLS sin rebaja | [ ] |
| Esquema | 16 tablas/30 FK, contratos completos, btree_gist, GiST/trigger/índices válidos | [ ] |
| Datos originales | Conteos/huellas privados coinciden antes de pruebas; administrador activo conservado | [ ] |
| Login/logout | Cuenta autorizada entra; contraseña oculta; logoutPOST+CSRF invalida sesión | [ ] |
| Sesión | Secure/HttpOnly/SameSite, caducidad, renovación y rechazo de sesión cerrada | [ ] |
| Roles | Menú y servidor coinciden; solicitudes directas no eluden permisos | [ ] |
| Pacientes | Búsqueda multitérmino por nombres/apellidos/CI y paginación sin fuga | [ ] |
| Médicos/especialidades | Listados/asociaciones correctos según autorización | [ ] |
| Horarios/CU12 | Intervalos y disponibilidad con fechas locales correctas | [ ] |
| Agenda/CU19 | Diaria/semanal, filtros y alcance del médico vinculado | [ ] |
| Historial/CU20 | Paginación y pertenencia por paciente, sin clínica en proyección administrativa | [ ] |
| Reportes/CU21 | Solo Administrador/Recepcionista; médico/paciente403; cifras y filtros correctos | [ ] |
| PDF/CSV/CU22 | Mismos filtros y conteos que CU21, estados suman total; sin datos clínicos | [ ] |
| Descargas | Tipo MIME correcto, attachment, private/no-store, nosniff; sin HTML500 disfrazado de PDF/CSV | [ ] |
| PDF | %PDF-/%%EOF, abre completo, acentos/Ñ y páginas legibles, reporte vacío y límite | [ ] |
| CSV | UTF-8 BOM, separador ;, CRLF, cifras coincidentes, textos peligrosos no ejecutan fórmulas | [ ] |
| Errores | 403/404/405/422/409 seguros según caso, sin SQL/stack/credenciales ni datos ajenos | [ ] |
| Reinicio controlado | Sin migraciones ni seed; 16 tablas iguales antes de nuevos logins; sesión puede expirar | [ ] |

Login puede actualizar ultimo_acceso: tomar huellas para consultas/reinicio
**después del login y antes del siguiente** para no confundir una actualización
autorizada con una alteración del reinicio.

## Operaciones con datos ficticios

Preferir el ensayo privado para pruebas exhaustivas y de concurrencia.
Las escrituras de humo en producción requieren autorización posterior, cuentas
y registros exclusivos de prueba, manifiesto privado de IDs y plan de retiro.
No alterar pacientes/citas reales para demostrar un caso.

| Área | Prueba y condición de aceptación | Pendiente |
|---|---|---|
| Usuarios | Alta ficticia y política de contraseña; no recrear administrador original | [ ] |
| Pacientes/médicos | Altas/ediciones ficticias; roles e inactivos respetados | [ ] |
| CU11/CU12 | Horario exclusivo ficticio y disponibilidad; rechazo de solapamientos y médico ajeno | [ ] |
| CU13 | Reserva ficticia, código y relaciones estables, doble solicitud sin duplicar | [ ] |
| CU15 | Reprogramación permitida: cita+historial atómicos; conflicto conserva ambos sin cambio | [ ] |
| CU16 | Cancelar programada con motivo opcional; horario liberado; no doble evento | [ ] |
| CU17 | Confirmar programada conserva intervalo ocupado y crea el evento; no confirma cancelada | [ ] |
| CU18 llegada | Administrador/Recepcionista; programada/confirmada→presente; no finaliza atención | [ ] |
| CU18 atención | Solo médico activo de la cita; presente→atendida desde el inicio previsto | [ ] |
| CU18 inasistencia | Administrador/Recepcionista; solo desde fin del intervalo; sin automatismo ni evento duplicado | [ ] |
| GiST | Programada/confirmada/presente/atendida/inasistencia conservan intervalo; cancelada lo libera | [ ] |
| CU23 | Solo médico activo vinculado con cita asistencial válida del día; perfil/alergia ficticios | [ ] |
| CU23 semántica | Desconocido y no_registrado no se muestran como grupo confirmado ni ausencia confirmada de alergias | [ ] |
| CU24 | Antecedente/laboratorio ficticios, rectificación con motivo, revisión anterior conservada | [ ] |
| Concurrencia clínica | Versiones obsoletas rechazadas, ningún historial perdido; probar en copia privada | [ ] |
| Privacidad clínica | Admin/Recepcionista/Paciente denegados; médico sin relación404 uniforme; ningún dato clínico en CU19–CU22/logs | [ ] |
| Vinculación CU25 | Solo Admin activo, cuenta Paciente activa+paciente activo sin vínculo, identidad humana confirmada, POST+CSRF | [ ] |
| Límites de vínculo | No autovinculación, desvinculación, sustitución ni reasignación; concurrencia atómica | [ ] |
| Portal | Cuenta vinculada solo sus citas; sin vínculo403; manipular ID/actor/paciente no da acceso ajeno | [ ] |
| Gestión propia | Reserva/reprogramación/cancelación reutilizan reglas; historial personal minimizado sin clínica | [ ] |
| CSRF/XSS | POST sin token rechazado sin cambio; texto ficticio escapado en vistas/descargas | [ ] |

La relación asistencial clínica CU23/CU24 exige cita propia cuyo inicio sea
hoy en America/La_Paz y estado programada/confirmada/presente/atendida. No basta
una cita cancelada, inasistencia, historia de ayer o cita futura. No hay excepción
clínica administrativa.

CU25 conserva un **recibo temporal**, no auditoría persistente de vinculación.
El selector muestra hasta100 cuentas. No prometer bitácora permanente ni
añadir una migración para resolverlo durante esta publicación.

## Retiro seguro de fixtures

1. Antes de crearlos, decidir qué pruebas se harán exclusivamente en una copia
   privada. Es el destino preferido para clínicas, historial y concurrencia:
   los eventos persistentes no son archivos temporales borrables.
2. Para un humo autorizado en producción registrar en privado los IDs realmente
   devueltos, identificador aleatorio del ensayo, responsables y relaciones.
   Nunca seleccionar registros para retirar solo por nombre, fecha o prefijo.
3. Usar entidades ficticias nuevas y relaciones exclusivamente ficticias.
   No vincular una cuenta real para probar CU25: el vínculo no admite deshacerlo.
4. Tras pruebas, bloquear/inactivar cuentas ficticias y cerrar sesiones usando
   operaciones permitidas; cancelar solo citas ficticias cuyo estado lo permita.
   Inactivar registros clínicos ficticios por los servicios, dejando su historial
   conforme a la política; esto es retiro lógico, **no eliminación física**.
5. Si se exige ausencia física de datos de prueba en producción, no ejecutar
   allí esas pruebas persistentes sin un procedimiento de limpieza específico
   previamente revisado y autorizado. Debe usar IDs exactos, verificar que
   ninguna FK alcanza registros anteriores, transacción y huellas antes/después.
   No hay autorización ni script de borrado en esta preparación; jamás TRUNCATE,
   DELETE por rango/prefijo ni borrado de auditoría clínica como atajo.
6. Al cerrar, comparar las filas originales con la línea base privada; informar
   qué fixtures se retiraron, cuáles permanecen inactivos y por qué. No afirmar
   que la BD está libre de pruebas si queda historial ficticio.

No incluir estas evidencias en Git, grabaciones de pantalla, logs técnicos o
exportaciones que se compartan con compañeros. La política de retención se
decide antes de las escrituras.

## CU26: micrófono real en Edge, aún pendiente

La campaña anterior usó Edge real con eventos de reconocimiento **simulados**.
La presencia de SpeechRecognition no demuestra audio real, idioma disponible
ni funcionamiento offline. Completar manualmente por cada rol representativo:

- [ ] Abrir la URL HTTPS publicada en Edge, con una cuenta ficticia autorizada.
- [ ] Leer el aviso: el navegador podría enviar audio a servicios externos.
  No dictar datos personales, clínicos, contraseñas o nombres de pacientes.
- [ ] Confirmar que no escucha automáticamente; aceptar consentimiento y
  pulsar iniciar explícitamente, con un micrófono físico conectado.
- [ ] Pronunciar «Ir al inicio» y observar transcripción/navegación efectiva.
- [ ] Pronunciar un comando del catálogo permitido al rol y comprobar el destino.
  «Reservar una cita» solo abre el formulario GET; no guarda una reserva.
- [ ] Pronunciar una frase desconocida; mensaje seguro, sin URL inventada
  ni cambios. Probar un comando ajeno al rol; servidor sigue denegándolo.
- [ ] Detener/cerrar y navegar: no aceptar resultados tardíos.
- [ ] Restablecer el permiso y denegarlo en Edge; error comprensible, sin bucle.
- [ ] Probar texto, selector y enlaces manuales con permiso denegado y sin API.
- [ ] Confirmar que no hay endpoint de audio, archivos o transcripciones
  persistentes en backend. No grabar ni conservar audio de la prueba.
- [ ] Registrar solo Edge/fecha/idioma/rol/caso/éxito o fallo y observación
  técnica sanitizada; no guardar lo dicho ni una grabación.

Hasta completar esa lista y observar el resultado, mantener explícitamente
**reconocimiento con micrófono real pendiente**.

## Cierre y decisión

- [ ] Todas las barreras críticas satisfactorias; si falla una, detener y
  mantener mantenimiento sin nuevos reintentos de migración.
- [ ] Aceptación explícita de la combinación de código, esquema y limitaciones.
- [ ] Registro privado de backup restaurable, SHA y procedimiento de recuperación.
- [ ] Fixtures retirados según lo acordado y datos originales conservados.
- [ ] Autorizar apertura de tráfico; vigilar errores sanitizados.
- [ ] Decidir por separado si se autoriza volver a Auto-Deploy. No reactivarlo
  automáticamente ni actualizar main durante esta preparación.
