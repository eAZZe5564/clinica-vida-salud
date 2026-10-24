# Implementación CU01–CU03

Actualización 6A, 23/10/2026: política de contraseñas nuevas centralizada en `PoliticaContrasena`, aplicada por UsuarioService tanto a creación/cambio como al primer administrador CLI. Errores concretos en formulario sin conservar la contraseña; login histórico intacto. Detalles y evidencia: [MEJORAS_6A.md](MEJORAS_6A.md).

## Modelo existente utilizado

La implementación consulta la tabla PostgreSQL existente `usuarios` (`id`, `rol_id`, `correo`, `hash_contrasena`, `activo`, `ultimo_acceso`, `creado_en`, `actualizado_en`) y la tabla `roles` (`id`, `nombre`, `descripcion`). El acceso de identificación es el correo porque no existe una columna de nombre de usuario. No se aplicó ninguna migración para CU01–CU03.

## Autenticación y sesiones

- `AuthService` busca el correo mediante PDO parametrizado y usa `password_verify()`. Los nuevos hashes se generan con `password_hash(PASSWORD_DEFAULT)`.
- Las respuestas de fallo no distinguen correo inexistente, contraseña incorrecta y usuario inactivo.
- El identificador de sesión se regenera tras autenticar. La sesión caduca tras 30 minutos de inactividad o 8 horas absolutas.
- La cookie usa `HttpOnly`, `SameSite=Lax` y `Secure` en HTTPS; además, `APP_ENV=production` obliga `Secure`.
- El middleware vuelve a consultar el estado y rol en PostgreSQL en cada ruta privada, por lo que una cuenta desactivada pierde acceso en su siguiente solicitud.
- El cierre es `POST /logout`, exige token CSRF, vacía la sesión y expira su cookie.

## Operaciones de usuarios

- Administradores listan y buscan hasta 100 cuentas, crean usuarios y asignan cualquiera de los cuatro roles existentes.
- Se permite modificar el correo, el rol y opcionalmente la contraseña; una contraseña vacía en edición conserva el hash existente.
- Activar/desactivar es lógico. No hay eliminación física ni editor de privilegios granulares.
- Se impide que un administrador se desactive a sí mismo y que un cambio quite el último administrador activo. Un advisory lock transaccional serializa los cambios de estado/rol que puedan afectar a la última cuenta administradora.
- Un administrador puede asignar el rol de administrador a otra cuenta; esto se limita al backend del rol administrador y no existe auto-registro público.
- Correos se normalizan a minúsculas y se validan. Desde 6A, las contraseñas nuevas/cambiadas exigen al menos 8 caracteres Unicode, mayúscula (Lu), minúscula (Ll), dígito decimal (Nd) y símbolo de puntuación o símbolo Unicode (P/S); los espacios no cuentan como símbolo. Se rechazan UTF-8 inválido y controles Cc. El máximo de 72 bytes evita truncamiento con PASSWORD_DEFAULT/bcrypt. Se conserva password_hash/password_verify. AuthService no aplica la nueva política al login y la edición con contraseña vacía mantiene el hash.

## Matriz de acceso

| Capacidad disponible en esta etapa | Administrador | Recepcionista | Médico | Paciente |
|---|---|---|---|---|
| Iniciar y cerrar sesión | Sí | Sí | Sí | Sí |
| Ver panel inicial | Sí | Sí | Sí | Sí |
| Buscar/listar usuarios | Sí | No | No | No |
| Crear usuario y asignar rol fijo | Sí | No | No | No |
| Cambiar datos y estado de usuario | Sí, con guardas del último administrador | No | No | No |
| Atender pacientes, médicos, horarios o citas | Pendiente | Pendiente | Pendiente | Pendiente |

Las autorizaciones se comprueban en `AuthMiddleware` y en el constructor de `UsuariosController`, no solo en la interfaz.

## Cuenta administradora inicial

Actualización de auditoría 5A: CU01–CU14 están implementados; la matriz de esta guía describe su etapa original de CU01–CU03. La matriz actual está en [REVISION_FINAL_CICLO1.md](../REVISION_FINAL_CICLO1.md). Se corrigió la navegación de CU03 pasando usuarioSesion desde UsuariosController al layout para no confundir al actor con la cuenta editada. El layout también valida que mensaje sea string antes de buscar avisos. Los permisos del backend y las reglas del último administrador se conservaron; las pruebas de regresión y la auditoría integral están aprobadas.

No se incluye ninguna cuenta de prueba. Antes de ejecutar la inicialización se debe obtener autorización explícita para crear una fila en `usuarios` con el correo indicado por el operador, rol administrador y hash de contraseña. El operador usa `.\scripts\crear-administrador.ps1`; el script solicita una contraseña oculta con `Read-Host -AsSecureString` y la pasa por entrada estándar al comando PHP CLI. El servicio rechaza la inicialización si ya existe cualquier cuenta. No hay endpoint web de instalación.
