# Ciclo 2 — Clínica Vida Salud

CU26 está implementado localmente en `feat/ciclo2-cu26-comandos-voz`, desde
CU25 `8e3fd8f`, sin commit/push de CU26. Agrega navegación opcional mediante
comandos cerrados de voz o texto y enlaces existentes, sin escribir datos.
Consulta [CU26](CU26.md) y [pruebas e inventario](PRUEBAS_CU26.md) para distinguir
las simulaciones de reconocimiento del micrófono real. No hay migraciones nuevas:
se conservan 16 tablas/30 FK y las limitaciones previas de CU25.

Desarrollo local de **CU15 — Reprogramar**, **CU16 — Cancelar**, **CU17 — Confirmar**
y **CU18 — Registrar llegada, atención e inasistencia**, con
**CU19 — Agenda médica diaria y semanal**, con **CU20 — Historial de citas por
paciente**, **CU21 — Reportes de citas médicas**, **CU22 — Exportación PDF/CSV**
**CU23 — Perfil clínico básico y alergias** y **CU24 — Antecedentes y laboratorio**
en desarrollo local.

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
- [Diseño, filtros y fórmulas CU21](CU21.md).
- [Pruebas e inventario CU21](PRUEBAS_CU21.md).
- [Diseño y dependencias CU22](CU22.md).
- [Pruebas e inventario CU22](PRUEBAS_CU22.md).
- [Perfil clínico, permisos y esquema CU23](CU23.md).
- [Pruebas e inventario CU23](PRUEBAS_CU23.md).
- [Antecedentes, laboratorio y auditoría CU24](CU24.md).
- [Pruebas e inventario CU24](PRUEBAS_CU24.md).

CU15 publicado en `feat/ciclo2-cu15-reprogramar-citas`, commit `03136dd`.
CU16 publicado en `feat/ciclo2-cu16-cancelar-citas`, commit `431a861`.
CU17 publicado en `feat/ciclo2-cu17-confirmar-citas`, commit `078272a`.
CU18 publicado en `feat/ciclo2-cu18-asistencia-citas`, commit `171c619`.
CU19 publicado en `feat/ciclo2-cu19-agenda-medica`, commit `09de4d9`.
CU20 publicado en `feat/ciclo2-cu20-historial-paciente`, commit `866839e`.
CU21 publicado en `feat/ciclo2-cu21-reportes-citas`, commit `3415743`.
CU22 publicado en `feat/ciclo2-cu22-exportar-reportes`, commit `58ab187`.
No se consultó ni modificó Render; `main` permanece intacta.
CU23 publicado en `feat/ciclo2-cu23-perfil-clinico-alergias`, commit `f3d275c`.
CU24 permanece local, con 010 aplicada y pendiente de guardar en Git.
CU19–CU22 no requieren migración; 008 ya estaba aplicada localmente.
El verificador admite estrictamente 001–004 (8/10), 001–005/006/007/008 (9/12)
001–009 (12/20) y 001–010 (16/30),
con columnas, CHECK, GiST y función del trigger de la versión detectada, sin aceptar
instalaciones parciales. Programada, confirmada, presente, atendida e inasistencia
conservan el intervalo; solo cancelada lo libera. CU11 conserva la cobertura de
los intervalos que aún no terminan. Llegada no equivale a atención finalizada.
Los informes CU15–CU23 conservan sus inventarios y resultados históricos.
Inventario y comprobaciones actuales: CU24.
Agenda, historial, reportes y exportaciones son de consulta, sin modificación de citas. El rol
Paciente continúa sin portal propio; la limitación y propuesta futura están documentadas.
Los cinco archivos de diagnóstico previos permanecen separados y excluidos.

- [Portal Paciente, identidad y límites CU25](CU25.md).
- [Pruebas e inventario CU25](PRUEBAS_CU25.md).

- [Comandos de voz, roles y privacidad CU26](CU26.md).
- [Pruebas e inventario CU26](PRUEBAS_CU26.md).
