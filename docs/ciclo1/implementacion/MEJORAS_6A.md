# Mejoras 6A: búsqueda de pacientes y contraseñas

Entrega local verificada el 23/10/2026. Continúa el Ciclo 1, sin iniciar el Ciclo 2.

## Búsqueda flexible

`BusquedaPaciente` normaliza separaciones y espacios repetidos, limita la entrada a 100 caracteres Unicode y elimina términos repetidos. Una entrada vacía, de tipo incorrecto, UTF-8 inválido o con controles no admitidos conserva el listado habitual.

`PacienteRepository::listar()` consulta las columnas reales `nombres`, `apellidos` y `documento_identidad`. Cada término puede aparecer parcialmente en cualquiera de las tres columnas; todos deben coincidir en la misma fila. Se combinan los campos con OR y los términos con AND. No se exige orden ni continuidad entre palabras.

La comparación ignora mayúsculas y pliega las vocales españolas con tilde y la ü, incluidas sus formas combinadas. La ñ sigue siendo distinta de n. Se usa `translate(lower(...))` de PostgreSQL, sin instalar extensiones ni migrar el esquema. El CI conserva la compatibilidad con separaciones históricas.

Cada condición tiene un parámetro PDO diferente. `%`, `_` y `!` se escapan para LIKE; la barra inversa es literal porque se declara `ESCAPE '!'`. La entrada no se interpola en SQL. Conteo y resultados usan el mismo filtro: dos consultas por listado, sin N+1. Se conservan las páginas de 20 filas, la búsqueda al navegar, los filtros de activos y los permisos. Los selectores que reutilizan este repositorio reciben la misma búsqueda; no se cambia la lógica de reservas.

Con pacientes ficticios se comprobaron `María`, `Pérez`, `1234567`, `María Pérez`, `Pérez María`, `María 1234567`, `1234567 Pérez` y combinaciones de los tres términos en diferentes órdenes. También se probaron coincidencias parciales, ausencia de tildes, espacios repetidos, términos de pacientes diferentes sin coincidencia falsa, paginación, comodines literales, SQL Injection y permisos.

## Contraseñas

`PoliticaContrasena::validar()` centraliza las cinco condiciones en PHP:

1. Al menos 8 caracteres Unicode.
2. Una letra mayúscula, categoría Unicode `Lu`.
3. Una letra minúscula, categoría `Ll`.
4. Un dígito decimal, categoría `Nd`.
5. Un signo de puntuación o símbolo, categorías `P` o `S`.

Los espacios no cuentan como símbolos. No se recorta ni transforma la contraseña. Se exige UTF-8 válido, se rechazan caracteres de control y se conserva el máximo de 72 bytes para impedir el truncamiento de bcrypt. Ese máximo puede representar menos de 72 caracteres cuando se usa Unicode.

`UsuarioService` aplica esta política al alta administrativa, al cambio explícito y a la primera cuenta por CLI. Se conservan `password_hash()` y `password_verify()`. No existen otros mecanismos de recuperación que requieran cambios. El login no impone la política nueva a las cuentas históricas y editar con la contraseña vacía conserva exactamente su hash.

`ContrasenaValidacionException` transporta únicamente los requisitos incumplidos. El formulario administrativo devuelve HTTP 422, muestra esos requisitos y conserva correo y rol, sin recuperar ni reflejar la contraseña. El asistente PowerShell informa la misma política y mantiene la lectura segura existente. La validación obligatoria funciona sin JavaScript.

## Archivos de la entrega

Archivos nuevos de aplicación:

- `app/Models/BusquedaPaciente.php`.
- `app/Services/PoliticaContrasena.php`.
- `app/Services/ContrasenaValidacionException.php`.

Archivos de aplicación o instalación modificados:

- `app/Controllers/PacientesController.php` y `UsuariosController.php`.
- `app/Repositories/PacienteRepository.php`.
- `app/Services/UsuarioService.php`.
- `app/Views/pacientes/index.php` y `app/Views/admin/users/form.php`.
- `scripts/crear-administrador.ps1`.

