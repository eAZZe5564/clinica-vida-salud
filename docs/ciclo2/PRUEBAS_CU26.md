# CU26 — Pruebas, compatibilidad e inventario

## Estado final

CU26 implementado localmente en `feat/ciclo2-cu26-comandos-voz`, desde CU25
`8e3fd8f38b6dfbbf706031c01c70aee677e64f94`. HEAD continúa en ese commit,
con índice vacío: no git add, commit, push, merge ni Pull Request.
Sin cambios de main ni acceso a Render. No hay migración 011 o 012.

[CU26.md](CU26.md) documenta actores, catálogo completo, reglas, tecnología,
privacidad, alternativa manual y demostración pendiente con micrófono real.

## Comprobaciones nuevas ejecutadas ahora

| Suite | Aprobadas | Qué prueba |
|---|---:|---|
| comandos_voz_validation | 315 | Catálogo PHP por rol/vínculo, IDs, destinos cerrados, resolución JS real, acentos, mayúsculas, espacios, puntuación, rechazo de texto libre, modificaciones, identidades, clínica y URLs manipuladas |
| http_comandos_voz | 85 | HTTP local real, anónimo sin módulo, seis cuentas ficticias, catálogo según rol/vínculo y actor al editar otra cuenta, destinos existentes autorizados, permisos directos, CSRF/logout y ausencia de escrituras de consultas |
| comandos_voz_browser | 61 | Edge real en tres tamaños, API detectada sin micrófono, eventos simulados, consentimiento, inicio/detención, errores, no soporte, es-BO/es-ES, tiempo límite, eventos tardíos, limpieza, texto/selector/Enter, alcance por rol, XSS y logout |
| **Total específico CU26** | **461** | Sin sumar repeticiones o intentos fallidos |

Comandos ejecutados: `php tests/regresion_cu26.php --solo-cu26` aprobó las
suites de validación (315) y HTTP (85), y detectó un problema en el recorrido de
teclado de la prueba Edge. El envío CDP de Enter necesitaba su texto de tecla;
se añadieron text/unmodifiedText y se repitió únicamente la suite correspondiente:
`php tests/regresion_cu26.php comandos_voz_browser`, **61 aprobadas, salida 0**.
No fue necesario cambiar la aplicación por ese fallo del ejecutor. Se añadió
también una guarda que exige comprobar que la API simulada reemplazó la nativa
antes de cualquier clic de inicio. No se intentó ocultar un fallo del producto.

Los 461 controles corresponden a los resultados finales de las tres suites
ejecutadas ahora, no a un único intento sin interrupciones. No se suman otra
vez las validaciones independientes ni las repeticiones. No quedan casos
reproducibles fallidos.

## Pruebas reales frente a simuladas

Sonda real de **Microsoft Edge Edg/156.0.4314.58** en localhost:

- SpeechRecognition: constructor presente.
- webkitSpeechRecognition: constructor presente.
- Contexto seguro: true.
- API nativa start(): **no invocada**.
- Audio real, permiso de micrófono y frase hablada: **no probados**.

La presencia del constructor no demuestra que el motor funcione, que admita
es-BO/es-ES, que tenga conexión al proveedor ni que exista un micrófono.
La aplicación comprueba errores durante la interacción real y mantiene texto,
selector y enlaces. No se afirma reconocimiento local ni funcionamiento offline.

El navegador, el DOM, la carga de módulos, el teclado Enter, el foco, las
navegaciones y las rutas PHP fueron reales. Los resultados y errores de voz
fueron **eventos simulados**, sin usar micrófono o solicitar permiso nativo.
Se verificaron ambas formas de constructor (estándar y prefijada), ausencia de
API/contexto, excepción de constructor/inicio, permiso denegado, silencio,
error de red e idioma no soportado. La asignación de lang se comprobó con
simulación, no con un servicio de reconocimiento real.

El límite de 12 segundos se prueba con un reloj acelerado dentro del navegador
de pruebas; no se presenta como una medición acústica real. Se comprueba que
cerrar, detener, retirar consentimiento, ocultar pestaña, pagehide, navegar o
cerrar sesión descarta resultados tardíos y limpia la interacción.

