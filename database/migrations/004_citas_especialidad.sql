-- CU13 backend. Ejecutar solo con autorización, dentro de una transacción.
-- Guardar la especialidad elegida, sin inferirla a partir de relaciones cambiantes.
-- NULL conserva compatibilidad con citas anteriores y fixtures del esquema 001.
ALTER TABLE citas ADD COLUMN especialidad_id BIGINT;
ALTER TABLE citas ADD CONSTRAINT citas_especialidad_id_fkey
    FOREIGN KEY (especialidad_id) REFERENCES especialidades(id) ON DELETE RESTRICT;
-- Las nuevas reservas del servicio exigen especialidad activa y vinculada.
-- No referenciar la tabla puente: retirar una asociación no debe borrar el historial.
