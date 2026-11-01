# Ciclo 2 — Clínica Vida Salud

Desarrollo local de **CU15 — Reprogramar**, **CU16 — Cancelar**, **CU17 — Confirmar**
y **CU18 — Registrar llegada, atención e inasistencia**, con
**CU19 — Agenda médica diaria y semanal**, con **CU20 — Historial de citas por
paciente** en desarrollo local.

- [Diseño y reglas CU15](CU15.md).
- [Verificación local compatible 8/10 y 9/12](CU15.md#verificación-compatible-de-esquema-cierre-8c): `php scripts/verificar-cu15-local.php` (solo lectura).
- [Mapa del código y referencias para UML](MAPA_DEL_CODIGO.md).
- [Informe de implementación y pruebas](PRUEBAS_CU15.md).
- [Diseño, reglas y migración CU16](CU16.md).
- [Pruebas e inventario CU16](PRUEBAS_CU16.md).
- [Diseño, reglas y migración CU17](CU17.md).
- [Pruebas e inventario CU17](PRUEBAS_CU17.md).
- [Diseño, permisos y migración CU18](CU18.md).
- [Pruebas e inventario CU18](PRUEBAS_CU18.md).
- [Diseño y permisos CU19](CU19.md).
- [Pruebas e inventario CU19](PRUEBAS_CU19.md).
- [Diseño, permisos y límite del portal Paciente CU20](CU20.md).
- [Pruebas e inventario CU20](PRUEBAS_CU20.md).

CU15 publicado en `feat/ciclo2-cu15-reprogramar-citas`, commit `03136dd`.
CU16 publicado en `feat/ciclo2-cu16-cancelar-citas`, commit `431a861`.
CU17 publicado en `feat/ciclo2-cu17-confirmar-citas`, commit `078272a`.
CU18 publicado en `feat/ciclo2-cu18-asistencia-citas`, commit `171c619`.
CU19 publicado en `feat/ciclo2-cu19-agenda-medica`, commit `09de4d9`.
CU20 se prepara desde CU19 en `feat/ciclo2-cu20-historial-paciente`, sin commit ni push.
No se consultó ni modificó Render; `main` permanece intacta.
No se implementa CU21. CU19/CU20 no requieren migración; 008 ya estaba aplicada localmente.
El verificador admite estrictamente 001–004 (8/10) y 001–005/006/007/008 (9/12),
con columnas, CHECK, GiST y función del trigger de la versión detectada, sin aceptar
instalaciones parciales. Programada, confirmada, presente, atendida e inasistencia
conservan el intervalo; solo cancelada lo libera. CU11 conserva la cobertura de
los intervalos que aún no terminan. Llegada no equivale a atención finalizada.
Los informes CU15–CU19 conservan sus inventarios y resultados históricos.
Inventario y pruebas ejecutadas ahora:
CU20. Agenda e historial son de consulta, sin modificación de citas. El rol
Paciente continúa sin portal propio; la limitación y propuesta futura están documentadas.
Los cinco archivos de diagnóstico previos permanecen separados y excluidos.
