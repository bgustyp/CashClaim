<?php
/**
 * CashClaim - Petty Cash & Reimbursement Simple Management System
 * 
 * @author    Bagus Setya
 * @github    https://github.com/bgustyp
 * @license   MIT License
 */
?>
<!-- Footer -->
<footer class="fixed-bottom py-3 bg-light border-top">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <p class="mb-0 text-muted small">
                    <i class="bi bi-c-circle"></i> <?= date('Y') ?> <?= APP_FOOTER ?>. Copyleft - All Wrongs Reserved.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-0 text-muted small">
                    <a href="https://github.com/bgustyp/CashClaim" target="_blank" class="text-decoration-none text-muted me-2">
                        <i class="bi bi-github"></i>
                    </a>
                    Made with <i class="bi bi-heart-fill text-danger"></i> in Indonesia
                </p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
