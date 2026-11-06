# CU25 — Implementación y pruebas del portal Paciente

## Estado final local

CU25 implementado en `feat/ciclo2-cu25-portal-paciente`, desde CU24
`13d204d10632eb20d3707379b1f65cbe7aa0a026`. HEAD permanece en ese commit;
no se ejecutan git add, commit, push, merge ni PR. main conserva su referencia
inicial; no se accede a Render ni se desarrolla CU26.

Se reutiliza pacientes.usuario_id nullable, BIGINT, FK RESTRICT a usuarios y
UNIQUE, existente desde 001. No hay migración 011 ni cambios de esquema.
La vinculación inicial por Administrador fue expresamente autorizada para
implementar y probar con datos ficticios. Reglas y rutas: [CU25.md](CU25.md).

## Pruebas específicas ejecutadas ahora

| Suite | Aprobadas | Alcance |
|---|---:|---|
| portal_paciente_integration | 98 | Identidad activa, cuentas sin vínculo, Administrador exclusivo, confirmación, no sustitución, duplicados, rol/actividad, parámetros, fechas, reserva, ocupación, CU15/CU16, estados y páginas |
| portal_paciente_concurrency | 14 | Procesos PDO independientes, barreras reales observadas en pg_stat_activity, dos cuentas/un paciente, dos pacientes/una cuenta, una reserva por turno, reprogramación frente a cancelación y revalidación tras desactivación |
| http_portal_paciente | 193 | HTTP real por ruta/método/rol, anónimo, CSRF, IDOR, XSS, parámetros, PRG, estado/botones, no caché/CSP y vínculo inicial administrativo |
| portal_paciente_privacidad | 54 | Datos clínicos ficticios reales excluidos de proyecciones/HTTP, rutas clínicas denegadas, FK/UNIQUE reales, transacciones incompatibles y rollback de cita/historial y vínculo |
| portal_paciente_browser | 87 | Edge real 1440/820/390 px: reservas, reprogramaciones, cancelaciones, eventos y vínculos; sin desbordamiento, capturas privadas o solicitudes ajenas |
| **Total específico CU25** | **446** | Sin sumar intentos fallidos ni repeticiones |

Se ejecutó `php tests/regresion_cu25.php --solo-cu25`: las cinco suites
terminaron con salida 0. Edge confirmó tres reservas propias reales,
reprogramadas y canceladas, y tres vínculos iniciales de identidades inventadas;
todas las filas de prueba se retiraron después.

La vinculación exige confirmación confiable de identidad: las coincidencias
de CI/nombre/correo/teléfono se rechazan como elección de identidad en el portal.
No hay autovinculación ni desvinculación/reasignación. En las carreras solo
una operación confirma y la otra recibe 409. El usuario/paciente se revalidan
tras esperar bloqueos. Ninguna cuenta real fue vinculada en estas pruebas.

Se probó un fallo después de UPDATE del vínculo mediante un PDO delegado:
rollback restaura usuario_id NULL. Los fallos de inserción de historial
restauran cita y eventos. Las pruebas no alteran funciones, triggers o GiST
para simular errores ni usan datos existentes como fixtures.

## Regresión CU01–CU24 ejecutada ahora

La cobertura de la campaña existente se ejecutó nuevamente, completando sus
suites mediante los comandos y reanudaciones descritos abajo:

| Grupo | Aprobadas |
|---|---:|
| Regresión PHP CU01–CU22 | 2.848 |
| Suites específicas PHP CU23 | 504 |
| Suites específicas PHP CU24 | 793 |
| **Subtotal PHP CU01–CU24** | **4.145** |
| Lector PDF real pypdf | 51 |
| PowerShell de preparación, sin red ni PostgreSQL | 34 |
| **Regresión CU01–CU24 actual** | **4.230** |
| CU25 específico PHP/Edge/HTTP | 446 |
| **Total actual, sin duplicar repeticiones** | **4.676** |

