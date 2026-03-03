<?php include 'layout.php'; ?>

<div class="container-fluid">
    <h3 class="fw-bold mb-4 text-dark">Ringkasan Aging</h3>
    
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card card-stats p-4 bg-white">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Total Outstanding</h6>
                        <h2 class="fw-bold text-primary">AGING AR</h2>
                    </div>
                    <i class="bi bi-cart-check fs-1 text-primary opacity-25"></i>
                </div>
                <div class="mt-3">
                    <a href="aging_sales.php" class="btn btn-outline-primary btn-sm w-100">Buka Detail Aging Sales</a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-stats p-4 bg-white">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Total Outstanding</h6>
                        <h2 class="fw-bold text-danger">AGING AP</h2>
                    </div>
                    <i class="bi bi-truck fs-1 text-danger opacity-25"></i>
                </div>
                <div class="mt-3">
                    <a href="aging_ap.php" class="btn btn-outline-danger btn-sm w-100">Buka Detail Aging AP</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>