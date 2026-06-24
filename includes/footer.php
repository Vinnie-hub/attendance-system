<?php // includes/footer.php ?>
</main><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<?php if (!empty($extraJs)): ?>
  <script><?= $extraJs ?></script>
<?php endif; ?>
</body>
</html>
