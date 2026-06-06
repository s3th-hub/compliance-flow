        </div><!-- /container-fluid -->
    </div><!-- /page-content -->
</div><!-- /wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $s): ?>
        <script src="<?= BASE_URL . $s ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
<?php if (!empty($inlineScript)): ?>
    <script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
