<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>ERP System Portal - PT. IMCTekno Indonesia</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <style>
    :root {
      --primary-dark: #1e293b;
      --bg-gradient: radial-gradient(circle at top right, #f1f5f9, #e2e8f0);
    }

    body {
      background: var(--bg-gradient);
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--primary-dark);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
    }

    /* Background Animation */
    body::before {
      content: "";
      position: absolute;
      top: -10%; right: -10%;
      width: 400px; height: 400px;
      background: rgba(59, 130, 246, 0.08);
      filter: blur(80px);
      border-radius: 50%;
      z-index: -1;
    }

    /* Header Styling */
    .header-section {
      padding: 3rem 0 2rem;
    }

    .logo-img {
      width: 70px;
      margin-bottom: 1.5rem;
      filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));
    }

    .dashboard-title {
      font-weight: 800;
      font-size: clamp(2rem, 5vw, 3rem);
      letter-spacing: -1.5px;
      background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 0.5rem;
    }

    .sub-title {
      font-weight: 600;
      font-size: 0.9rem;
      color: #64748b;
      letter-spacing: 3px;
      text-transform: uppercase;
    }

    /* Customizing Standard Button */
    .menu-btn {
      font-weight: 600;
      letter-spacing: 0.5px;
      border: none;
      transition: all 0.2s ease-in-out;
      display: flex;
      align-items: center;
    }
    
    .menu-btn:hover {
      transform: translateY(-3px) scale(1.02);
      box-shadow: 0 10px 15px -3px rgba(0,0,0,0.15);
      filter: brightness(1.1);
    }

    .menu-icon {
      font-size: 1.25rem;
      width: 35px;
      display: inline-block;
      text-align: center;
    }

    footer {
      margin-top: auto;
      padding: 2rem 0;
      border-top: 1px solid rgba(0,0,0,0.05);
      color: #94a3b8;
      font-size: 0.85rem;
    }
  </style>
</head>
<body>

  <div class="container-fluid main-content px-4 mb-5">
    <header class="header-section text-center">
      <img src="logo_imc.jpg" alt="Logo" class="logo-img">
      <h1 class="dashboard-title">ERP SYSTEM PORTAL PT.IMC TEKNO INDONESIA</h1>
      <p class="sub-title">Enterprise Resource Planning</p>
    </header>

    <!-- 4 Panels Container -->
    <div class="row g-4 text-start justify-content-center">
      
      <!-- PANEL 1: PLANNING -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white p-3 rounded-4 h-100">
          <h5 class="text-muted fw-bold mb-3 text-center" style="font-size: 0.9rem; letter-spacing: 1px;">PLANNING</h5>
          
          <a href="ppic2/login.php" class="btn btn-primary p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-calendar3 menu-icon"></i> PPIC
          </a>
          <a href="inventory/login.php" class="btn btn-info text-white p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-box-seam menu-icon"></i> Inventory
          </a>
          <a href="warehouse1/login.php" class="btn btn-secondary p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-truck menu-icon"></i> Warehouse
          </a>
          <a href="/msii/bea/login.php" class="btn btn-dark p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-boxes menu-icon"></i> IT Inventory Plant 1
          </a>
          <a href="pe/login.php" class="btn btn-primary p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-lightbulb menu-icon"></i> PE
          </a>
        </div>
      </div>

      <!-- PANEL 2: ORDERING -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white p-3 rounded-4 h-100">
          <h5 class="text-muted fw-bold mb-3 text-center" style="font-size: 0.9rem; letter-spacing: 1px;">ORDERING</h5>

          <a href="ordering_p2/login.php" class="btn btn-success p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-bag-check menu-icon"></i> Ordering
          </a>
          <a href="purch2/login.php" class="btn btn-success p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-cart3 menu-icon"></i> Purchasing
          </a>
          <a href="marketing/login.php" class="btn btn-warning text-dark p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-megaphone menu-icon"></i> Marketing
          </a>
          <a href="exim/dashboard_exim.php" class="btn btn-danger p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-airplane menu-icon"></i> Exim
          </a>
          <a href="vendor/login.php" class="btn btn-secondary p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-buildings menu-icon"></i> Vendor Control
          </a>
        </div>
      </div>

      <!-- PANEL 3: PROSES -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white p-3 rounded-4 h-100">
          <h5 class="text-muted fw-bold mb-3 text-center" style="font-size: 0.9rem; letter-spacing: 1px;">PROSES</h5>

          <a href="prod2/login.php" class="btn btn-danger p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-cpu menu-icon"></i> Production
          </a>
          <a href="qc/dashboard_qc.php" class="btn btn-info text-white p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-shield-check menu-icon"></i> QC
          </a>
          <a href="mold/login.php" class="btn btn-dark p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-wrench menu-icon"></i> Moldshop
          </a>
          <a href="mtn/login.php" class="btn btn-secondary p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-wrench-adjustable menu-icon"></i> Maintenance
          </a>
          <a href="pica/login.php" class="btn btn-primary p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-asterisk menu-icon"></i> PICA
          </a>
          <a href="4m/login.php" class="btn btn-warning text-dark p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-gear-fill menu-icon"></i> 4M Change
          </a>
        </div>
      </div>

      <!-- PANEL 4: ACCOUNTING -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white p-3 rounded-4 h-100">
          <h5 class="text-muted fw-bold mb-3 text-center" style="font-size: 0.9rem; letter-spacing: 1px;">ACCOUNTING</h5>

          <a href="finance/dashboard.php" class="btn btn-success p-2 mb-2 w-100 text-start menu-btn">
            <i class="bi bi-wallet2 menu-icon"></i> Finance
          </a>
          <a href="it/login.php" class="btn btn-info text-white p-2 w-100 text-start menu-btn">
            <i class="bi bi-laptop menu-icon"></i> Budgeting
          </a>
        </div>
      </div>

    </div>
  </div>

  <footer class="text-center">
    <div class="container">
      <p class="mb-0">&copy; 2026 <strong>PT. IMC Tekno Indonesia</strong>. All Rights Reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>