Escritorio **1440×1000**, tablet **820×1180**, celular **390×844**: sin
desbordamiento horizontal, botones táctiles, estado aria-live, foco y alternativa
manual por Enter. No hay certificación de lector de pantalla: accesibilidad
básica verificada mediante teclado, estructura y DOM.

No se generaron capturas privadas. El registro de solicitudes de la aplicación
solo vio el origen local o recursos data: internos; ningún valor de transcripción
en URLs ni POST de comandos. Solo se observan los POST habituales de login/logout.
Los metadatos de compatibilidad se guardan en el artefacto local ignorado
tests/artifacts/cu26/compatibilidad.json, sin audio, transcripciones o credenciales.

## Seguridad, permisos y ausencia de escrituras por voz

- La lista PHP se genera desde el actor autenticado real, no desde la cuenta
  editada. El Paciente requiere un vínculo activo existente para recibir
  comandos de citas; sin vínculo solo ve inicio y ayuda.
- La ruta de destino vuelve a validar sesión, rol, recurso e identidad. Una
  orden o URL manipulada no abre citas ajenas, información clínica ni funciones
  administrativas prohibidas al rol.
- Los comandos no añaden parámetros con pacientes/CI/nombres ni generan SQL,
  HTML o URLs desde voz. No hay interpretación libre ni acciones de modificación.
- «Reservar una cita» abre el formulario GET existente. No envía una reserva,
  no confirma cambios ni altera las reglas CU13–CU18.
- La transcripción se mantiene solo temporalmente en el DOM mediante textContent.
  No hay endpoint de voz ni almacenamiento en PHP, sesiones, PostgreSQL,
  ficheros, localStorage/IndexedDB o logs. Los campos manuales no tienen name
  y su envío se previene; los enlaces siguen funcionando sin JS.
- No se usa MediaRecorder, speechSynthesis, SDK externo o claves de proveedor.
  El navegador puede enviar audio fuera del equipo tras la aceptación explícita;
  la aplicación no puede prometer que el proveedor no lo retenga.
- Se revalidó logout con CSRF y sesión cerrada; no hay comando para cerrarla.
  Los eventos de submit/enlaces/salida detienen y limpian la escucha.

En HTTP se tomaron huellas después de los logins ficticios y se recorrieron
todos los destinos permitidos: las **dieciséis tablas permanecieron idénticas**.
En Edge los únicos cambios de usuarios son los últimos accesos de sus logins
ficticios; las otras quince tablas se compararon sin diferencias. No se
atribuyen esas marcas de autenticación a órdenes de voz.

## Regresión CU01–CU25 ejecutada ahora

Se ejecutó `php tests/Cu26Ejecutar.php regresion_cu25`, que lanzó la campaña
anidada completa existente: **4.591 comprobaciones PHP aprobadas**, salida 0,
sin detenerse en suites anteriores. Incluye 4.145 de CU01–CU24 y 446 de CU25.

Se complementó con:

- `python tests/leer_pdf_cu22.py`: **51**, lectura real mediante pypdf.
- `powershell -NoProfile -File tests/render_preparacion.ps1`: **34**,
  sin red ni conexión PostgreSQL.

**Regresión actual CU01–CU25: 4.676 aprobadas.**
**CU26 nuevo: 461 aprobadas. Total actual: 5.137.**
Las campañas citadas en CU25 son históricas: esta cifra corresponde a lo
ejecutado ahora y no se suma de nuevo a ellas. Las repeticiones de una misma
suite tampoco se suman.

Sintaxis: **295 archivos PHP y 10 JavaScript/MJS**, cero errores. Tras ajustar
el ejecutor Edge se verificaron nuevamente su sintaxis y el archivo HTTP
actualizado. No se ha compilado Docker ni cambiado su configuración.

## PostgreSQL y conservación

