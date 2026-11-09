"""CI de imagenes por SHA; sin PostgreSQL, datos reales o despliegues."""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import tarfile
import tempfile
import uuid

ROOT = Path(__file__).resolve().parents[2]
TESTS = ROOT / "tests" / "docker"
CONTAINERS = []
RESULTS = []
BASE_BRIDGE = "1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37"
BASE_FULL = "23b1175fe865021a102a890c0f5b267eabad5588"
RUNTIME = {"app/Config/Mantenimiento.php", "app/Config/mantenimiento-http.php",
           "docker/apache/clinica.conf", "public/index.php", "app/Middleware/AuthMiddleware.php"}

def run(command, *, data=None, capture=False, env=None):
    result = subprocess.run(command, input=data, stdout=subprocess.PIPE if capture else None,
                            stderr=subprocess.PIPE if capture else None, env=env, check=False)
    if result.returncode:
        # No imprimir stderr de peticiones, variables ni cuerpos de respuestas.
        if capture and (result.stdout or result.stderr):
            diagnostic = re.search(r"Uncaught (?:RuntimeException|Error): ([^\r\n]+)", (result.stdout + result.stderr).decode(errors="replace"))
            if diagnostic:
                print("Diagnostico de la prueba PHP: " + diagnostic.group(1), flush=True)
        raise RuntimeError(f"Fallo tecnico: {command[0]} (exit {result.returncode})")
    return result.stdout if capture else b""

def git(*args):
    return run(["git", "-C", str(ROOT), *args], capture=True).decode().strip()

def export(source, version, target):
    if not re.fullmatch(r"[a-f0-9]{40}", source):
        raise RuntimeError("Se exige SHA completo")
    if git("rev-parse", source + "^{commit}") != source:
        raise RuntimeError("Commit fuente inexistente")
    base = BASE_BRIDGE if version == "puente" else BASE_FULL
    run(["git", "-C", str(ROOT), "merge-base", "--is-ancestor", base, source])
    paths = git("diff", "--name-only", base, source, "--", "app", "public", "docker",
                "Dockerfile", ".dockerignore", "composer.json", "composer.lock", "database", "scripts")
    if set(paths.splitlines()) != RUNTIME:
        raise RuntimeError("El inventario no coincide con mantenimiento y la correccion HTTP 419")
    for file in ["Dockerfile", ".dockerignore", "docker/start.sh", "app/Config/Database.php"]:
        if git("rev-parse", source + ":" + file) != git("rev-parse", base + ":" + file):
            raise RuntimeError("Se modifico un componente protegido del candidato")
    original = run(["git", "-C", str(ROOT), "show", base + ":public/index.php"], capture=True)
    needle = b"require dirname(__DIR__) . '/app/Config/bootstrap.php';"
    expected = original.replace(needle, b"require_once dirname(__DIR__) . '/app/Config/mantenimiento-http.php';\n" + needle)
    if original.count(needle) != 1 or expected != run(["git", "-C", str(ROOT), "show", source + ":public/index.php"], capture=True):
        raise RuntimeError("Index contiene cambios fuera del guard")
    csrf = run(["git", "-C", str(ROOT), "show", base + ":app/Middleware/AuthMiddleware.php"], capture=True)
    expected_csrf = csrf.replace(b"http_response_code(419);", b"header('HTTP/1.1 419 Page Expired', true, 419);")
    if csrf.count(b"http_response_code(419);") != 1 or expected_csrf != run(["git", "-C", str(ROOT), "show", source + ":app/Middleware/AuthMiddleware.php"], capture=True):
        raise RuntimeError("El cambio CSRF excede la correccion de su linea HTTP")
    archive = run(["git", "-C", str(ROOT), "archive", source], capture=True)
    with tarfile.open(fileobj=io.BytesIO(archive)) as package:
        package.extractall(target, filter="data")
    for file in [".env", "vendor", "app/Config/DatabaseDiagnostic.php"]:
        if (target / file).exists():
            raise RuntimeError("Archivo privado en fuente")
    if version == "puente":
        for file in ["composer.json", "app/Config/RutasPortal.php", "app/Exports",
                     "app/Controllers/PortalPacienteController.php"]:
            if (target / file).exists():
                raise RuntimeError("Componente Ciclo 2 en puente")
        migrations = git("ls-tree", "-r", "--name-only", source, "--", "database/migrations")
        if migrations != git("ls-tree", "-r", "--name-only", base, "--", "database/migrations"):
            raise RuntimeError("Migraciones ajenas al Ciclo 1 en puente")
    print(json.dumps({"version": version, "source": source, "base": base,
                      "runtime_files": sorted(RUNTIME)}), flush=True)

