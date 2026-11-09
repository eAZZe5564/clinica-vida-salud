<?php

declare(strict_types=1);

// Compartido por el front controller y auto_prepend_file de Apache.
require_once __DIR__ . '/Mantenimiento.php';
\App\Config\Mantenimiento::interceptar();