Pasaron los verificadores existentes CU15, CU23, CU24, CU25 y el inspector CU18,
todos en READ ONLY y con guarda loopback/clinica_vida_salud_dev/PostgreSQL 17.
Confirman **16 tablas/30 FK**, contrato completo 001–010, vínculo nullable/
FK RESTRICT/UNIQUE, cero conflictos de intervalos retenidos y cero duplicados
de eventos CU18.

No se ejecutó ninguna migración ni cambió tabla, trigger, estado, índice o GiST.
La comparación final de huellas privadas de las dieciséis tablas con el estado
anterior a las pruebas coincide exactamente en filas y contenido. Las siete
tablas clínicas están vacías; no quedan fixtures persistentes. Las pruebas
retiran únicamente sus IDs, sin vaciar tablas ni eliminar registros previos.
Las secuencias pueden avanzar por los fixtures o rollback.

## Reproducción

Solo sobre la base local de desarrollo ya migrada, con dependencias CU22,
Node con WebSocket y Edge instalado. No aplicar migraciones para probar CU26.

```powershell
php scripts/verificar-cu25-local.php
php scripts/inspeccionar-cu18-local.php
php tests/regresion_cu26.php --solo-cu26
# Campaña PHP completa, CU01–CU25 + CU26:
php tests/regresion_cu26.php
python tests/leer_pdf_cu22.py
powershell -NoProfile -File tests/render_preparacion.ps1
```

Para un navegador no compatible se mantienen texto, selector y enlaces manuales.
La prueba hablada para defensa debe ejecutarla una persona tras leer y aceptar
el aviso, elegir idioma, conceder permiso e indicar una frase del catálogo.
Ese procedimiento **sigue pendiente**; no se sustituye por las simulaciones.

## Inventario exacto

**18 archivos de CU26: 13 nuevos y 5 modificados**, frente al commit CU25.
Los cinco archivos pendientes anteriores quedan fuera de este inventario.

| Estado | Archivo |
|---|---|
| Nuevo | `app/Services/CatalogoVoz.php` |
| Modificado | `app/Views/layout.php` |
| Nuevo | `app/Views/voz/panel.php` |
| Nuevo | `docs/ciclo2/CU26.md` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU26.md` |
| Modificado | `docs/ciclo2/README.md` |
| Nuevo | `public/assets/css/voz.css` |
| Nuevo | `public/assets/js/comandos-voz.mjs` |
| Modificado | `README.md` |
| Nuevo | `tests/comandos_voz_browser.mjs` |
| Nuevo | `tests/comandos_voz_browser.php` |
| Nuevo | `tests/comandos_voz_validation.mjs` |
| Nuevo | `tests/comandos_voz_validation.php` |
| Nuevo | `tests/Cu26Ejecutar.php` |
| Nuevo | `tests/http_comandos_voz.php` |
| Modificado | `tests/README.md` |
| Nuevo | `tests/regresion_cu26.php` |

## Protección y límites

Se comprobaron huellas iniciales/finales de .env, Composer, Docker/.dockerignore,
migraciones 001–010 y los cinco archivos anteriores; todos permanecen intactos:

- app/Config/Database.php
- app/Config/DatabaseDiagnostic.php
- scripts/render-postgres.ps1
- tests/render_preparacion.ps1
- tests/database_render_validation.php

El árbol conserva esos cinco pendientes y los 18 archivos de CU26; el índice
está vacío. No hay credenciales, .env, vendor, audio, sesiones, logs, capturas,
artefactos o datos personales reales en el inventario. No se publica nada.
main sigue en `1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37`, sin acceso a
Render ni otros proyectos/bases. No se han creado migraciones 011/012.

Límites: reconocimiento real hablado pendiente; compatibilidad efectiva y
es-BO/es-ES dependen del navegador/proveedor; no se garantiza procesamiento local
ni offline; auditoría de accesibilidad básica. Se conservan las limitaciones
CU25 de recibo temporal sin auditoría persistente, selector de 100 cuentas y
construcción real Docker pendiente. No se implementan funciones de gestión
fuera de CU26.
