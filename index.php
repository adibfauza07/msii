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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    :root {
      --primary-dark: #1e293b;
      --accent-color: #3b82f6;
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
      background: rgba(59, 130, 246, 0.05);
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

    /* Modern Card Styling */
    .card-division {
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 28px;
      padding: 2.5rem 1.5rem;
      height: 100%;
      text-align: center;
      transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      text-decoration: none !important;
    }

    .card-division:hover {
      transform: translateY(-12px) scale(1.02);
      background: #ffffff;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.12);
      border-color: var(--accent-color);
    }

    /* Icon Box with Glow */
    .icon-box {
      width: 75px;
      height: 75px;
      border-radius: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.2rem;
      margin-bottom: 1.5rem;
      color: white;
      transition: all 0.5s ease;
      position: relative;
    }

    .card-division:hover .icon-box {
      transform: rotate(-5deg) scale(1.1);
    }

    .division-name {
      font-weight: 700;
      font-size: 0.85rem;
      color: #334155;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Individual Color Palettes */
    .bg-admin { background: linear-gradient(135deg, #1e293b, #475569); box-shadow: 0 15px 30px -10px rgba(30, 41, 59, 0.5); }
    .bg-finance { background: linear-gradient(135deg, #0ea5e9, #38bdf8); box-shadow: 0 15px 30px -10px rgba(14, 165, 233, 0.5); }
    .bg-marketing { background: linear-gradient(135deg, #6366f1, #818cf8); box-shadow: 0 15px 30px -10px rgba(99, 102, 241, 0.5); }
    .bg-sales { background: linear-gradient(135deg, #10b981, #34d399); box-shadow: 0 15px 30px -10px rgba(16, 185, 129, 0.5); }
    .bg-ppic { background: linear-gradient(135deg, #8b5cf6, #a78bfa); box-shadow: 0 15px 30px -10px rgba(139, 92, 246, 0.5); }
    .bg-inventory { background: linear-gradient(135deg, #f59e0b, #fbbf24); box-shadow: 0 15px 30px -10px rgba(245, 158, 11, 0.5); }
    .bg-purchasing { background: linear-gradient(135deg, #64748b, #94a3b8); box-shadow: 0 15px 30px -10px rgba(100, 116, 139, 0.5); }
    .bg-production { background: linear-gradient(135deg, #ef4444, #f87171); box-shadow: 0 15px 30px -10px rgba(239, 68, 68, 0.5); }
    .bg-pe { background: linear-gradient(135deg, #ec4899, #f472b6); box-shadow: 0 15px 30px -10px rgba(236, 72, 153, 0.5); }
    .bg-qc { background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 15px 30px -10px rgba(6, 182, 212, 0.5); }
    .bg-mtn { background: linear-gradient(135deg, #f97316, #fb923c); box-shadow: 0 15px 30px -10px rgba(249, 115, 22, 0.5); }
.bg-warehouse { background: linear-gradient(135deg, #334155, #475569); box-shadow: 0 15px 30px -10px rgba(51, 65, 85, 0.5); }

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

  <div class="container main-content text-center">
    <header class="header-section">
      <img src="logo_imc.jpg" alt="Logo" class="logo-img">
      <h1 class="dashboard-title">ERP SYSTEM PORTAL</h1>
      <p class="sub-title">Integrated Management System</p>
    </header>

    <div class="row g-4 row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 justify-content-center pb-5">
      
      <div class="col">
        <a href="admin/dashboard_admin.php" class="card-division">
          <div class="icon-box bg-admin"><i class="bi bi-shield-lock"></i></div>
          <span class="division-name">Admin</span>
        </a>
      </div>

      <div class="col">
        <a href="finance/dashboard.php" class="card-division">
          <div class="icon-box bg-finance"><i class="bi bi-wallet2"></i></div>
          <span class="division-name">Finance</span>
        </a>
      </div>

            <div class="col">
        <a href="inventory/login.php" class="card-division">
          <div class="icon-box bg-inventory"><i class="bi bi-box-seam"></i></div>
          <span class="division-name">Inventory</span>
        </a>
      </div>

      <div class="col">
        <a href="mtn/login.php" class="card-division">
          <div class="icon-box bg-mtn"><i class="bi bi-wrench-adjustable"></i></div>
          <span class="division-name">Maintenance</span>
        </a>
      </div>

      <div class="col">
        <a href="marketing/dashboard_marketing.php" class="card-division">
          <div class="icon-box bg-marketing"><i class="bi bi-megaphone"></i></div>
          <span class="division-name">Marketing</span>
        </a>
      </div>

      <div class="col">
    <a href="ordering_p2/login.php" class="card-division">
        <div class="icon-box bg-production"><i class="bi bi-cart-check"></i></div>
        <span class="division-name">Ordering Plant 2</span>
    </a>
</div>

      <div class="col">
        <a href="ppic/dashboard_ppic.php" class="card-division">
          <div class="icon-box bg-ppic"><i class="bi bi-calendar3"></i></div>
          <span class="division-name">PPIC</span>
        </a>
      </div>

      <div class="col">
        <a href="purchasing/dashboard_purchasing.php" class="card-division">
          <div class="icon-box bg-purchasing"><i class="bi bi-bag-check"></i></div>
          <span class="division-name">Purchasing</span>
        </a>
      </div>

      <div class="col">
        <a href="production/dashboard_production.php" class="card-division">
          <div class="icon-box bg-production"><i class="bi bi-cpu"></i></div>
          <span class="division-name">Production</span>
        </a>
      </div>

      <div class="col">
        <a href="pe/login.php" class="card-division">
          <div class="icon-box bg-pe"><i class="bi bi-lightbulb"></i></div>
          <span class="division-name">PE</span>
        </a>
      </div>

      <div class="col">
        <a href="sales/dashboard_sales.php" class="card-division">
          <div class="icon-box bg-sales"><i class="bi bi-cart-check"></i></div>
          <span class="division-name">Sales</span>
        </a>
      </div>

      <div class="col">
        <a href="qc/dashboard_qc.php" class="card-division">
          <div class="icon-box bg-qc"><i class="bi bi-shield-check"></i></div>
          <span class="division-name">QC</span>
        </a>
      </div>

      <div class="col">
        <a href="warehouse/login.php" class="card-division">
          <div class="icon-box bg-warehouse"><i class="bi bi-truck"></i></div>
          <span class="division-name">Warehouse</span>
        </a>
      </div>

      <div class="col">
        <a href="4m/login.php" class="card-division">
          <div class="icon-box bg-4m"><i class="bi bi-gear-fill"></i></div>
          <span class="division-name">4M Change</span>
        </a>
      </div>

    </div>
  </div>

  <footer>
    <div class="container text-center">
      <p class="mb-0">&copy; 2026 <strong>PT. IMC Tekno Indonesia</strong>. All Rights Reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>