El ejecutor completo se detuvo inicialmente en cancelacion_integration:
la extensión de autorización había cambiado a 404 el rechazo de un Paciente
sin vínculo. Se restauró el 403 anterior; los vinculados que piden citas ajenas
reciben 404. La nueva ejecución pasó de CU01 hasta todas las suites de CU23
anteriores al navegador, incluido CU22 con sus 2.848 comprobaciones.

En esa ejecución hubo un fallo puntual del navegador CU23 que no dejó detalle
en la salida del ejecutor. No se demostró una causa ni se modificó CU23 para
ocultarlo. La repetición aislada pasó (42), y también pasó su ejecutor habitual:
`php tests/regresion_cu23.php perfil_clinico_browser` (42, salida 0).
No se suman ambas repeticiones. Se completó CU24 con
`php tests/regresion_cu24.php --solo-cu24` (793, salida 0).
Por tanto, no se afirma que un único comando anidado haya terminado sin
interrupciones: las cifras se consolidan con los resultados finales de cada
suite ejecutada ahora. No quedan casos reproducibles fallidos.

`python tests/leer_pdf_cu22.py` aprobó 51 y
`powershell -NoProfile -File tests/render_preparacion.ps1` aprobó 34.
La campaña anterior de 4.230 citada en CU24 es histórica; la tabla de arriba
corresponde a esta ejecución y no se suma otra vez a ella.

## Esquema, sintaxis y conservación

Pasaron los verificadores CU15, CU23, CU24, CU25 e inspector CU18 en READ ONLY:
16 tablas/30 FK, contratos completos de 001–010, vínculo nullable/FK RESTRICT/
UNIQUE, cero conflictos de intervalos retenidos y cero eventos CU18 duplicados.
No se aplicó ninguna migración.

Sintaxis final: **288 archivos PHP y 7 JavaScript/MJS**, cero errores.
Docker solo se revisa mediante las pruebas estáticas existentes; no se compila
ni se despliega y su construcción real continúa pendiente.

Se compararon huellas privadas de las dieciséis tablas con el estado anterior
al primer fixture de CU25: coincidencia exacta de filas y contenido.
Las siete tablas clínicas quedaron vacías. Los fixtures de usuarios, pacientes,
médicos, especialidades, horarios, citas e historiales también se retiraron por
sus IDs; no se vaciaron tablas ni se desactivaron restricciones. Las secuencias
pueden avanzar después de pruebas y rollback.

No se detectaron secretos en los archivos de CU25 al revisar contenido,
patrones de credenciales/claves y coincidencias con la contraseña local cargada,
sin imprimir ningún valor secreto. .env, vendor, sesiones, logs y artefactos
siguen excluidos; no se preparó el índice de Git. No se incorporan datos
personales o clínicos reales ni capturas médicas.

## Reproducción

Requiere PostgreSQL 17 local clinica_vida_salud_dev, 001–010 previamente
aplicadas y dependencias CU22 instaladas. Cu15Local bloquea destinos externos.
No aplicar migraciones para ejecutar estas pruebas.

```powershell
php scripts/verificar-cu25-local.php
php tests/regresion_cu25.php --solo-cu25
# Campaña completa, incluye CU01–CU24:
php tests/regresion_cu25.php
python tests/leer_pdf_cu22.py
powershell -NoProfile -File tests/render_preparacion.ps1
```

Una suite específica se puede ejecutar con su nombre permitido, por ejemplo
`php tests/regresion_cu25.php portal_paciente_concurrency`.
Las escrituras funcionales usan exclusivamente fixtures ficticios, limpieza en
finally y huellas. Los diagnósticos locales están ignorados en
tests/artifacts/cu25; no publicar logs, sesiones, capturas ni esos artefactos.
El ejecutor Cu25Ejecutar usa un archivo PHP y argumentos estructurados para
evitar código multilínea en argumentos de Windows.