def boot(image, temporary, version, mode, instrument=False, private=False):
    name = "clinica20i-" + version + "-" + uuid.uuid4().hex[:10]
    environment = dict(os.environ)
    environment.update(DB_HOST="127.0.0.1", DB_PORT="1", DB_NAME="ci_sin_base",
                       DB_USER="ci_sin_usuario", DB_PASSWORD=secrets.token_hex(24),
                       CLINICA_FIXTURE_PASSWORD="Ficticia!9" + secrets.token_hex(12))
    command = ["docker", "run", "--detach", "--network", "none", "--name", name,
               "-e", "APP_ENV=production", "-e", "APP_TIMEZONE=America/La_Paz",
               "-e", "PORT=10000", "-e", "PGSSLMODE=verify-full",
               "-e", "PGSSLROOTCERT=/etc/ssl/certs/ca-certificates.crt"]
    for variable in ["DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASSWORD"]:
        command += ["-e", variable]
    if mode is not None:
        command += ["-e", "APP_MAINTENANCE=" + mode]
    canary = temporary / ("canary-" + version + ".php")
    canary.write_text('<?php echo "PHP_CANARY";', encoding="utf-8")
    command += ["--mount", f"type=bind,src={canary},dst=/var/www/clinica/public/probe.php,readonly"]
    if instrument:
        code = b'<?php echo password_hash(getenv("CLINICA_FIXTURE_PASSWORD"), PASSWORD_DEFAULT);'
        hashed = run(["docker", "run", "--rm", "--network", "none", "-i", "-e",
                      "CLINICA_FIXTURE_PASSWORD", "--entrypoint", "php", image],
                     data=code, capture=True, env=environment).decode()
        environment["CLINICA_FIXTURE_HASH"] = hashed
        command += ["--mount", f"type=bind,src={TESTS / 'database_fixture.php'},dst=/var/www/clinica/app/Config/Database.php,readonly",
                    "-e", "CLINICA_FIXTURE_PASSWORD", "-e", "CLINICA_FIXTURE_HASH"]
    if private:
        environment["CLINICA_CI_KEY"] = secrets.token_hex(32)
        environment["APP_MAINTENANCE_TEST_HASH"] = hashlib.sha256(environment["CLINICA_CI_KEY"].encode()).hexdigest()
        command += ["-e", "CLINICA_CI_KEY", "-e", "APP_MAINTENANCE_TEST_HASH"]
    command += [image]
    run(command, capture=True, env=environment)
    CONTAINERS.append(name)
    network = run(["docker", "inspect", "--format", "{{.HostConfig.NetworkMode}}", name], capture=True).decode().strip()
    if network != "none":
        raise RuntimeError("Contenedor con red no autorizada")
    run(["docker", "cp", str(TESTS / "http.php"), name + ":/tmp/clinica-http.php"])
    return name

def http(container, phase):
    payload = run(["docker", "exec", "--user", "www-data", container, "php", "/tmp/clinica-http.php", phase], capture=True)
    result = json.loads(payload)
    print(json.dumps(result), flush=True)
    return result

