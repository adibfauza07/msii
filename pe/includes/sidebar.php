<div id="sidebar">
    <div class="brand">
        <i class="bi bi-gear-wide-connected"></i> PE SYSTEM
    </div>

    <div class="py-2 overflow-auto h-100">
        <ul class="nav flex-column">
            <div class="menu-label">Utama & Input</div>
            
            <li class="nav-item">
                <a href="/msii/pe/dashboard_pe.php" class="nav-link">
    <i class="bi bi-speedometer2"></i> Dashboard Overview
</a>
            </li>

            <li class="nav-item">
                <a href="/msii/pe/input_trial_pe.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'input_trial_pe.php') ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-plus"></i> Input Trial Baru
                </a>
            </li>
             <li><!-- Menu Material Slip - Tambahan di sini -->
        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'material_slip/index.php') ? 'active' : ''; ?>" href="material_slip/index.php" target="mainFrame">
            📋 SPB TO CUSTOMER / VENDOR
        </a></li>

        <li>
		
		 <!-- Menu Material Slip - Tambahan di sini -->
        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'SPB/index.php') ? 'active' : ''; ?>" href="SPB/index.php" target="mainFrame">
            📋 SPB GENERAL
        </a></li>

            <div class="menu-label">Master & Database</div>
            
            <li class="nav-item">
                <a href="/msii/pe/master_std.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'master_std.php') ? 'active' : ''; ?>">
                    <i class="bi bi-database-check"></i> Master Standard
                </a>
            </li>
            
            <li class="nav-item">
                <!-- <a href="/msii/pe/report_trial_pe.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'report_trial_pe.php') ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-pdf"></i> Rekap Report
                </a> -->
            </li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="d-flex align-items-center mb-3">
            <div class="bg-success rounded-circle" style="width: 10px; height: 10px; margin-right: 8px;"></div>
            <small class="text-white-50">User: <strong class="text-white"><?= $_SESSION['db_user']  ?></strong></small>
        </div>
        
        <a href="../index.php" class="btn btn-outline-secondary w-100 btn-sm mb-2 text-white-50" style="border-color: #4a5568;">
            <i class="bi bi-arrow-left"></i> Kembali ke ERP
        </a>
        <a href="logout.php" class="btn btn-danger w-100 btn-sm">
            <i class="bi bi-box-arrow-right"></i> LOGOUT
        </a>
    </div>
</div>