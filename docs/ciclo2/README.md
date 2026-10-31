# Ciclo 2 — Clínica Vida Salud

Desarrollo local de **CU15 — Reprogramar citas médicas** y **CU16 — Cancelar citas médicas**.

- [Diseño y reglas CU15](CU15.md).
- [Verificación local compatible 8/10 y 9/12](CU15.md#verificación-compatible-de-esquema-cierre-8c): `php scripts/verificar-cu15-local.php` (solo lectura).
- [Mapa del código y referencias para UML](MAPA_DEL_CODIGO.md).
- [Informe de implementación y pruebas](PRUEBAS_CU15.md).
- [Diseño, reglas y migración CU16](CU16.md).
- [Pruebas e inventario CU16](PRUEBAS_CU16.md).

CU15 publicado en `feat/ciclo2-cu15-reprogramar-citas`, commit `03136dd`.
CU16 se prepara en `feat/ciclo2-cu16-cancelar-citas`, sin commit ni push.
Render sigue en `main`, con 001–004: no recibe migraciones o despliegues en este trabajo.
No se implementa CU17. La migración 006 fue autorizada y aplicada solo localmente.
El verificador admite estrictamente 001–004 (8/10), 001–005 (9/12) y 001–006 (9/12
con la columna y los CHECK de cancelaciones), sin aceptar instalaciones parciales.
Inventario histórico 8C: 33 archivos de CU15; inventario actual CU16 en su informe.
Los cinco archivos de diagnóstico previos permanecen separados y excluidos.
