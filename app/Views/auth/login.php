<section class="auth-wrap">
    <div class="auth-intro">
        <p class="eyebrow">Acceso seguro</p>
        <h1>Bienvenido de nuevo.</h1>
        <p class="lead">Ingresa con el correo asociado a tu cuenta de Clínica Vida Salud.</p>
        <div class="auth-promise">
            <span class="promise-icon" aria-hidden="true">✓</span>
            <span>Tu cuenta solo verá las funciones autorizadas para su rol.</span>
        </div>
    </div>
    <form class="panel login-panel" method="post" action="/login" autocomplete="on">
        <div>
            <p class="eyebrow">Iniciar sesión</p>
            <h2>Accede a tu cuenta</h2>
        </div>
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label for="correo">Correo electrónico</label>
        <input id="correo" name="correo" type="email" maxlength="254" autocomplete="username" required autofocus>
        <label for="contrasena">Contraseña</label>
        <input id="contrasena" name="contrasena" type="password" maxlength="72" autocomplete="current-password" required>
        <button class="button button-wide" type="submit">Ingresar</button>
        <p class="form-hint">Si no puedes acceder, solicita ayuda al administrador del sistema.</p>
    </form>
</section>
