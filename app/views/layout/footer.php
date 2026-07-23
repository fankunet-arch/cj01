<footer class="winfoot">
    <span class="b">西华招聘</span>
    <span class="dom">zhaopin.es</span>
    <span>免注册发布 · 帖子30天自动过期 · 电话默认遮号</span>
    <span lang="es" class="legal">Tablón de anuncios de empleo. Contenido publicado por los usuarios.
        <a href="/privacy">Política de privacidad y cookies</a></span>
</footer>
<?php if (isset($page) && is_file(dirname(ZP_APP_PATH) . '/zp_html/assets/js/' . $page . '.js')): ?>
<script src="/assets/js/<?= zp_e($page) ?>.js"></script>
<?php endif; ?>
</body>
</html>