def verify(source, version, temporary):
    context = temporary / version
    context.mkdir()
    export(source, version, context)
    image = "clinica20i-" + version + ":" + source[:12]
    run(["docker", "build", "--pull", "--tag", image, str(context)])
    image_id = run(["docker", "image", "inspect", "--format", "{{.Id}}", image], capture=True).decode().strip()
    print(json.dumps({"image": image, "id": image_id, "source": source}), flush=True)
    run(["docker", "run", "--rm", "--network", "none", "-e", "PORT=10000",
         "--entrypoint", "apache2ctl", image, "-t"])
    run(["docker", "run", "--rm", "--network", "none", "--entrypoint", "apache2", image, "-v"])
    run(["docker", "run", "--rm", "--network", "none", "-i", "-e", "CI_VERSION=" + version,
         "--entrypoint", "php", image], data=(TESTS / "runtime.php").read_bytes())
    if version == "cu26":
        for args in [["validate", "--strict"], ["check-platform-reqs", "--no-dev"],
                     ["install", "--dry-run", "--no-dev", "--no-interaction", "--no-scripts", "--no-plugins"]]:
            run(["docker", "run", "--rm", "--network", "none", "--entrypoint", "composer", image, *args])
        run(["docker", "run", "--rm", "--entrypoint", "composer", image, "audit", "--locked", "--no-dev", "--no-interaction"])
        run(["docker", "run", "--rm", "--network", "none", "--user", "www-data", "-i",
             "-e", "APP_ENV=production", "-e", "APP_TIMEZONE=America/La_Paz",
             "--entrypoint", "php", image], data=(TESTS / "pdf_csv.php").read_bytes())
    phases = []
    primary = boot(image, temporary, version, "on")
    phases.append(http(primary, "closed"))
    # PID1 y arranque real, no un servidor PHP de desarrollo.
    process = run(["docker", "exec", primary, "cat", "/proc/1/comm"], capture=True).decode().strip()
    if process != "apache2":
        raise RuntimeError("PID1 no es Apache")
    for mode in [None, "of"]:
        name = boot(image, temporary, version, mode)
        phases.append(http(name, "closed"))
        run(["docker", "rm", "--force", name], capture=True); CONTAINERS.remove(name)
    for mode, private, phase in [("on", False, "closed"), ("off", False, "normal"), ("on", True, "private")]:
        name = boot(image, temporary, version, mode, instrument=True, private=private)
        phases.append(http(name, phase))
        run(["docker", "rm", "--force", name], capture=True); CONTAINERS.remove(name)
    run(["docker", "restart", primary], capture=True)
    phases.append(http(primary, "overlap"))
    RESULTS.append({"version": version, "source": source, "image_id": image_id,
                    "assertions": sum(item["assertions"] for item in phases),
                    "phases": phases, "postgresql": False})
    return primary

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--bridge", required=True)
    parser.add_argument("--full")
    args = parser.parse_args()
    run(["docker", "version"])
    try:
        with tempfile.TemporaryDirectory(prefix="clinica20i-") as folder:
            temporary = Path(folder)
            bridge = verify(args.bridge, "puente", temporary)
            if args.full:
                full = verify(args.full, "cu26", temporary)
                for _ in range(5):
                    http(bridge, "overlap"); http(full, "overlap")
            print(json.dumps({"result": "passed", "images": RESULTS, "render": "untouched",
                              "database": "none", "real_microphone": "not_tested"}), flush=True)
            summary = os.environ.get("GITHUB_STEP_SUMMARY")
            if summary:
                with open(summary, "a", encoding="utf-8") as handle:
                    handle.write("## Docker real del mantenimiento\n\n")
                    for item in RESULTS:
                        handle.write(f"- {item['version']}: SHA {item['source']}; imagen {item['image_id']}; {item['assertions']} aserciones; salud 200 y cierre 503.\n")
                    handle.write("\nSin PostgreSQL, migraciones, datos reales o despliegues. Normal/login/roles: PDO ficticio montado solo en pruebas. PDF/CSV CLI en CU26; no regresion completa ni validacion del orquestador Render.\n")
    finally:
        for container in list(CONTAINERS):
            subprocess.run(["docker", "rm", "--force", container], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=False)

if __name__ == "__main__":
    main()