## Inventario exacto de CU25

**39 archivos propios: 26 nuevos y 13 modificados**, frente al HEAD de CU24.
Este inventario reemplaza los tres archivos de preparación inicial.

| Estado | Archivo |
|---|---|
| Nuevo | `app/Config/RutasPortal.php` |
| Nuevo | `app/Controllers/PortalPacienteController.php` |
| Nuevo | `app/Controllers/VinculacionesController.php` |
| Modificado | `app/Repositories/CitaRepository.php` |
| Nuevo | `app/Repositories/PortalPacienteRepository.php` |
| Nuevo | `app/Services/AccesoCitaPaciente.php` |
| Modificado | `app/Services/CancelacionService.php` |
| Modificado | `app/Services/CitaService.php` |
| Modificado | `app/Services/ConsultaCitaService.php` |
| Nuevo | `app/Services/FiltrosCita.php` |
| Nuevo | `app/Services/PortalPacienteService.php` |
| Modificado | `app/Services/ReprogramacionService.php` |
| Nuevo | `app/Services/VinculacionPacienteService.php` |
| Modificado | `app/Views/dashboard.php` |
| Modificado | `app/Views/layout.php` |
| Modificado | `app/Views/pacientes/show.php` |
| Nuevo | `app/Views/portal/detalle.php` |
| Nuevo | `app/Views/portal/error.php` |
| Nuevo | `app/Views/portal/formulario.php` |
| Nuevo | `app/Views/portal/index.php` |
| Nuevo | `app/Views/portal/vincular.php` |
| Nuevo | `docs/ciclo2/CU25.md` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU25.md` |
| Modificado | `docs/ciclo2/README.md` |
| Nuevo | `public/assets/css/portal.css` |
| Modificado | `public/index.php` |
| Modificado | `README.md` |
| Nuevo | `scripts/verificar-cu25-local.php` |
| Nuevo | `tests/Cu25Ejecutar.php` |
| Nuevo | `tests/http_portal_paciente.php` |
| Nuevo | `tests/portal_paciente_browser.mjs` |
| Nuevo | `tests/portal_paciente_browser.php` |
| Nuevo | `tests/portal_paciente_concurrency.php` |
| Nuevo | `tests/portal_paciente_integration.php` |
| Nuevo | `tests/portal_paciente_privacidad.php` |
| Nuevo | `tests/PortalPacientePrueba.php` |
| Modificado | `tests/README.md` |
| Nuevo | `tests/regresion_cu25.php` |

## Protección y limitaciones

Se compararon las huellas iniciales y finales de estos cinco archivos anteriores,
que siguen presentes y fuera del inventario de CU25:

- app/Config/Database.php
- app/Config/DatabaseDiagnostic.php
- scripts/render-postgres.ps1
- tests/render_preparacion.ps1
- tests/database_render_validation.php

Los cinco permanecen intactos, al igual que .env, migraciones 001–010, Composer,
Docker y .dockerignore. main sigue en
`1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37`; no hay operaciones sobre Render.
El árbol conserva CU25 y los cinco pendientes anteriores, con índice vacío.

No existe auditoría persistente apropiada del vínculo: el recibo muestra actor
y momento una vez en la sesión, sin convertirlo en una bitácora. La fecha de
actualización del paciente no conserva el administrador responsable. Esta
limitación es explícita tanto aquí como en el formulario; no se usan historias
clínicas ni logs técnicos como sustituto. El selector muestra hasta 100 cuentas
activas sin vínculo. La verificación de identidad sigue siendo una responsabilidad
humana confiable, no una coincidencia textual ni un proceso documental automático.

El portal no da acceso a información clínica; las acciones disponibles conservan
reglas y estados de CU12–CU18. Sin producción, pagos, notificaciones, adjuntos,
cuentas permanentes de prueba o desarrollo de CU26.
