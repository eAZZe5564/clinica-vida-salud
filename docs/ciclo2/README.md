# Ciclo 2 — Clínica Vida Salud

Desarrollo local de **CU15 — Reprogramar**, **CU16 — Cancelar** y **CU17 — Confirmar citas médicas**.

- [Diseño y reglas CU15](CU15.md).
- [Verificación local compatible 8/10 y 9/12](CU15.md#verificación-compatible-de-esquema-cierre-8c): `php scripts/verificar-cu15-local.php` (solo lectura).
- [Mapa del código y referencias para UML](MAPA_DEL_CODIGO.md).
- [Informe de implementación y pruebas](PRUEBAS_CU15.md).
- [Diseño, reglas y migración CU16](CU16.md).
- [Pruebas e inventario CU16](PRUEBAS_CU16.md).
- [Diseño, reglas y migración CU17](CU17.md).
- [Pruebas e inventario CU17](PRUEBAS_CU17.md).

CU15 publicado en `feat/ciclo2-cu15-reprogramar-citas`, commit `03136dd`.
CU16 publicado en `feat/ciclo2-cu16-cancelar-citas`, commit `431a861`.
CU17 se prepara en `feat/ciclo2-cu17-confirmar-citas`, sin commit ni push.
Render sigue en `main`, con 001–004: no recibe migraciones o despliegues en este trabajo.
No se implementa CU18. La migración 007 fue autorizada y aplicada solo localmente.
El verificador admite estrictamente 001–004 (8/10) y 001–005/006/007 (9/12),
con columnas, CHECK, GiST y función del trigger de la versión detectada, sin aceptar
instalaciones parciales. Programada y confirmada mantienen ocupado el turno.
Inventarios históricos de CU15 y CU16 en sus informes; inventario actual en CU17.
Los cinco archivos de diagnóstico previos permanecen separados y excluidos.
