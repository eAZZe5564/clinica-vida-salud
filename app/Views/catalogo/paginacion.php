<nav class="pagination" aria-label="Páginas de resultados">
    <?php if ($resultado['pagina'] > 1): ?><a class="text-link" href="?<?= e(http_build_query(array_merge($filtros, ['pagina' => $resultado['pagina'] - 1]))) ?>">← Anterior</a><?php endif; ?>
    <span>Página <?= $resultado['pagina'] ?> de <?= $resultado['paginas'] ?> · <?= $resultado['total'] ?> resultados</span>
    <?php if ($resultado['pagina'] < $resultado['paginas']): ?><a class="text-link" href="?<?= e(http_build_query(array_merge($filtros, ['pagina' => $resultado['pagina'] + 1]))) ?>">Siguiente →</a><?php endif; ?>
</nav>