Pruebas nuevas:

- `tests/mejoras_6a_validation.php`.
- `tests/mejoras_6a_integration.php`.
- `tests/http_mejoras_6a.php`.
- `tests/mejoras_6a_browser.php` y `tests/mejoras_6a_browser.mjs`.

Se ajustaron exclusivamente las contraseñas ficticias en 14 archivos de pruebas existentes, conservando sus aserciones: `HorariosFixture.php`, `auditoria_ciclo1_http.php`, `auth_users_integration.php`, `citas_browser.php`, `consulta_citas_browser.php`, `http_auth_integration.php`, `http_citas_formulario.php`, `http_citas_integration.php`, `http_consulta_citas.php`, `http_disponibilidad_integration.php`, `http_horarios_integration.php`, `http_medicos_integration.php`, `http_pacientes_integration.php` y `medicos_integration.php`, todos bajo `tests/`.

Documentación actualizada: README principal, mapa del código, índice de implementación, documentos de pacientes y autenticación/usuarios, registro de pruebas, `tests/README.md` y este informe.

## Verificación ejecutada

| Comprobación | Aprobadas |
|---|---:|
| Nuevas validaciones sin base | 43 |
| Nueva integración con rollback | 46 |
| Nuevas pruebas HTTP y CLI | 62 |
| Nuevas pruebas Edge de escritorio y móvil | 14 |
| Total nuevo | 165 |
| Regresión completa CU01–CU14: 22 suites | 838 |
| Total distinto de esta ejecución | 1003 |

Sintaxis correcta en 123 archivos PHP y cuatro scripts JS/MJS. No se suman repeticiones de las mismas comprobaciones. Las cuatro capturas reales de escritorio/móvil están en `tests/artifacts/mejoras-6a/`, ignoradas por Git; se inspeccionaron búsqueda, mensajes y ausencia de desbordamiento horizontal.

Se comprobó el rechazo de claves inválidas desde el script PHP inicial y que una clave válida conserva la guarda de base con usuarios. No se creó una base vacía para repetir el alta inicial completa ni se ejecutó `auth_users_integration.php`, que requiere usuarios vacíos. No se vaciaron tablas para satisfacer esa precondición. Una cuenta histórica ficticia, válida bajo la longitud anterior pero sin símbolo, verifica el login compatible.

Las pruebas usaron únicamente `clinica_vida_salud_dev`. Conteos y huellas de contenido de las ocho tablas se compararon antes y después: iguales. Las pruebas revierten o retiran exclusivamente sus fixtures; las secuencias pueden avanzar. No se cambiaron migraciones, extensiones, configuración, duración de citas, vinculación de cuentas, concurrencia ni los módulos de horarios, disponibilidad y reservas.

## Comprobación manual

Desde la raíz, inicia `php -S 127.0.0.1:8001 -t public` y conserva abierta esa terminal. El puerto 8000 estaba ocupado por un servidor Django de otro proyecto; no se detuvo ese proceso. Abre <http://127.0.0.1:8001/login>.

- Como administrador o recepcionista, abre `/pacientes` y combina nombre, apellido y CI de un paciente local, cambiando el orden y usando espacios repetidos. Comprueba el filtro y las páginas.
- Como administrador, abre `/admin/users/new`. Revisa la ayuda e intenta una contraseña con 8 caracteres a la que falte una condición; el servidor indica cuál. Para probar el mínimo con el navegador, evita usar menos de 8, pues el atributo HTML también lo impide.
- Crea únicamente una cuenta de prueba autorizada con las cinco condiciones. Edita dejando la contraseña vacía y verifica que sigue admitiendo la misma; un cambio explícito exige la política nueva. No cambies cuentas reales solo para probar.

No se hizo commit ni push. Los cambios quedan locales para revisión. No se detectaron fallos funcionales pendientes en las pruebas ejecutadas; la instalación limpia y el despliegue no se ensayaron en esta entrega.
