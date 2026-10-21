# GitHub y trabajo entre dos integrantes — Clínica Vida Salud

Guía del Prompt 5B. Este repositorio corresponde exclusivamente a Clínica Vida Salud. CU01–CU14 y la auditoría 5A están completos; no se agregan funcionalidades del Ciclo 2. El usuario autorizó el primer commit local y decidió que GitHub será público. La conexión remota y el primer push siguen pendientes de la URL real y de una autorización separada.

## 1. Crear el repositorio y compartirlo

1. En GitHub, abrir el menú `+` → **New repository**. Elegir al integrante que será propietario.
2. Usar el nombre **clinica-vida-salud** y una descripción breve del proyecto académico.
3. Seleccionar **Public**, como decidió el usuario para permitir la consulta del código por la ingeniera y los compañeros mediante URL o QR. El propietario y el compañero trabajarán con sus propias cuentas; la publicación no debe incluir secretos ni datos médicos reales.
4. Crear un repositorio **vacío**, sin generar README, .gitignore, licencia, plantilla ni código inicial en GitHub: esos archivos ya se preparan localmente y así se evita crear dos historiales iniciales.
5. Copiar la URL real desde GitHub y proporcionarla para preparar origin. No utilizar una URL inventada ni agregar una contraseña/token dentro de ella.
6. En el repositorio, **Settings → Collaborators → Add people**, buscar la cuenta real del compañero y enviar la invitación. El compañero debe aceptarla. No compartir la contraseña de GitHub ni la cuenta del propietario.

En un repositorio personal, un colaborador puede escribir en el repositorio: acuerden revisar los cambios mediante Pull Requests. No hace falta crear una organización, automatizaciones o reglas complejas para dos estudiantes. Pueden adoptar la regla de equipo de no publicar cambios de trabajo directamente en main, sin depender de funciones de pago.

Fuentes oficiales: [crear repositorio](https://docs.github.com/en/repositories/creating-and-managing-repositories/creating-a-new-repository), [invitar colaboradores](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/repository-access-and-collaboration/inviting-collaborators-to-a-personal-repository).

## 2. Visibilidad, ingeniera, URL y QR

La visibilidad del código y el acceso a la aplicación son decisiones diferentes. Una aplicación desplegada puede tener una URL pública aunque su código siga privado, si el alojamiento dispone de acceso autorizado al repositorio.

El **repositorio público** elegido permitirá a la ingeniera y los compañeros consultar el código sin una invitación de colaboración. Invitar al compañero de desarrollo sigue siendo necesario para que pueda publicar ramas y contribuir. El QR del código podrá apuntar a la URL real del repositorio cuando exista; no debe incluir credenciales.

El QR de la **aplicación** debe apuntar a su URL HTTPS de despliegue, preferiblemente la entrada/login, sin contraseñas ni tokens. El sistema conserva autenticación por roles; preparar cualquier cuenta ficticia de presentación por un canal privado y con consentimiento. Comprobar la URL y el QR desde otro teléfono/red antes de la presentación.

Subir el repositorio no ejecuta PHP. GitHub Pages aloja sitios estáticos y no ejecutará este backend PHP/PostgreSQL. El alojamiento se elegirá por separado. Fuentes: [visibilidad](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/managing-repository-settings/setting-repository-visibility), [GitHub Pages](https://docs.github.com/en/pages/getting-started-with-github-pages/what-is-github-pages).

## 3. Primer commit y publicación inicial

La rama local es main y el índice fue revisado. El usuario ya autorizó el primer commit con el siguiente mensaje; el primer push sigue requiriendo autorización expresa en un paso separado:

```text
feat: implementar CU01-CU14 de Clínica Vida Salud (Ciclo 1)
```

Antes del commit, revisar `git status`, `git diff --cached --name-only`, `git diff --cached --stat` y los cambios concretos con `git diff --cached`. La revisión del índice se documenta en [PREPARACION_GITHUB.md](PREPARACION_GITHUB.md). `.env` y sus secretos no forman parte del índice.

Solo después de autorizar el primer commit se podrá ejecutar:

```powershell
git commit -m "feat: implementar CU01-CU14 de Clínica Vida Salud (Ciclo 1)"
```

Cuando exista la URL real y el commit local, verificar primero `git remote -v`. Si todavía no hay origin, los comandos previstos son:

```powershell
$urlRepositorio = Read-Host 'Pega la URL real del repositorio vacío creado en GitHub'
git remote add origin "$urlRepositorio"
git remote -v
git ls-remote origin
```

Estas instrucciones no configuran origin automáticamente. Verificar que la URL corresponda a Clínica Vida Salud. Si origin ya existe y apunta a otro proyecto, detenerse y revisarlo; no reemplazarlo sin comprobarlo. `git ls-remote origin` debe terminar correctamente; un repositorio vacío puede devolver una lista vacía. Si aparecen ramas/commits inesperados, revisar el contenido antes de publicar.

Tras comprobar destino, autenticación, índice/commit y recibir autorización expresa para el primer push:

```powershell
git push -u origin main
```

Publicar únicamente main; no usar push --mirror ni publicar todas las referencias locales. Las referencias internas de herramientas y la carpeta .git no son parte del código que se entrega. Autenticarse mediante el administrador de credenciales o una clave SSH configurada en la cuenta, sin poner tokens en comandos, documentos, URLs o .env.example.

## 4. Clonar después de la publicación inicial

El compañero primero acepta la invitación si el repositorio es privado. En una carpeta distinta de otros proyectos, usando la URL real que muestra GitHub:

```powershell
$urlRepositorio = Read-Host 'Pega la URL real de Clínica Vida Salud'
git clone "$urlRepositorio" clinica-vida-salud
Set-Location clinica-vida-salud
git branch --show-current
git remote -v
git config --get user.name
git config --get user.email
```

Cada integrante usa su propia identidad de autor. Si falta o corresponde a otra persona, configurarla solo en este repositorio con datos reales; el correo noreply proporcionado por GitHub permite evitar publicar un correo personal:

```powershell
$nombreAutor = Read-Host 'Tu nombre de autor de Git'
$correoAutor = Read-Host 'Tu correo de autor, preferiblemente el noreply de tu cuenta GitHub'
git config --local user.name "$nombreAutor"
git config --local user.email "$correoAutor"
```

No copiar la identidad del compañero ni cambiar la configuración global de otros proyectos. La identidad de autor no sustituye la autenticación necesaria para clonar o publicar.

## 5. Configurar PHP, .env y PostgreSQL en la otra computadora

Requisitos: PHP 8.4 en PATH con PDO_PGSQL, mbstring y sesiones; PostgreSQL 17 y cliente psql, con btree_gist disponible. Confirmar `php -v`, `php --ini`, `php --ri pdo_pgsql`, `php --ri mbstring` y `psql --version`. No se necesita Composer. La guía detallada de instalación de PHP/PostgreSQL está en [README](../../README.md).

Copiar la plantilla solo si no existe configuración local:

```powershell
if (-not (Test-Path -LiteralPath .env)) {
    Copy-Item -LiteralPath .env.example -Destination .env
}
git check-ignore .env
```

Editar .env únicamente en la computadora propia. Configurar DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD según la instalación local, con APP_ENV=local y APP_TIMEZONE=America/La_Paz. La contraseña de PostgreSQL de un integrante puede ser diferente de la del otro. No compartir el archivo .env, publicarlo, enviarlo por chat ni copiarlo al directorio public. PGSSLMODE y PGSSLROOTCERT están documentadas como opciones comentadas para un futuro alojamiento remoto; no activarlas contra una instalación local sin TLS/CA.

**Solo en una instalación nueva autorizada**, confirmar que clinica_vida_salud_dev no exista. Cada integrante tendrá su propia base local; no se comparte el contenido de la base mediante Git. No ejecutar createdb ni migraciones otra vez en la computadora donde ya están aplicadas. Con permisos para crear la base e instalar btree_gist, desde la raíz:

```powershell
createdb -h 127.0.0.1 -p 5432 -U postgres -W -O postgres clinica_vida_salud_dev
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\migrations\001_esquema_ciclo1.sql
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\migrations\002_pacientes_cu04_cu06.sql
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\migrations\003_medicos_especialidades.sql
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\migrations\004_citas_especialidad.sql
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\seeds\001_datos_base.sql
```

La contraseña se solicita interactivamente. Comprobar el resultado de cada comando y detenerse si falla; no seguir con la siguiente migración ni borrar una base para resolverlo. Las migraciones no son reejecutables. El seed solo contiene cuatro roles y tres especialidades, sin cuentas, pacientes, médicos ni citas.

En esa instalación nueva, con usuarios vacíos y después de autorizar la cuenta local inicial:

```powershell
.\scripts\crear-administrador.ps1
```

El asistente solicita correo y contraseña oculta, utiliza stdin y guarda un hash. No escribir la contraseña en el repositorio ni como argumento. No se puede repetir si ya existen cuentas.

Iniciar la aplicación desde la raíz:

```powershell
php -S 127.0.0.1:8000 -t public
```

Abrir http://127.0.0.1:8000/login. El servidor usa únicamente public como raíz. Las pruebas se explican en [tests/README.md](../../tests/README.md); ejecutarlas sobre desarrollo local preparado, sin vaciar tablas ni apuntar a producción. La auditoría 5A aprobó 838 comprobaciones; preparar GitHub no representa una nueva ejecución de esa batería.

## 6. Trabajo diario con una main estable

Acuerden tareas pequeñas y avisen si ambos necesitan editar el mismo archivo. Utilicen main para versiones revisadas y una rama por tarea, por ejemplo feature/guia-presentacion o fix/validacion-existente. No se necesita una rama develop.

Con el árbol limpio y después de la publicación inicial:

```powershell
git status
git switch main
git pull --ff-only origin main
git switch -c feature/guia-presentacion
```

Después de editar, revisar y ejecutar las verificaciones adecuadas a la tarea. Preparar únicamente los archivos de esa tarea (sustituir los nombres por rutas reales):

```powershell
git status --short
git diff
git add -- README.md
git diff --cached --name-only
git diff --cached
git commit -m "docs: actualizar guía de presentación"
git push -u origin feature/guia-presentacion
```

El ejemplo solo modifica documentación; no indica desarrollar nuevas funcionalidades. Evitar git add -f para .env o artefactos. Si se necesita cambiar el esquema, preparar una migración nueva y revisarla juntos; no editar silenciosamente una migración ya aplicada ni ejecutar cambios de base sin autorización. Cada integrante aplica únicamente lo pendiente en su base local.

## 7. Pull Requests y revisión del compañero

1. Tras publicar la rama, abrir **Pull requests → New pull request** en GitHub.
2. Elegir base main y compare la rama de la tarea. Describir problema, cambio y verificaciones realmente ejecutadas.
3. Solicitar revisión al compañero. El revisor abre Files changed, comprueba alcance, secretos y efectos en otros casos de uso y prueba localmente cuando corresponda.
4. Corregir observaciones en la misma rama y publicar nuevos commits; el PR se actualiza.
5. Cuando el compañero apruebe y las verificaciones estén bien, integrar el PR en main desde GitHub. Elegir una forma de integración habitual y mantenerla consistente; no activar automatizaciones innecesarias.

No integrar si hay conflictos sin resolver o fallos pendientes. Si hay que probar una rama del compañero, guardar antes los cambios propios, hacer fetch y cambiar a una rama local que siga esa rama remota. Compartir el código mediante Git; cada uno conserva .env y PostgreSQL propios.

## 8. Actualizar main sin perder cambios locales

Primero ejecutar git status. Si hay cambios, terminar y confirmar el trabajo revisado en su rama, o guardarlo temporalmente con stash. No cambiar a main esperando que Git descarte esos cambios.

Si todavía no se desea hacer commit:

```powershell
git stash push -u -m "Trabajo local antes de actualizar main"
git stash list
```

`-u` incluye archivos nuevos no ignorados, pero no incluye .env ni otros archivos ignorados. Mantener esos archivos privados en su sitio; no usar stash -a para capturarlos. Anotar la rama de origen para volver a ella.

Con los cambios guardados y árbol limpio:

```powershell
git switch main
git pull --ff-only origin main
git switch feature/guia-presentacion
git merge main
```

Si se utilizó stash, recuperar el indicado en la **rama de trabajo original**, después de incorporar main:

```powershell
git stash apply 'stash@{0}'
git status
git diff
```

Revisar y probar antes de eliminar el stash. apply conserva la copia guardada; si hay varios stashes, escoger el correcto en git stash list. Si pull --ff-only se rechaza, revisar la divergencia con el compañero. Si merge o stash generan conflictos, resolverlos en los archivos, verificar el resultado y preparar solo las resoluciones correctas; no borrar el trabajo ni forzar la publicación para evitarlos.

## 9. Preparación del despliegue

Revisar [PREPARACION_DESPLIEGUE.md](PREPARACION_DESPLIEGUE.md): PHP 8.4, PDO_PGSQL/mbstring/sesiones, PostgreSQL 17/btree_gist, servidor de producción con raíz public, HTTPS, entorno privado, sesiones persistentes y TLS verificado a PostgreSQL. El proveedor todavía no está seleccionado. No se crean archivos de producción ni se ejecutan migraciones externas en esta etapa.

Después de elegir el destino, acordar quién administra alojamiento y secretos. Configurar APP_ENV=production, la zona de la clínica y credenciales privadas del destino; comprobar PGSSLMODE/PGSSLROOTCERT según su CA/TLS. El código no interpreta DATABASE_URL automáticamente. El despliegue requiere sus propias verificaciones; los archivos del repositorio no contienen una copia de la base ni secretos para automatizarlo.
