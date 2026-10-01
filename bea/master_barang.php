<?php
// ==================================================================================
// BAGIAN 0: PANGGIL KONFIGURASI DATABASE
// ==================================================================================
$dbConnected = false;
$dbError = '';

require_once __DIR__ . '/config/database.php';

if (isset($conn) && $conn !== false) {
    $dbConnected = true;
} else {
    $errors = sqlsrv_errors();
    $dbError = 'Koneksi SQL Server tidak tersedia. ' . ($errors ? $errors[0]['message'] : '');
}

// Deteksi Page Param
$pageName = isset($_GET['page']) ? $_GET['page'] : '';
$urlPrefix = ($pageName !== '') ? '?page=' . urlencode($pageName) . '&' : '?';

// Default Tab Routing
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : (
    isset($_GET['id']) ? 'barang' : (
        isset($_GET['itty_code']) ? 'item_type' : (
            isset($_GET['trty_code']) ? 'trans_type' : (
                isset($_GET['loc_id']) ? 'locations' : (
                    isset($_GET['curr_code']) ? 'currency' : (
                        isset($_GET['colour_id']) ? 'colour' : 'company'
                    )
                )
            )
        )
    )
);
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'view';
$isEntry = ($mode == 'new' || $mode == 'edit');

// ==================================================================================
// BAGIAN 1: PROSES DATA (POST) - COMPANY CRUD
// ==================================================================================
if ($activeTab == 'company') {
    if (isset($_POST['btnHapusCompany'])) {
        $coSetDel = isset($_POST['hapus_co_set']) ? intval($_POST['hapus_co_set']) : 0;
        if ($coSetDel > 0) {
            $sqlDelCo = "DELETE FROM dbo.COMPANY WHERE CO_SET = ?";
            $stmtDelCo = sqlsrv_query($conn, $sqlDelCo, array($coSetDel));
            if ($stmtDelCo) {
                echo "<script>alert('Data Company Berhasil Dihapus!'); window.location.href='{$urlPrefix}tab=company';</script>";
            } else {
                $err = htmlspecialchars(print_r(sqlsrv_errors(), true));
                echo "<div class='alert alert-danger' style='margin-bottom:15px;'><b>Gagal Hapus Company:</b><br><pre>{$err}</pre></div>";
            }
        }
    }

    if (isset($_POST['btnSimpanCompany']) || isset($_POST['btnUpdateCompany'])) {
        $isUpdateCo = isset($_POST['btnUpdateCompany']);
        $coSet      = isset($_POST['CO_SET']) ? intval($_POST['CO_SET']) : 0;
        $coCompany  = isset($_POST['CO_COMPANY']) ? trim($_POST['CO_COMPANY']) : '';
        $coAddr1    = isset($_POST['CO_ADDR1']) ? trim($_POST['CO_ADDR1']) : '';
        $coAddr2    = isset($_POST['CO_ADDR2']) ? trim($_POST['CO_ADDR2']) : '';
        $coCity     = isset($_POST['CO_CITY']) ? trim($_POST['CO_CITY']) : '';
        $coKodepos  = isset($_POST['CO_KODEPOS']) ? trim($_POST['CO_KODEPOS']) : '';
        $coPhone    = isset($_POST['CO_PHONE']) ? trim($_POST['CO_PHONE']) : '';
        $coFax      = isset($_POST['CO_FAX']) ? trim($_POST['CO_FAX']) : '';
        $coNpwp     = isset($_POST['CO_NPWP']) ? trim($_POST['CO_NPWP']) : '';
        $coBasecur  = isset($_POST['CO_BASECUR']) ? trim($_POST['CO_BASECUR']) : 'IDR';
        $coBcsigner = isset($_POST['CO_BCSIGNER']) ? trim($_POST['CO_BCSIGNER']) : '';
        $coPosigner = isset($_POST['CO_POSIGNER']) ? trim($_POST['CO_POSIGNER']) : '';
        $kpbcId     = (!empty($_POST['KPBC_ID'])) ? intval($_POST['KPBC_ID']) : null;
        $coVersion  = isset($_POST['CO_VERSION']) ? trim($_POST['CO_VERSION']) : '';

        if (empty($coCompany)) {
            echo "<div class='alert alert-warning' style='margin-bottom:15px;'><i class='fa fa-warning'></i> Nama Company wajib diisi!</div>";
        } else {
            if ($isUpdateCo && $coSet > 0) {
                $sqlCo = "UPDATE dbo.COMPANY SET 
                            CO_COMPANY = ?, CO_ADDR1 = ?, CO_ADDR2 = ?, CO_CITY = ?, CO_KODEPOS = ?,
                            CO_PHONE = ?, CO_FAX = ?, CO_NPWP = ?, CO_BASECUR = ?, CO_BCSIGNER = ?,
                            CO_POSIGNER = ?, KPBC_ID = ?, CO_VERSION = ?
                          WHERE CO_SET = ?";
                $paramsCo = array($coCompany, $coAddr1, $coAddr2, $coCity, $coKodepos, $coPhone, $coFax, $coNpwp, $coBasecur, $coBcsigner, $coPosigner, $kpbcId, $coVersion, $coSet);
                $msgCo = "Data Company Berhasil Diupdate!";
            } else {
                $sqlCo = "INSERT INTO dbo.COMPANY 
                            (CO_COMPANY, CO_ADDR1, CO_ADDR2, CO_CITY, CO_KODEPOS, CO_PHONE, CO_FAX, CO_NPWP, CO_BASECUR, CO_BCSIGNER, CO_POSIGNER, KPBC_ID, CO_VERSION) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
                          SELECT SCOPE_IDENTITY() AS NEW_ID;";
                $paramsCo = array($coCompany, $coAddr1, $coAddr2, $coCity, $coKodepos, $coPhone, $coFax, $coNpwp, $coBasecur, $coBcsigner, $coPosigner, $kpbcId, $coVersion);
                $msgCo = "Data Company Baru Berhasil Disimpan!";
            }

            $stmtCo = sqlsrv_query($conn, $sqlCo, $paramsCo);
            if ($stmtCo) {
                if (!$isUpdateCo) {
                    sqlsrv_next_result($stmtCo);
                    $rowId = sqlsrv_fetch_array($stmtCo, SQLSRV_FETCH_ASSOC);
                    $coSet = isset($rowId['NEW_ID']) ? intval($rowId['NEW_ID']) : 0;
                }
                echo "<script>alert('$msgCo'); window.location.href='{$urlPrefix}tab=company&co_set=$coSet';</script>";
            } else {
                echo "<div class='alert alert-danger' style='margin-bottom:15px;'>Error Database: " . print_r(sqlsrv_errors(), true) . "</div>";
            }
        }
    }
}

// ==================================================================================
// BAGIAN 2: PROSES DATA (POST) - ITEM TYPE CRUD
// ==================================================================================
if ($activeTab == 'item_type') {
    if (isset($_POST['btnHapusItty'])) {
        $ittyCodeDel = isset($_POST['hapus_itty_code']) ? trim($_POST['hapus_itty_code']) : '';
        if ($ittyCodeDel !== '') {
            $cekUsed = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITTY_CODE = ?", array($ittyCodeDel));
            if ($cekUsed && sqlsrv_fetch_array($cekUsed)) {
                echo "<div class='alert alert-danger alert-dismissible' style='margin-bottom:15px;'>
                        <button type='button' class='close' data-dismiss='alert'>&times;</button>
                        <h4><i class='icon fa fa-ban'></i> GAGAL MENGHAPUS!</h4>
                        Item Type '{$ittyCodeDel}' tidak bisa dihapus karena masih digunakan dalam data Master Barang.
                      </div>";
            } else {
                $sqlDelItty = "DELETE FROM dbo.ITTY WHERE ITTY_CODE = ?";
                $stmtDelItty = sqlsrv_query($conn, $sqlDelItty, array($ittyCodeDel));
                if ($stmtDelItty) {
                    echo "<script>alert('Item Type {$ittyCodeDel} Berhasil Dihapus!'); window.location.href='{$urlPrefix}tab=item_type';</script>";
                } else {
                    $err = htmlspecialchars(print_r(sqlsrv_errors(), true));
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'><b>Gagal Hapus Item Type:</b><br><pre>{$err}</pre></div>";
                }
            }
        }
    }

    if (isset($_POST['btnSimpanItty']) || isset($_POST['btnUpdateItty'])) {
        $isUpdateItty = isset($_POST['btnUpdateItty']);
        $code  = isset($_POST['ITTY_CODE']) ? strtoupper(trim($_POST['ITTY_CODE'])) : '';
        $desc  = isset($_POST['ITTY_DESC']) ? trim($_POST['ITTY_DESC']) : '';
        $first = isset($_POST['ITTY_FIRST']) ? trim($_POST['ITTY_FIRST']) : '';
        $last  = isset($_POST['ITTY_LAST']) ? trim($_POST['ITTY_LAST']) : '';
        $locId = (!empty($_POST['LOC_ID'])) ? intval($_POST['LOC_ID']) : null;
        $catId = (!empty($_POST['CAT_ID'])) ? intval($_POST['CAT_ID']) : null;

        if (empty($code) || empty($desc)) {
            echo "<div class='alert alert-warning' style='margin-bottom:15px;'><i class='fa fa-warning'></i> Kode dan Deskripsi Item Type wajib diisi!</div>";
        } else {
            if ($isUpdateItty) {
                $sql = "UPDATE dbo.ITTY SET ITTY_DESC = ?, ITTY_FIRST = ?, ITTY_LAST = ?, LOC_ID = ?, CAT_ID = ? WHERE ITTY_CODE = ?";
                $params = array($desc, $first, $last, $locId, $catId, $code);
                $msg = "Item Type Berhasil Diupdate!";
            } else {
                $cek = sqlsrv_query($conn, "SELECT ITTY_CODE FROM dbo.ITTY WHERE ITTY_CODE = ?", array($code));
                if ($cek && sqlsrv_has_rows($cek)) {
                    echo "<script>alert('Gagal! Kode Item Type $code sudah terdaftar.');</script>";
                    $params = null;
                } else {
                    $sql = "INSERT INTO dbo.ITTY (ITTY_CODE, ITTY_DESC, ITTY_FIRST, ITTY_LAST, LOC_ID, CAT_ID) VALUES (?, ?, ?, ?, ?, ?)";
                    $params = array($code, $desc, $first, $last, $locId, $catId);
                    $msg = "Item Type Baru Berhasil Disimpan!";
                }
            }

            if ($params) {
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) {
                    echo "<script>alert('$msg'); window.location.href='{$urlPrefix}tab=item_type&itty_code=" . urlencode($code) . "';</script>";
                } else {
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'>Error Database: " . print_r(sqlsrv_errors(), true) . "</div>";
                }
            }
        }
    }
}

// ==================================================================================
// BAGIAN 3: PROSES DATA (POST) - TRANS. TYPE & DETAIL CRUD
// ==================================================================================
if ($activeTab == 'trans_type') {
    if (isset($_POST['btnHapusTrty'])) {
        $trtyCodeDel = isset($_POST['hapus_trty_code']) ? trim($_POST['hapus_trty_code']) : '';
        if ($trtyCodeDel !== '') {
            $cekTrans = sqlsrv_query($conn, "SELECT TOP 1 TRTY_CODE FROM dbo.INV_TRAN WHERE TRTY_CODE = ?", array($trtyCodeDel));
            if ($cekTrans && sqlsrv_fetch_array($cekTrans)) {
                echo "<div class='alert alert-danger alert-dismissible' style='margin-bottom:15px;'>
                        <button type='button' class='close' data-dismiss='alert'>&times;</button>
                        <h4><i class='icon fa fa-ban'></i> GAGAL MENGHAPUS!</h4>
                        Transaction Type '{$trtyCodeDel}' tidak bisa dihapus karena sudah dipakai dalam transaksi inventory.
                      </div>";
            } else {
                sqlsrv_query($conn, "DELETE FROM dbo.TRTY_LOC WHERE TRTY_CODE = ?", array($trtyCodeDel));
                $sqlDelTrty = "DELETE FROM dbo.TRTY WHERE TRTY_CODE = ?";
                $stmtDelTrty = sqlsrv_query($conn, $sqlDelTrty, array($trtyCodeDel));
                if ($stmtDelTrty) {
                    echo "<script>alert('Trans. Type {$trtyCodeDel} Berhasil Dihapus!'); window.location.href='{$urlPrefix}tab=trans_type';</script>";
                } else {
                    $err = htmlspecialchars(print_r(sqlsrv_errors(), true));
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'><b>Gagal Hapus Trans. Type:</b><br><pre>{$err}</pre></div>";
                }
            }
        }
    }

    if (isset($_POST['btnSimpanTrty']) || isset($_POST['btnUpdateTrty'])) {
        $isUpdateTrty = isset($_POST['btnUpdateTrty']);
        $tCode = isset($_POST['TRTY_CODE']) ? trim($_POST['TRTY_CODE']) : '';
        $tDesc = isset($_POST['TRTY_DESC']) ? trim($_POST['TRTY_DESC']) : '';
        $cotyId = isset($_POST['COTY_ID']) ? trim($_POST['COTY_ID']) : '';
        $trgrId = (!empty($_POST['TRGR_ID'])) ? intval($_POST['TRGR_ID']) : null;
        $bcIo   = isset($_POST['TRTY_BCIO']) ? trim($_POST['TRTY_BCIO']) : '';

        if (empty($tCode) || empty($tDesc)) {
            echo "<div class='alert alert-warning' style='margin-bottom:15px;'><i class='fa fa-warning'></i> TRANS CODE dan DESC wajib diisi!</div>";
        } else {
            if ($isUpdateTrty) {
                $sqlT = "UPDATE dbo.TRTY SET TRTY_DESC = ?, COTY_ID = ?, TRGR_ID = ?, TRTY_BCIO = ? WHERE TRTY_CODE = ?";
                $paramsT = array($tDesc, $cotyId, $trgrId, $bcIo, $tCode);
                $msgT = "Trans. Type Berhasil Diupdate!";
            } else {
                $cekT = sqlsrv_query($conn, "SELECT TRTY_CODE FROM dbo.TRTY WHERE TRTY_CODE = ?", array($tCode));
                if ($cekT && sqlsrv_has_rows($cekT)) {
                    echo "<script>alert('Gagal! TRANS CODE $tCode sudah ada.');</script>";
                    $paramsT = null;
                } else {
                    $sqlT = "INSERT INTO dbo.TRTY (TRTY_CODE, TRTY_DESC, COTY_ID, TRGR_ID, TRTY_BCIO) VALUES (?, ?, ?, ?, ?)";
                    $paramsT = array($tCode, $tDesc, $cotyId, $trgrId, $bcIo);
                    $msgT = "Trans. Type Baru Berhasil Disimpan!";
                }
            }

            if ($paramsT) {
                $stmtT = sqlsrv_query($conn, $sqlT, $paramsT);
                if ($stmtT) {
                    if (isset($_POST['loc_items']) && is_array($_POST['loc_items'])) {
                        foreach ($_POST['loc_items'] as $locId => $item) {
                            $locId = intval($locId);
                            $inout = isset($item['TRTY_INOUT']) ? trim($item['TRTY_INOUT']) : '';
                            $sign  = isset($item['TRTY_SIGN']) ? intval($item['TRTY_SIGN']) : 0;
                            $ok1   = isset($item['TRTY_OK1_SIGN']) ? intval($item['TRTY_OK1_SIGN']) : 0;
                            $ok2   = isset($item['TRTY_OK2_SIGN']) ? intval($item['TRTY_OK2_SIGN']) : 0;
                            $hold  = isset($item['TRTY_HOLD_SIGN']) ? intval($item['TRTY_HOLD_SIGN']) : 0;
                            $ng    = isset($item['TRTY_NG_SIGN']) ? intval($item['TRTY_NG_SIGN']) : 0;

                            $sqlUpdLoc = "UPDATE dbo.TRTY_LOC SET 
                                            TRTY_INOUT = ?, TRTY_SIGN = ?, TRTY_OK1_SIGN = ?, 
                                            TRTY_OK2_SIGN = ?, TRTY_HOLD_SIGN = ?, TRTY_NG_SIGN = ? 
                                          WHERE TRTY_CODE = ? AND LOC_ID = ?";
                            sqlsrv_query($conn, $sqlUpdLoc, array($inout, $sign, $ok1, $ok2, $hold, $ng, $tCode, $locId));
                        }
                    }
                    echo "<script>alert('$msgT'); window.location.href='{$urlPrefix}tab=trans_type&trty_code=" . urlencode($tCode) . "';</script>";
                } else {
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'>Error Database: " . print_r(sqlsrv_errors(), true) . "</div>";
                }
            }
        }
    }

    if (isset($_POST['btnSimpanLocDetail'])) {
        $parentTrty = isset($_POST['DETAIL_TRTY_CODE']) ? trim($_POST['DETAIL_TRTY_CODE']) : '';
        $locId      = isset($_POST['LOC_ID']) ? intval($_POST['LOC_ID']) : 0;
        $inout      = isset($_POST['TRTY_INOUT']) ? trim($_POST['TRTY_INOUT']) : 'In';
        $sign       = isset($_POST['TRTY_SIGN']) ? intval($_POST['TRTY_SIGN']) : 1;
        $ok1        = isset($_POST['TRTY_OK1_SIGN']) ? intval($_POST['TRTY_OK1_SIGN']) : 1;
        $ok2        = isset($_POST['TRTY_OK2_SIGN']) ? intval($_POST['TRTY_OK2_SIGN']) : 0;
        $hold       = isset($_POST['TRTY_HOLD_SIGN']) ? intval($_POST['TRTY_HOLD_SIGN']) : 0;
        $ng         = isset($_POST['TRTY_NG_SIGN']) ? intval($_POST['TRTY_NG_SIGN']) : 0;

        if ($locId <= 0 || empty($parentTrty)) {
            echo "<script>alert('Pilih lokasi terlebih dahulu!');</script>";
        } else {
            sqlsrv_query($conn, "DELETE FROM dbo.TRTY_LOC WHERE TRTY_CODE = ? AND LOC_ID = ?", array($parentTrty, $locId));
            $sqlInsTL = "INSERT INTO dbo.TRTY_LOC (TRTY_CODE, LOC_ID, TRTY_SIGN, TRTY_INOUT, TRTY_OK1_SIGN, TRTY_OK2_SIGN, TRTY_HOLD_SIGN, TRTY_NG_SIGN) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtInsTL = sqlsrv_query($conn, $sqlInsTL, array($parentTrty, $locId, $sign, $inout, $ok1, $ok2, $hold, $ng));
            if ($stmtInsTL) {
                echo "<script>alert('Lokasi berhasil ditambahkan!'); window.location.href='{$urlPrefix}tab=trans_type&trty_code=" . urlencode($parentTrty) . "';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal tambah detail lokasi: " . print_r(sqlsrv_errors(), true) . "</div>";
            }
        }
    }

    if (isset($_POST['btnUpdateSingleLoc'])) {
        $parentTrty = isset($_POST['EDIT_TRTY_CODE']) ? trim($_POST['EDIT_TRTY_CODE']) : '';
        $locId      = isset($_POST['EDIT_LOC_ID']) ? intval($_POST['EDIT_LOC_ID']) : 0;
        $inout      = isset($_POST['EDIT_TRTY_INOUT']) ? trim($_POST['EDIT_TRTY_INOUT']) : '';
        $sign       = isset($_POST['EDIT_TRTY_SIGN']) ? intval($_POST['EDIT_TRTY_SIGN']) : 0;
        $ok1        = isset($_POST['EDIT_TRTY_OK1_SIGN']) ? intval($_POST['EDIT_TRTY_OK1_SIGN']) : 0;
        $ok2        = isset($_POST['EDIT_TRTY_OK2_SIGN']) ? intval($_POST['EDIT_TRTY_OK2_SIGN']) : 0;
        $hold       = isset($_POST['EDIT_TRTY_HOLD_SIGN']) ? intval($_POST['EDIT_TRTY_HOLD_SIGN']) : 0;
        $ng         = isset($_POST['EDIT_TRTY_NG_SIGN']) ? intval($_POST['EDIT_TRTY_NG_SIGN']) : 0;

        if (!empty($parentTrty) && $locId > 0) {
            $sqlUpd = "UPDATE dbo.TRTY_LOC SET 
                        TRTY_INOUT = ?, TRTY_SIGN = ?, TRTY_OK1_SIGN = ?, 
                        TRTY_OK2_SIGN = ?, TRTY_HOLD_SIGN = ?, TRTY_NG_SIGN = ? 
                      WHERE TRTY_CODE = ? AND LOC_ID = ?";
            $stmtUpd = sqlsrv_query($conn, $sqlUpd, array($inout, $sign, $ok1, $ok2, $hold, $ng, $parentTrty, $locId));
            if ($stmtUpd) {
                echo "<script>alert('Pengaturan lokasi berhasil diupdate!'); window.location.href='{$urlPrefix}tab=trans_type&trty_code=" . urlencode($parentTrty) . "';</script>";
            } else {
                echo "<div class='alert alert-danger'>Gagal update lokasi: " . print_r(sqlsrv_errors(), true) . "</div>";
            }
        }
    }

    if (isset($_POST['btnHapusLocDetail'])) {
        $parentTrty = isset($_POST['del_trty_code']) ? trim($_POST['del_trty_code']) : (isset($_POST['TRTY_CODE']) ? trim($_POST['TRTY_CODE']) : '');
        $locIdDel   = isset($_POST['btnHapusLocDetail']) ? intval($_POST['btnHapusLocDetail']) : 0;
        if (!empty($parentTrty) && $locIdDel > 0) {
            $stmtDelTL = sqlsrv_query($conn, "DELETE FROM dbo.TRTY_LOC WHERE TRTY_CODE = ? AND LOC_ID = ?", array($parentTrty, $locIdDel));
            if ($stmtDelTL) {
                echo "<script>alert('Detail lokasi berhasil dihapus!'); window.location.href='{$urlPrefix}tab=trans_type&trty_code=" . urlencode($parentTrty) . "';</script>";
            }
        }
    }
}

// ==================================================================================
// BAGIAN 4: PROSES DATA (POST) - LOCATIONS CRUD
// ==================================================================================
if ($activeTab == 'locations') {
    if (isset($_POST['btnHapusLoc'])) {
        $locIdDel = isset($_POST['hapus_loc_id']) ? intval($_POST['hapus_loc_id']) : 0;
        if ($locIdDel > 0) {
            $cekLoc = sqlsrv_query($conn, "SELECT TOP 1 LOC_ID FROM dbo.TRTY_LOC WHERE LOC_ID = ?", array($locIdDel));
            if ($cekLoc && sqlsrv_fetch_array($cekLoc)) {
                echo "<div class='alert alert-danger alert-dismissible' style='margin-bottom:15px;'>
                        <button type='button' class='close' data-dismiss='alert'>&times;</button>
                        <h4><i class='icon fa fa-ban'></i> GAGAL MENGHAPUS!</h4>
                        Lokasi ID '{$locIdDel}' tidak bisa dihapus karena masih dipakai pada konfigurasi Trans. Type.
                      </div>";
            } else {
                $sqlDelLoc = "DELETE FROM dbo.LOC WHERE LOC_ID = ?";
                $stmtDelLoc = sqlsrv_query($conn, $sqlDelLoc, array($locIdDel));
                if ($stmtDelLoc) {
                    echo "<script>alert('Lokasi berhasil dihapus!'); window.location.href='{$urlPrefix}tab=locations';</script>";
                } else {
                    $err = htmlspecialchars(print_r(sqlsrv_errors(), true));
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'><b>Gagal Hapus Lokasi:</b><br><pre>{$err}</pre></div>";
                }
            }
        }
    }

    if (isset($_POST['btnSimpanLoc']) || isset($_POST['btnUpdateLoc'])) {
        $isUpdateLoc = isset($_POST['btnUpdateLoc']);
        $locId      = isset($_POST['LOC_ID']) ? intval($_POST['LOC_ID']) : 0;
        $locCode    = isset($_POST['LOC_CODE']) ? strtoupper(trim($_POST['LOC_CODE'])) : '';
        $locName    = isset($_POST['LOC_NAME']) ? trim($_POST['LOC_NAME']) : '';
        $locAdjtrty = isset($_POST['LOC_ADJTRTY']) ? trim($_POST['LOC_ADJTRTY']) : '';
        $locType    = isset($_POST['LOC_TYPE']) ? trim($_POST['LOC_TYPE']) : 'I';
        $locLevel   = isset($_POST['LOC_LEVEL']) && $_POST['LOC_LEVEL'] !== '' ? intval($_POST['LOC_LEVEL']) : null;
        $locGroup   = isset($_POST['LOC_GROUP']) ? trim($_POST['LOC_GROUP']) : '';
        $locParent  = isset($_POST['LOC_PARENT']) && $_POST['LOC_PARENT'] !== '' ? intval($_POST['LOC_PARENT']) : null;
        $locVisible = isset($_POST['LOC_VISIBLE']) ? 1 : 0;

        if (empty($locCode)) {
            echo "<div class='alert alert-warning' style='margin-bottom:15px;'><i class='fa fa-warning'></i> LOC CODE wajib diisi!</div>";
        } else {
            if ($isUpdateLoc && $locId > 0) {
                $sqlLoc = "UPDATE dbo.LOC SET 
                            LOC_CODE = ?, LOC_NAME = ?, LOC_ADJTRTY = ?, LOC_TYPE = ?, 
                            LOC_LEVEL = ?, LOC_GROUP = ?, LOC_PARENT = ?, LOC_VISIBLE = ? 
                          WHERE LOC_ID = ?";
                $paramsLoc = array($locCode, $locName, $locAdjtrty, $locType, $locLevel, $locGroup, $locParent, $locVisible, $locId);
                $msgLoc = "Data Lokasi Berhasil Diupdate!";
            } else {
                $cekDup = sqlsrv_query($conn, "SELECT LOC_ID FROM dbo.LOC WHERE LOC_CODE = ?", array($locCode));
                if ($cekDup && sqlsrv_has_rows($cekDup)) {
                    echo "<script>alert('Gagal! LOC CODE $locCode sudah ada.');</script>";
                    $paramsLoc = null;
                } else {
                    $sqlLoc = "INSERT INTO dbo.LOC 
                                (LOC_CODE, LOC_NAME, LOC_ADJTRTY, LOC_TYPE, LOC_LEVEL, LOC_GROUP, LOC_PARENT, LOC_VISIBLE) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?);
                              SELECT SCOPE_IDENTITY() AS NEW_ID;";
                    $paramsLoc = array($locCode, $locName, $locAdjtrty, $locType, $locLevel, $locGroup, $locParent, $locVisible);
                    $msgLoc = "Data Lokasi Baru Berhasil Disimpan!";
                }
            }

            if ($paramsLoc) {
                $stmtLoc = sqlsrv_query($conn, $sqlLoc, $paramsLoc);
                if ($stmtLoc) {
                    if (!$isUpdateLoc) {
                        sqlsrv_next_result($stmtLoc);
                        $rowId = sqlsrv_fetch_array($stmtLoc, SQLSRV_FETCH_ASSOC);
                        $locId = isset($rowId['NEW_ID']) ? intval($rowId['NEW_ID']) : 0;
                    }
                    echo "<script>alert('$msgLoc'); window.location.href='{$urlPrefix}tab=locations&loc_id=$locId';</script>";
                } else {
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'>Error Database: " . print_r(sqlsrv_errors(), true) . "</div>";
                }
            }
        }
    }
}

// ==================================================================================
// BAGIAN 5: PROSES DATA (POST) - CURRENCY CRUD
// ==================================================================================
if ($activeTab == 'currency') {
    if (isset($_POST['btnHapusCurr'])) {
        $currCodeDel = isset($_POST['hapus_curr_code']) ? trim($_POST['hapus_curr_code']) : '';
        if ($currCodeDel !== '') {
            $sqlDelCurr = "DELETE FROM dbo.CURR WHERE CURR_CODE = ?";
            $stmtDelCurr = sqlsrv_query($conn, $sqlDelCurr, array($currCodeDel));
            if ($stmtDelCurr) {
                echo "<script>alert('Mata Uang {$currCodeDel} Berhasil Dihapus!'); window.location.href='{$urlPrefix}tab=currency';</script>";
            } else {
                $err = htmlspecialchars(print_r(sqlsrv_errors(), true));
                echo "<div class='alert alert-danger' style='margin-bottom:15px;'><b>Gagal Hapus Mata Uang:</b><br><pre>{$err}</pre></div>";
            }
        }
    }

    if (isset($_POST['btnSimpanCurr']) || isset($_POST['btnUpdateCurr'])) {
        $isUpdateCurr = isset($_POST['btnUpdateCurr']);
        $cCode = isset($_POST['CURR_CODE']) ? strtoupper(trim($_POST['CURR_CODE'])) : '';
        $cDesc = isset($_POST['CURR_DESC']) ? trim($_POST['CURR_DESC']) : '';
        $cSymb = isset($_POST['CURR_SYMBOL']) ? trim($_POST['CURR_SYMBOL']) : '';
        $cDec  = isset($_POST['CURR_DEC']) && $_POST['CURR_DEC'] !== '' ? intval($_POST['CURR_DEC']) : 0;

        if (empty($cCode)) {
            echo "<div class='alert alert-warning' style='margin-bottom:15px;'><i class='fa fa-warning'></i> CURR_CODE wajib diisi!</div>";
        } else {
            if ($isUpdateCurr) {
                $sqlC = "UPDATE dbo.CURR SET CURR_DESC = ?, CURR_SYMBOL = ?, CURR_DEC = ? WHERE CURR_CODE = ?";
                $paramsC = array($cDesc, $cSymb, $cDec, $cCode);
                $msgC = "Mata Uang Berhasil Diupdate!";
            } else {
                $cekC = sqlsrv_query($conn, "SELECT CURR_CODE FROM dbo.CURR WHERE CURR_CODE = ?", array($cCode));
                if ($cekC && sqlsrv_has_rows($cekC)) {
                    echo "<script>alert('Gagal! CURR_CODE $cCode sudah ada.');</script>";
                    $paramsC = null;
                } else {
                    $sqlC = "INSERT INTO dbo.CURR (CURR_CODE, CURR_DESC, CURR_SYMBOL, CURR_DEC) VALUES (?, ?, ?, ?)";
                    $paramsC = array($cCode, $cDesc, $cSymb, $cDec);
                    $msgC = "Mata Uang Baru Berhasil Disimpan!";
                }
            }

            if ($paramsC) {
                $stmtC = sqlsrv_query($conn, $sqlC, $paramsC);
                if ($stmtC) {
                    echo "<script>alert('$msgC'); window.location.href='{$urlPrefix}tab=currency&curr_code=" . urlencode($cCode) . "';</script>";
                } else {
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'>Error Database: " . print_r(sqlsrv_errors(), true) . "</div>";
                }
            }
        }
    }
}

// ==================================================================================
// BAGIAN 6: PROSES DATA (POST) - COLOUR CRUD
// ==================================================================================
if ($activeTab == 'colour') {
    if (isset($_POST['btnHapusColour'])) {
        $colourIdDel = isset($_POST['hapus_colour_id']) ? intval($_POST['hapus_colour_id']) : 0;
        if ($colourIdDel > 0) {
            $sqlDelCol = "DELETE FROM dbo.COLOUR WHERE ID = ?";
            $stmtDelCol = sqlsrv_query($conn, $sqlDelCol, array($colourIdDel));
            if ($stmtDelCol) {
                echo "<script>alert('Warna Berhasil Dihapus!'); window.location.href='{$urlPrefix}tab=colour';</script>";
            } else {
                $err = htmlspecialchars(print_r(sqlsrv_errors(), true));
                echo "<div class='alert alert-danger' style='margin-bottom:15px;'><b>Gagal Hapus Warna:</b><br><pre>{$err}</pre></div>";
            }
        }
    }

    if (isset($_POST['btnSimpanColour']) || isset($_POST['btnUpdateColour'])) {
        $isUpdateCol = isset($_POST['btnUpdateColour']);
        $colId   = isset($_POST['ID']) ? intval($_POST['ID']) : 0;
        $colName = isset($_POST['COLOUR']) ? trim($_POST['COLOUR']) : '';

        if (empty($colName)) {
            echo "<div class='alert alert-warning' style='margin-bottom:15px;'><i class='fa fa-warning'></i> Nama Warna wajib diisi!</div>";
        } else {
            if ($isUpdateCol && $colId > 0) {
                $sqlCol = "UPDATE dbo.COLOUR SET COLOUR = ? WHERE ID = ?";
                $paramsCol = array($colName, $colId);
                $msgCol = "Warna Berhasil Diupdate!";
            } else {
                $sqlCol = "INSERT INTO dbo.COLOUR (COLOUR) VALUES (?); SELECT SCOPE_IDENTITY() AS NEW_ID;";
                $paramsCol = array($colName);
                $msgCol = "Warna Baru Berhasil Disimpan!";
            }

            $stmtCol = sqlsrv_query($conn, $sqlCol, $paramsCol);
            if ($stmtCol) {
                if (!$isUpdateCol) {
                    sqlsrv_next_result($stmtCol);
                    $rowId = sqlsrv_fetch_array($stmtCol, SQLSRV_FETCH_ASSOC);
                    $colId = isset($rowId['NEW_ID']) ? intval($rowId['NEW_ID']) : 0;
                }
                echo "<script>alert('$msgCol'); window.location.href='{$urlPrefix}tab=colour&colour_id=$colId';</script>";
            } else {
                echo "<div class='alert alert-danger' style='margin-bottom:15px;'>Error Database: " . print_r(sqlsrv_errors(), true) . "</div>";
            }
        }
    }
}

// ==================================================================================
// BAGIAN 7: PROSES DATA (POST) - MASTER BARANG
// ==================================================================================
if ($activeTab == 'barang') {
    if (isset($_POST['btnHapus'])) {
        $idToDelete = isset($_POST['hapus_id']) ? $_POST['hapus_id'] : '';
        if ($idToDelete) {
            $cekTrans = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM INV_TRAN WHERE ITEM_CODE=?", array($idToDelete));
            $isUsedTrans = ($cekTrans && sqlsrv_fetch_array($cekTrans));

            $cekTags = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM TAGS WHERE ITEM_CODE=?", array($idToDelete));
            $isUsedTags = ($cekTags && sqlsrv_fetch_array($cekTags));

            if ($isUsedTrans || $isUsedTags) {
                echo "<div class='alert alert-danger alert-dismissible' style='margin-bottom:15px;'>
                        <button type='button' class='close' data-dismiss='alert'>&times;</button>
                        <h4><i class='icon fa fa-ban'></i> GAGAL MENGHAPUS!</h4>
                        Barang '{$idToDelete}' tidak bisa dihapus karena sudah dipakai dalam data Transaksi atau Stock Opname.
                      </div>";
            } else {
                $sqlDel = "DELETE FROM ITEMS WHERE ITEM_CODE = ?";
                $stmtDel = sqlsrv_query($conn, $sqlDel, array($idToDelete));
                if ($stmtDel) {
                    echo "<script>alert('Barang {$idToDelete} Berhasil Dihapus!'); window.location.href='{$urlPrefix}tab=barang';</script>";
                } else {
                    $err = htmlspecialchars(print_r(sqlsrv_errors(), true));
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'><b>Gagal Hapus:</b><br><pre>{$err}</pre></div>";
                }
            }
        }
    }

    if (isset($_POST['btnSimpan']) || isset($_POST['btnUpdate'])) {
        $isUpdate = isset($_POST['btnUpdate']);
        $code = isset($_POST['ITEM_CODE']) ? trim($_POST['ITEM_CODE']) : '';
        $name = isset($_POST['ITEM_NAME']) ? trim($_POST['ITEM_NAME']) : '';
        $unit = isset($_POST['ITEM_UNIT']) ? trim($_POST['ITEM_UNIT']) : '';
        $cost = isset($_POST['ITEM_COST']) ? trim($_POST['ITEM_COST']) : 0;
        $curr = isset($_POST['ITEM_CUR']) ? trim($_POST['ITEM_CUR']) : 'IDR';
        $itty = isset($_POST['ITTY_CODE']) ? trim($_POST['ITTY_CODE']) : '';
        $itemNo = isset($_POST['ITEM_NO']) ? trim($_POST['ITEM_NO']) : '';
        $inactive = isset($_POST['ITEM_INACTIVE']) ? 1 : 0;
        $forsale  = isset($_POST['ITEM_FORSALE']) ? 1 : 0;
        $inv      = isset($_POST['ITEM_INV']) ? 1 : 0;

        if (empty($code) || empty($name)) {
            echo "<div class='alert alert-warning' style='margin-bottom:15px;'><i class='fa fa-warning'></i> Kode dan Nama Barang wajib diisi!</div>";
        } else {
            if ($isUpdate) {
                $sql = "UPDATE ITEMS SET ITEM_NAME=?, ITEM_UNIT=?, ITEM_COST=?, ITEM_CUR=?, ITTY_CODE=?, 
                        ITEM_INACTIVE=?, ITEM_FORSALE=?, ITEM_INV=?, ITEM_NO=? 
                        WHERE ITEM_CODE=?";
                $params = array($name, $unit, $cost, $curr, $itty, $inactive, $forsale, $inv, $itemNo, $code);
                $msg = "Data Barang Berhasil Diupdate!";
            } else {
                $cek = sqlsrv_query($conn, "SELECT ITEM_CODE FROM ITEMS WHERE ITEM_CODE=?", array($code));
                if (sqlsrv_has_rows($cek)) {
                    echo "<script>alert('Gagal! Kode Barang $code sudah ada.');</script>";
                    $params = null;
                } else {
                    $sql = "INSERT INTO ITEMS (ITEM_NAME, ITEM_UNIT, ITEM_COST, ITEM_CUR, ITTY_CODE, 
                            ITEM_INACTIVE, ITEM_FORSALE, ITEM_INV, ITEM_CODE, ITEM_NO) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = array($name, $unit, $cost, $curr, $itty, $inactive, $forsale, $inv, $code, $itemNo);
                    $msg = "Data Barang Baru Berhasil Disimpan!";
                }
            }

            if ($params) {
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) {
                    echo "<script>alert('$msg'); window.location.href='{$urlPrefix}tab=barang&id=$code';</script>";
                } else {
                    echo "<div class='alert alert-danger' style='margin-bottom:15px;'>Error Database: " . print_r(sqlsrv_errors(), true) . "</div>";
                }
            }
        }
    }
}

// ==================================================================================
// BAGIAN 8: QUERY VIEW DATA
// ==================================================================================

// --- DATA COMPANY ---
if ($activeTab == 'company') {
    $currentCoID = isset($_GET['co_set']) ? intval($_GET['co_set']) : 0;
    $coData = array(
        'CO_SET'=>'', 'CO_COMPANY'=>'', 'CO_ADDR1'=>'', 'CO_ADDR2'=>'', 'CO_CITY'=>'',
        'CO_KODEPOS'=>'', 'CO_PHONE'=>'', 'CO_FAX'=>'', 'CO_NPWP'=>'', 'CO_BASECUR'=>'IDR',
        'CO_BCSIGNER'=>'', 'CO_POSIGNER'=>'', 'KPBC_ID'=>'', 'CO_VERSION'=>'1.0'
    );

    if ($mode != 'new') {
        if ($currentCoID <= 0) {
            $qFirstCo = sqlsrv_query($conn, "SELECT TOP 1 CO_SET FROM dbo.COMPANY ORDER BY CO_SET ASC");
            if ($qFirstCo && ($rFirstCo = sqlsrv_fetch_array($qFirstCo, SQLSRV_FETCH_ASSOC))) {
                $currentCoID = intval($rFirstCo['CO_SET']);
            }
        }
        if ($currentCoID > 0) {
            $qCo = sqlsrv_query($conn, "SELECT * FROM dbo.COMPANY WHERE CO_SET = ?", array($currentCoID));
            if ($qCo && ($rCo = sqlsrv_fetch_array($qCo, SQLSRV_FETCH_ASSOC))) {
                $coData = array_merge($coData, $rCo);
            }
        }
    }

    $prevCoID = $nextCoID = $firstCoID = $lastCoID = null;
    if (!$isEntry && $currentCoID > 0) {
        $qP = sqlsrv_query($conn, "SELECT TOP 1 CO_SET FROM dbo.COMPANY WHERE CO_SET < ? ORDER BY CO_SET DESC", array($currentCoID)); 
        if($qP && ($r=sqlsrv_fetch_array($qP))) $prevCoID = $r['CO_SET'];
        $qN = sqlsrv_query($conn, "SELECT TOP 1 CO_SET FROM dbo.COMPANY WHERE CO_SET > ? ORDER BY CO_SET ASC", array($currentCoID)); 
        if($qN && ($r=sqlsrv_fetch_array($qN))) $nextCoID = $r['CO_SET'];
        $qF = sqlsrv_query($conn, "SELECT TOP 1 CO_SET FROM dbo.COMPANY ORDER BY CO_SET ASC"); 
        if($qF && ($r=sqlsrv_fetch_array($qF))) $firstCoID = $r['CO_SET'];
        $qL = sqlsrv_query($conn, "SELECT TOP 1 CO_SET FROM dbo.COMPANY ORDER BY CO_SET DESC"); 
        if($qL && ($r=sqlsrv_fetch_array($qL))) $lastCoID = $r['CO_SET'];
    }

    $listCompany = array();
    $qAllCo = sqlsrv_query($conn, "SELECT CO_SET, CO_COMPANY, CO_CITY, CO_PHONE, CO_NPWP, CO_BASECUR FROM dbo.COMPANY ORDER BY CO_SET ASC");
    if ($qAllCo) {
        while ($rc = sqlsrv_fetch_array($qAllCo, SQLSRV_FETCH_ASSOC)) {
            $listCompany[] = $rc;
        }
    }
}

// --- DATA ITEM TYPE ---
if ($activeTab == 'item_type') {
    $currentIttyCode = isset($_GET['itty_code']) ? trim($_GET['itty_code']) : '';
    $ittyData = array(
        'ITTY_CODE'=>'', 'ITTY_DESC'=>'', 'ITTY_FIRST'=>'', 'ITTY_LAST'=>'', 'LOC_ID'=>'', 'CAT_ID'=>''
    );

    if ($mode != 'new') {
        if ($currentIttyCode === '') {
            $qFirstItty = sqlsrv_query($conn, "SELECT TOP 1 ITTY_CODE FROM dbo.ITTY WHERE ITTY_CODE IS NOT NULL AND ITTY_CODE <> '' ORDER BY ITTY_CODE ASC");
            if ($qFirstItty && ($rFirstItty = sqlsrv_fetch_array($qFirstItty, SQLSRV_FETCH_ASSOC))) {
                $currentIttyCode = trim($rFirstItty['ITTY_CODE']);
            }
        }
        if ($currentIttyCode !== '') {
            $qIttyRow = sqlsrv_query($conn, "SELECT * FROM dbo.ITTY WHERE ITTY_CODE = ?", array($currentIttyCode));
            if ($qIttyRow && ($rIttyRow = sqlsrv_fetch_array($qIttyRow, SQLSRV_FETCH_ASSOC))) {
                $ittyData = array_merge($ittyData, $rIttyRow);
            }
        }
    }

    $prevItty = $nextItty = $firstItty = $lastItty = null;
    if (!$isEntry && $currentIttyCode !== '') {
        $qP = sqlsrv_query($conn, "SELECT TOP 1 ITTY_CODE FROM dbo.ITTY WHERE ITTY_CODE < ? AND ITTY_CODE <> '' ORDER BY ITTY_CODE DESC", array($currentIttyCode)); 
        if($qP && ($r=sqlsrv_fetch_array($qP))) $prevItty = $r['ITTY_CODE'];
        $qN = sqlsrv_query($conn, "SELECT TOP 1 ITTY_CODE FROM dbo.ITTY WHERE ITTY_CODE > ? AND ITTY_CODE <> '' ORDER BY ITTY_CODE ASC", array($currentIttyCode)); 
        if($qN && ($r=sqlsrv_fetch_array($qN))) $nextItty = $r['ITTY_CODE'];
        $qF = sqlsrv_query($conn, "SELECT TOP 1 ITTY_CODE FROM dbo.ITTY WHERE ITTY_CODE <> '' ORDER BY ITTY_CODE ASC"); 
        if($qF && ($r=sqlsrv_fetch_array($qF))) $firstItty = $r['ITTY_CODE'];
        $qL = sqlsrv_query($conn, "SELECT TOP 1 ITTY_CODE FROM dbo.ITTY WHERE ITTY_CODE <> '' ORDER BY ITTY_CODE DESC"); 
        if($qL && ($r=sqlsrv_fetch_array($qL))) $lastItty = $r['ITTY_CODE'];
    }

    $listItty = array();
    $qAllItty = sqlsrv_query($conn, "SELECT ITTY_CODE, ITTY_DESC, ITTY_FIRST, ITTY_LAST, LOC_ID, CAT_ID FROM dbo.ITTY ORDER BY ITTY_CODE ASC");
    if ($qAllItty) {
        while ($ri = sqlsrv_fetch_array($qAllItty, SQLSRV_FETCH_ASSOC)) {
            $listItty[] = $ri;
        }
    }
}

// --- DATA TRANS. TYPE & DETAIL ---
if ($activeTab == 'trans_type') {
    $currentTrtyCode = isset($_GET['trty_code']) ? trim($_GET['trty_code']) : '';
    $trtyData = array(
        'TRTY_CODE' => '', 'TRTY_DESC' => '', 'COTY_ID' => '', 'TRGR_ID' => '', 'TRTY_BCIO' => ''
    );

    if ($mode != 'new') {
        if ($currentTrtyCode === '') {
            $qFirstTrty = sqlsrv_query($conn, "SELECT TOP 1 TRTY_CODE FROM dbo.TRTY WHERE TRTY_CODE IS NOT NULL AND TRTY_CODE <> '' ORDER BY TRTY_CODE ASC");
            if ($qFirstTrty && ($rFirstTrty = sqlsrv_fetch_array($qFirstTrty, SQLSRV_FETCH_ASSOC))) {
                $currentTrtyCode = trim($rFirstTrty['TRTY_CODE']);
            }
        }
        if ($currentTrtyCode !== '') {
            $qTrtyRow = sqlsrv_query($conn, "SELECT * FROM dbo.TRTY WHERE TRTY_CODE = ?", array($currentTrtyCode));
            if ($qTrtyRow && ($rTrtyRow = sqlsrv_fetch_array($qTrtyRow, SQLSRV_FETCH_ASSOC))) {
                $trtyData = array_merge($trtyData, $rTrtyRow);
            }
        }
    }

    $prevTrty = $nextTrty = $firstTrty = $lastTrty = null;
    if (!$isEntry && $currentTrtyCode !== '') {
        $qP = sqlsrv_query($conn, "SELECT TOP 1 TRTY_CODE FROM dbo.TRTY WHERE TRTY_CODE < ? AND TRTY_CODE <> '' ORDER BY TRTY_CODE DESC", array($currentTrtyCode)); 
        if($qP && ($r=sqlsrv_fetch_array($qP))) $prevTrty = $r['TRTY_CODE'];
        $qN = sqlsrv_query($conn, "SELECT TOP 1 TRTY_CODE FROM dbo.TRTY WHERE TRTY_CODE > ? AND TRTY_CODE <> '' ORDER BY TRTY_CODE ASC", array($currentTrtyCode)); 
        if($qN && ($r=sqlsrv_fetch_array($qN))) $nextTrty = $r['TRTY_CODE'];
        $qF = sqlsrv_query($conn, "SELECT TOP 1 TRTY_CODE FROM dbo.TRTY WHERE TRTY_CODE <> '' ORDER BY TRTY_CODE ASC"); 
        if($qF && ($r=sqlsrv_fetch_array($qF))) $firstTrty = $r['TRTY_CODE'];
        $qL = sqlsrv_query($conn, "SELECT TOP 1 TRTY_CODE FROM dbo.TRTY WHERE TRTY_CODE <> '' ORDER BY TRTY_CODE DESC"); 
        if($qL && ($r=sqlsrv_fetch_array($qL))) $lastTrty = $r['TRTY_CODE'];
    }

    $listTrtyLoc = array();
    if ($currentTrtyCode !== '') {
        $sqlTL = "SELECT TL.TRTY_CODE, TL.LOC_ID, TL.TRTY_SIGN, TL.TRTY_INOUT, 
                         TL.TRTY_OK1_SIGN, TL.TRTY_OK2_SIGN, TL.TRTY_HOLD_SIGN, TL.TRTY_NG_SIGN,
                         L.LOC_CODE, L.LOC_NAME
                  FROM dbo.TRTY_LOC TL
                  INNER JOIN dbo.LOC L ON TL.LOC_ID = L.LOC_ID
                  WHERE TL.TRTY_CODE = ?
                  ORDER BY L.LOC_CODE ASC";
        $qTL = sqlsrv_query($conn, $sqlTL, array($currentTrtyCode));
        if ($qTL) {
            while ($rtl = sqlsrv_fetch_array($qTL, SQLSRV_FETCH_ASSOC)) {
                $listTrtyLoc[] = $rtl;
            }
        }
    }

    $listAllTrty = array();
    $qAllTrty = sqlsrv_query($conn, "SELECT TRTY_CODE, TRTY_DESC FROM dbo.TRTY ORDER BY TRTY_CODE ASC");
    if ($qAllTrty) {
        while ($rat = sqlsrv_fetch_array($qAllTrty, SQLSRV_FETCH_ASSOC)) {
            $listAllTrty[] = $rat;
        }
    }

    $listAllLoc = array();
    $qLoc = sqlsrv_query($conn, "SELECT LOC_ID, LOC_CODE, LOC_NAME FROM dbo.LOC ORDER BY LOC_CODE ASC");
    if ($qLoc) {
        while ($rl = sqlsrv_fetch_array($qLoc, SQLSRV_FETCH_ASSOC)) {
            $listAllLoc[] = $rl;
        }
    }
}

// --- DATA LOCATIONS (LOC) ---
if ($activeTab == 'locations') {
    $currentLocID = isset($_GET['loc_id']) ? intval($_GET['loc_id']) : 0;
    $locData = array(
        'LOC_ID' => '', 'LOC_CODE' => '', 'LOC_NAME' => '', 'LOC_ADJTRTY' => '',
        'LOC_TYPE' => 'I', 'LOC_LEVEL' => 1, 'LOC_GROUP' => '', 'LOC_PARENT' => '', 'LOC_VISIBLE' => 1
    );

    if ($mode != 'new') {
        if ($currentLocID <= 0) {
            $qFirstLoc = sqlsrv_query($conn, "SELECT TOP 1 LOC_ID FROM dbo.LOC ORDER BY LOC_CODE ASC");
            if ($qFirstLoc && ($rFirstLoc = sqlsrv_fetch_array($qFirstLoc, SQLSRV_FETCH_ASSOC))) {
                $currentLocID = intval($rFirstLoc['LOC_ID']);
            }
        }
        if ($currentLocID > 0) {
            $qLocRow = sqlsrv_query($conn, "SELECT * FROM dbo.LOC WHERE LOC_ID = ?", array($currentLocID));
            if ($qLocRow && ($rLocRow = sqlsrv_fetch_array($qLocRow, SQLSRV_FETCH_ASSOC))) {
                $locData = array_merge($locData, $rLocRow);
            }
        }
    }

    $prevLocID = $nextLocID = $firstLocID = $lastLocID = null;
    if (!$isEntry && $currentLocID > 0) {
        $curCode = $locData['LOC_CODE'];
        $qP = sqlsrv_query($conn, "SELECT TOP 1 LOC_ID FROM dbo.LOC WHERE LOC_CODE < ? ORDER BY LOC_CODE DESC", array($curCode)); 
        if($qP && ($r=sqlsrv_fetch_array($qP))) $prevLocID = $r['LOC_ID'];
        $qN = sqlsrv_query($conn, "SELECT TOP 1 LOC_ID FROM dbo.LOC WHERE LOC_CODE > ? ORDER BY LOC_CODE ASC", array($curCode)); 
        if($qN && ($r=sqlsrv_fetch_array($qN))) $nextLocID = $r['LOC_ID'];
        $qF = sqlsrv_query($conn, "SELECT TOP 1 LOC_ID FROM dbo.LOC ORDER BY LOC_CODE ASC"); 
        if($qF && ($r=sqlsrv_fetch_array($qF))) $firstLocID = $r['LOC_ID'];
        $qL = sqlsrv_query($conn, "SELECT TOP 1 LOC_ID FROM dbo.LOC ORDER BY LOC_CODE DESC"); 
        if($qL && ($r=sqlsrv_fetch_array($qL))) $lastLocID = $r['LOC_ID'];
    }

    $listAllLocations = array();
    $qAllLocations = sqlsrv_query($conn, "SELECT LOC_ID, LOC_CODE, LOC_NAME, LOC_TYPE, LOC_GROUP, LOC_VISIBLE FROM dbo.LOC ORDER BY LOC_CODE ASC");
    if ($qAllLocations) {
        while ($rloc = sqlsrv_fetch_array($qAllLocations, SQLSRV_FETCH_ASSOC)) {
            $listAllLocations[] = $rloc;
        }
    }
}

// --- DATA CURRENCY (CURR) ---
if ($activeTab == 'currency') {
    $currentCurrCode = isset($_GET['curr_code']) ? trim($_GET['curr_code']) : '';
    $currData = array(
        'CURR_CODE' => '', 'CURR_DESC' => '', 'CURR_SYMBOL' => '', 'CURR_DEC' => 2
    );

    if ($mode != 'new') {
        if ($currentCurrCode === '') {
            $qFirstCurr = sqlsrv_query($conn, "SELECT TOP 1 CURR_CODE FROM dbo.CURR ORDER BY CURR_CODE ASC");
            if ($qFirstCurr && ($rFirstCurr = sqlsrv_fetch_array($qFirstCurr, SQLSRV_FETCH_ASSOC))) {
                $currentCurrCode = trim($rFirstCurr['CURR_CODE']);
            }
        }
        if ($currentCurrCode !== '') {
            $qCurrRow = sqlsrv_query($conn, "SELECT * FROM dbo.CURR WHERE CURR_CODE = ?", array($currentCurrCode));
            if ($qCurrRow && ($rCurrRow = sqlsrv_fetch_array($qCurrRow, SQLSRV_FETCH_ASSOC))) {
                $currData = array_merge($currData, $rCurrRow);
            }
        }
    }

    $prevCurr = $nextCurr = $firstCurr = $lastCurr = null;
    if (!$isEntry && $currentCurrCode !== '') {
        $qP = sqlsrv_query($conn, "SELECT TOP 1 CURR_CODE FROM dbo.CURR WHERE CURR_CODE < ? ORDER BY CURR_CODE DESC", array($currentCurrCode)); 
        if($qP && ($r=sqlsrv_fetch_array($qP))) $prevCurr = $r['CURR_CODE'];
        $qN = sqlsrv_query($conn, "SELECT TOP 1 CURR_CODE FROM dbo.CURR WHERE CURR_CODE > ? ORDER BY CURR_CODE ASC", array($currentCurrCode)); 
        if($qN && ($r=sqlsrv_fetch_array($qN))) $nextCurr = $r['CURR_CODE'];
        $qF = sqlsrv_query($conn, "SELECT TOP 1 CURR_CODE FROM dbo.CURR ORDER BY CURR_CODE ASC"); 
        if($qF && ($r=sqlsrv_fetch_array($qF))) $firstCurr = $r['CURR_CODE'];
        $qL = sqlsrv_query($conn, "SELECT TOP 1 CURR_CODE FROM dbo.CURR ORDER BY CURR_CODE DESC"); 
        if($qL && ($r=sqlsrv_fetch_array($qL))) $lastCurr = $r['CURR_CODE'];
    }

    $listAllCurr = array();
    $qAllC = sqlsrv_query($conn, "SELECT CURR_CODE, CURR_DESC, CURR_SYMBOL, CURR_DEC FROM dbo.CURR ORDER BY CURR_CODE ASC");
    if ($qAllC) {
        while ($rc = sqlsrv_fetch_array($qAllC, SQLSRV_FETCH_ASSOC)) {
            $listAllCurr[] = $rc;
        }
    }
}

// --- DATA COLOUR (COLOUR) ---
if ($activeTab == 'colour') {
    $currentColourID = isset($_GET['colour_id']) ? intval($_GET['colour_id']) : 0;
    $colourData = array(
        'ID' => '', 'COLOUR' => ''
    );

    if ($mode != 'new') {
        if ($currentColourID <= 0) {
            $qFirstCol = sqlsrv_query($conn, "SELECT TOP 1 ID FROM dbo.COLOUR ORDER BY COLOUR ASC");
            if ($qFirstCol && ($rFirstCol = sqlsrv_fetch_array($qFirstCol, SQLSRV_FETCH_ASSOC))) {
                $currentColourID = intval($rFirstCol['ID']);
            }
        }
        if ($currentColourID > 0) {
            $qColRow = sqlsrv_query($conn, "SELECT * FROM dbo.COLOUR WHERE ID = ?", array($currentColourID));
            if ($qColRow && ($rColRow = sqlsrv_fetch_array($qColRow, SQLSRV_FETCH_ASSOC))) {
                $colourData = array_merge($colourData, $rColRow);
            }
        }
    }

    $prevCol = $nextCol = $firstCol = $lastCol = null;
    if (!$isEntry && $currentColourID > 0) {
        $curName = $colourData['COLOUR'];
        $qP = sqlsrv_query($conn, "SELECT TOP 1 ID FROM dbo.COLOUR WHERE COLOUR < ? ORDER BY COLOUR DESC", array($curName)); 
        if($qP && ($r=sqlsrv_fetch_array($qP))) $prevCol = $r['ID'];
        $qN = sqlsrv_query($conn, "SELECT TOP 1 ID FROM dbo.COLOUR WHERE COLOUR > ? ORDER BY COLOUR ASC", array($curName)); 
        if($qN && ($r=sqlsrv_fetch_array($qN))) $nextCol = $r['ID'];
        $qF = sqlsrv_query($conn, "SELECT TOP 1 ID FROM dbo.COLOUR ORDER BY COLOUR ASC"); 
        if($qF && ($r=sqlsrv_fetch_array($qF))) $firstCol = $r['ID'];
        $qL = sqlsrv_query($conn, "SELECT TOP 1 ID FROM dbo.COLOUR ORDER BY COLOUR DESC"); 
        if($qL && ($r=sqlsrv_fetch_array($qL))) $lastCol = $r['ID'];
    }

    $listAllColours = array();
    $qAllCols = sqlsrv_query($conn, "SELECT ID, COLOUR FROM dbo.COLOUR ORDER BY COLOUR ASC");
    if ($qAllCols) {
        while ($rco = sqlsrv_fetch_array($qAllCols, SQLSRV_FETCH_ASSOC)) {
            $listAllColours[] = $rco;
        }
    }
}

// --- DATA MASTER BARANG ---
if ($activeTab == 'barang') {
    $currentID = isset($_GET['id']) ? trim($_GET['id']) : null;
    $data = array(
        'ITEM_CODE'=>'', 'ITEM_NAME'=>'', 'ITEM_NO'=>'', 'ITEM_UNIT'=>'Pcs', 'ITEM_COST'=>0, 'ITEM_CUR'=>'IDR',
        'ITTY_CODE'=>'RM', 'ITEM_INACTIVE'=>0, 'ITEM_FORSALE'=>1, 'ITEM_INV'=>1, 'ITEM_ONHAND'=>0
    );

    if ($mode != 'new') {
        if (empty($currentID)) {
            $qFirst = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE IS NOT NULL AND ITEM_CODE <> '' ORDER BY ITEM_CODE ASC");
            if ($qFirst && ($rFirst = sqlsrv_fetch_array($qFirst))) {
                $currentID = trim($rFirst['ITEM_CODE']);
            }
        }
        if ($currentID !== null && $currentID !== '') {
            $qData = sqlsrv_query($conn, "SELECT * FROM ITEMS WHERE ITEM_CODE = ?", array($currentID));
            if ($qData && ($rData = sqlsrv_fetch_array($qData, SQLSRV_FETCH_ASSOC))) {
                $data = $rData;
            }
        }
    }

    $prevID = $nextID = $firstID = $lastID = null;
    if (!$isEntry && $currentID !== null && $currentID !== '') {
        $qP = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE < ? AND ITEM_CODE <> '' ORDER BY ITEM_CODE DESC", array($currentID)); 
        if($qP && ($r=sqlsrv_fetch_array($qP))) $prevID=$r['ITEM_CODE'];
        $qN = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE > ? AND ITEM_CODE <> '' ORDER BY ITEM_CODE ASC", array($currentID)); 
        if($qN && ($r=sqlsrv_fetch_array($qN))) $nextID=$r['ITEM_CODE'];
        $qF = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE <> '' ORDER BY ITEM_CODE ASC"); 
        if($qF && ($r=sqlsrv_fetch_array($qF))) $firstID=$r['ITEM_CODE'];
        $qL = sqlsrv_query($conn, "SELECT TOP 1 ITEM_CODE FROM ITEMS WHERE ITEM_CODE <> '' ORDER BY ITEM_CODE DESC"); 
        if($qL && ($r=sqlsrv_fetch_array($qL))) $lastID=$r['ITEM_CODE'];
    }

    $optItty = "";
    $qItty = sqlsrv_query($conn, "SELECT ITTY_CODE, ITTY_DESC FROM ITTY ORDER BY ITTY_CODE ASC");
    if ($qItty) {
        while($r=sqlsrv_fetch_array($qItty)) {
            $sel = ($data['ITTY_CODE'] == $r['ITTY_CODE']) ? 'selected' : '';
            $optItty .= "<option value='{$r['ITTY_CODE']}' $sel>{$r['ITTY_CODE']} - {$r['ITTY_DESC']}</option>";
        }
    }
}
?>

<!-- TAB NAVIGATION (COMPANY -> ITEM TYPE -> TRANS TYPE -> LOCATIONS -> CURRENCY -> COLOUR -> MASTER BARANG) -->
<div class="nav-tabs-custom" style="box-shadow: none; margin-bottom: 15px;">
    <ul class="nav nav-tabs">
        <li class="<?php echo ($activeTab == 'company') ? 'active' : ''; ?>">
            <a href="<?php echo $urlPrefix; ?>tab=company"><i class="fa fa-building text-primary"></i> <b>Company</b></a>
        </li>
        <li class="<?php echo ($activeTab == 'item_type') ? 'active' : ''; ?>">
            <a href="<?php echo $urlPrefix; ?>tab=item_type"><i class="fa fa-tags text-primary"></i> <b>Item Type</b></a>
        </li>
        <li class="<?php echo ($activeTab == 'trans_type') ? 'active' : ''; ?>">
            <a href="<?php echo $urlPrefix; ?>tab=trans_type"><i class="fa fa-exchange text-primary"></i> <b>Trans. Type</b></a>
        </li>
        <li class="<?php echo ($activeTab == 'locations') ? 'active' : ''; ?>">
            <a href="<?php echo $urlPrefix; ?>tab=locations"><i class="fa fa-map-marker text-primary"></i> <b>Locations</b></a>
        </li>
        <li class="<?php echo ($activeTab == 'currency') ? 'active' : ''; ?>">
            <a href="<?php echo $urlPrefix; ?>tab=currency"><i class="fa fa-money text-primary"></i> <b>Currency</b></a>
        </li>
        <li class="<?php echo ($activeTab == 'colour') ? 'active' : ''; ?>">
            <a href="<?php echo $urlPrefix; ?>tab=colour"><i class="fa fa-paint-brush text-primary"></i> <b>Colour</b></a>
        </li>
        <li class="<?php echo ($activeTab == 'barang') ? 'active' : ''; ?>">
            <a href="<?php echo $urlPrefix; ?>tab=barang"><i class="fa fa-cube text-primary"></i> <b>Master Barang</b></a>
        </li>
    </ul>
</div>

<!-- ================================================================================ -->
<!-- TAB CONTENT: COMPANY -->
<!-- ================================================================================ -->
<?php if ($activeTab == 'company'): ?>
<form method="POST" action="<?php echo $urlPrefix; ?>tab=company">
    <input type="hidden" name="hapus_co_set" value="<?php echo htmlspecialchars($coData['CO_SET']); ?>">
    <input type="hidden" name="CO_SET" value="<?php echo htmlspecialchars($coData['CO_SET']); ?>">

    <div class="row">
        <div class="col-md-9">
            <div class="box box-solid bg-gray-light" style="margin-bottom: 15px;">
                <div class="box-body" style="padding: 10px;">
                    <div class="pull-left">
                        <div class="btn-group">
                            <a href="<?php echo $urlPrefix; ?>tab=company&co_set=<?php echo urlencode($firstCoID); ?>" class="btn btn-default <?php echo (!$firstCoID || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=company&co_set=<?php echo urlencode($prevCoID); ?>" class="btn btn-default <?php echo (!$prevCoID || $isEntry)?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                            <button type="button" class="btn btn-default disabled" style="font-weight:bold; min-width:180px; color:#333;">
                                <?php echo $isEntry ? (($mode == 'new') ? 'INPUT COMPANY BARU' : 'EDIT COMPANY') : (!empty($coData['CO_COMPANY']) ? htmlspecialchars($coData['CO_COMPANY']) : 'DATA KOSONG'); ?>
                            </button>
                            <a href="<?php echo $urlPrefix; ?>tab=company&co_set=<?php echo urlencode($nextCoID); ?>" class="btn btn-default <?php echo (!$nextCoID || $isEntry)?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=company&co_set=<?php echo urlencode($lastCoID); ?>" class="btn btn-default <?php echo (!$lastCoID || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                        </div>
                    </div>

                    <div class="pull-right">
                        <div class="btn-group">
                            <?php if ($isEntry): ?>
                                <?php if ($mode == 'edit'): ?>
                                    <button type="submit" name="btnUpdateCompany" class="btn btn-warning" onclick="return confirm('Simpan perubahan data perusahaan ini?');">
                                        <i class="fa fa-save"></i> <b>UPDATE (SIMPAN)</b>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="btnSimpanCompany" class="btn btn-success" onclick="return confirm('Simpan perusahaan baru?');">
                                        <i class="fa fa-save"></i> <b>SIMPAN BARU</b>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo $urlPrefix; ?>tab=company<?php echo ($currentCoID ? '&co_set='.urlencode($currentCoID) : ''); ?>" class="btn btn-default">
                                    <i class="fa fa-times-circle"></i> BATAL
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $urlPrefix; ?>tab=company&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>">
                                    <i class="fa fa-plus"></i> BARU
                                </a>
                                <a href="<?php echo $urlPrefix; ?>tab=company&mode=edit&co_set=<?php echo urlencode($currentCoID); ?>" class="btn btn-warning <?php echo (!$currentCoID || $isEntry)?'disabled':''; ?>">
                                    <i class="fa fa-pencil"></i> EDIT
                                </a>
                                <?php if (!$isEntry && $currentCoID): ?>
                                    <button type="submit" name="btnHapusCompany" class="btn btn-danger" onclick="return confirm('Hapus Perusahaan <?php echo htmlspecialchars($coData['CO_COMPANY']); ?>?');">
                                        <i class="fa fa-trash"></i> HAPUS
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <div class="box <?php echo $isEntry ? 'box-warning' : 'box-primary'; ?>">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-building"></i> INFORMASI PERUSAHAAN (COMPANY)</h3>
                </div>
                <div class="box-body" style="<?php echo $isEntry ? 'background-color: #fff9e6;' : ''; ?>">
                    <div class="row">
                        <div class="col-md-2 form-group">
                            <label>ID / SET</label>
                            <input type="text" class="form-control text-primary" style="font-weight:bold; background:#eee;" value="<?php echo htmlspecialchars($coData['CO_SET']); ?>" readonly>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>COMPANY NAME</label>
                            <input type="text" class="form-control" name="CO_COMPANY" value="<?php echo htmlspecialchars($coData['CO_COMPANY']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> required maxlength="40">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>NPWP</label>
                            <input type="text" class="form-control" name="CO_NPWP" value="<?php echo htmlspecialchars($coData['CO_NPWP']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="20">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group"><label>ADDRESS 1</label><input type="text" class="form-control" name="CO_ADDR1" value="<?php echo htmlspecialchars($coData['CO_ADDR1']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="50"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group"><label>ADDRESS 2</label><input type="text" class="form-control" name="CO_ADDR2" value="<?php echo htmlspecialchars($coData['CO_ADDR2']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="50"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group"><label>CITY</label><input type="text" class="form-control" name="CO_CITY" value="<?php echo htmlspecialchars($coData['CO_CITY']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="30"></div>
                        <div class="col-md-2 form-group"><label>KODE POS</label><input type="text" class="form-control" name="CO_KODEPOS" value="<?php echo htmlspecialchars($coData['CO_KODEPOS']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="5"></div>
                        <div class="col-md-3 form-group"><label>PHONE</label><input type="text" class="form-control" name="CO_PHONE" value="<?php echo htmlspecialchars($coData['CO_PHONE']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="35"></div>
                        <div class="col-md-3 form-group"><label>FAX</label><input type="text" class="form-control" name="CO_FAX" value="<?php echo htmlspecialchars($coData['CO_FAX']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="35"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group"><label>BASE CURRENCY</label><input type="text" class="form-control" name="CO_BASECUR" value="<?php echo htmlspecialchars(!empty($coData['CO_BASECUR']) ? $coData['CO_BASECUR'] : 'IDR'); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="3"></div>
                        <div class="col-md-3 form-group"><label>BC SIGNER</label><input type="text" class="form-control" name="CO_BCSIGNER" value="<?php echo htmlspecialchars($coData['CO_BCSIGNER']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="25"></div>
                        <div class="col-md-3 form-group"><label>PO SIGNER</label><input type="text" class="form-control" name="CO_POSIGNER" value="<?php echo htmlspecialchars($coData['CO_POSIGNER']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="25"></div>
                        <div class="col-md-2 form-group"><label>KPBC ID</label><input type="number" class="form-control" name="KPBC_ID" value="<?php echo htmlspecialchars($coData['KPBC_ID']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>></div>
                        <div class="col-md-1 form-group"><label>VER</label><input type="text" class="form-control" name="CO_VERSION" value="<?php echo htmlspecialchars(!empty($coData['CO_VERSION']) ? $coData['CO_VERSION'] : '1.0'); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="10"></div>
                    </div>
                </div>
            </div>

            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title" style="font-size:14px;"><i class="fa fa-list"></i> DAFTAR COMPANY</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-bordered table-hover" style="margin-bottom:0;">
                        <thead>
                            <tr style="background:#f4f4f4;">
                                <th style="width: 50px; text-align:center;">SET</th>
                                <th>COMPANY</th>
                                <th>KOTA</th>
                                <th>PHONE</th>
                                <th>NPWP</th>
                                <th>BASE CURR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listCompany) > 0): foreach ($listCompany as $rc): ?>
                                <tr class="<?php echo ($rc['CO_SET'] == $currentCoID) ? 'warning' : ''; ?>" style="cursor:pointer;" onclick="location.href='<?php echo $urlPrefix; ?>tab=company&co_set=<?php echo urlencode($rc['CO_SET']); ?>';">
                                    <td class="text-center font-weight-bold"><?php echo htmlspecialchars($rc['CO_SET']); ?></td>
                                    <td><b><?php echo htmlspecialchars($rc['CO_COMPANY']); ?></b></td>
                                    <td><?php echo htmlspecialchars($rc['CO_CITY']); ?></td>
                                    <td><?php echo htmlspecialchars($rc['CO_PHONE']); ?></td>
                                    <td><?php echo htmlspecialchars($rc['CO_NPWP']); ?></td>
                                    <td><span class="label label-primary"><?php echo htmlspecialchars($rc['CO_BASECUR']); ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Belum ada data company.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="box box-solid box-default">
                <div class="box-header with-border" style="background-color: #222d32; color: #fff; text-align:center;">
                    <h3 class="box-title" style="font-size: 14px; font-weight:bold;">STATUS AKSI</h3>
                </div>
                <div class="box-body" style="text-align: center;">
                    <div class="alert <?php echo $isEntry ? ($mode == 'edit' ? 'alert-warning' : 'alert-success') : 'alert-info'; ?>" style="margin-bottom: 0; padding: 10px;">
                        <i class="fa <?php echo $isEntry ? ($mode == 'edit' ? 'fa-pencil' : 'fa-plus-circle') : 'fa-eye'; ?> fa-2x"></i><br>
                        <b><?php echo $isEntry ? ($mode == 'edit' ? 'Edit Company' : 'Baru Company') : 'Mode Lihat Data'; ?></b>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<!-- ================================================================================ -->
<!-- TAB CONTENT: ITEM TYPE -->
<!-- ================================================================================ -->
<?php if ($activeTab == 'item_type'): ?>
<form method="POST" action="<?php echo $urlPrefix; ?>tab=item_type">
    <input type="hidden" name="hapus_itty_code" value="<?php echo htmlspecialchars($ittyData['ITTY_CODE']); ?>">

    <div class="row">
        <div class="col-md-7">
            <div class="box box-solid bg-gray-light" style="margin-bottom: 15px;">
                <div class="box-body" style="padding: 10px;">
                    <div class="pull-left">
                        <div class="btn-group">
                            <a href="<?php echo $urlPrefix; ?>tab=item_type&itty_code=<?php echo urlencode($firstItty); ?>" class="btn btn-default <?php echo (!$firstItty || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=item_type&itty_code=<?php echo urlencode($prevItty); ?>" class="btn btn-default <?php echo (!$prevItty || $isEntry)?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                            <button type="button" class="btn btn-default disabled" style="font-weight:bold; min-width:140px; color:#333;">
                                <?php echo $isEntry ? (($mode == 'new') ? 'INPUT BARU' : 'EDIT ITTY') : (!empty($ittyData['ITTY_CODE']) ? htmlspecialchars($ittyData['ITTY_CODE']) : 'KOSONG'); ?>
                            </button>
                            <a href="<?php echo $urlPrefix; ?>tab=item_type&itty_code=<?php echo urlencode($nextItty); ?>" class="btn btn-default <?php echo (!$nextItty || $isEntry)?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=item_type&itty_code=<?php echo urlencode($lastItty); ?>" class="btn btn-default <?php echo (!$lastItty || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                        </div>
                    </div>

                    <div class="pull-right">
                        <div class="btn-group">
                            <?php if ($isEntry): ?>
                                <?php if ($mode == 'edit'): ?>
                                    <button type="submit" name="btnUpdateItty" class="btn btn-warning" onclick="return confirm('Simpan update tipe barang ini?');">
                                        <i class="fa fa-save"></i> <b>UPDATE (SIMPAN)</b>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="btnSimpanItty" class="btn btn-success" onclick="return confirm('Simpan tipe barang baru?');">
                                        <i class="fa fa-save"></i> <b>SIMPAN BARU</b>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo $urlPrefix; ?>tab=item_type<?php echo ($currentIttyCode ? '&itty_code='.urlencode($currentIttyCode) : ''); ?>" class="btn btn-default">
                                    <i class="fa fa-times-circle"></i> BATAL
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $urlPrefix; ?>tab=item_type&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>">
                                    <i class="fa fa-plus"></i> BARU
                                </a>
                                <a href="<?php echo $urlPrefix; ?>tab=item_type&mode=edit&itty_code=<?php echo urlencode($currentIttyCode); ?>" class="btn btn-warning <?php echo (!$currentIttyCode || $isEntry)?'disabled':''; ?>">
                                    <i class="fa fa-pencil"></i> EDIT
                                </a>
                                <?php if (!$isEntry && $currentIttyCode): ?>
                                    <button type="submit" name="btnHapusItty" class="btn btn-danger" onclick="return confirm('Hapus Item Type <?php echo htmlspecialchars($currentIttyCode); ?>?');">
                                        <i class="fa fa-trash"></i> HAPUS
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <div class="box <?php echo $isEntry ? 'box-warning' : 'box-primary'; ?>">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-tags"></i> INFORMASI ITEM TYPE</h3>
                </div>
                <div class="box-body" style="<?php echo $isEntry ? 'background-color: #fff9e6;' : ''; ?>">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>ITTY_CODE</label>
                            <input type="text" class="form-control text-primary font-weight-bold" name="ITTY_CODE" value="<?php echo htmlspecialchars($ittyData['ITTY_CODE']); ?>" <?php echo ($mode != 'new') ? 'readonly' : ''; ?> required maxlength="10" style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-8 form-group">
                            <label>ITTY_DESC</label>
                            <input type="text" class="form-control" name="ITTY_DESC" value="<?php echo htmlspecialchars($ittyData['ITTY_DESC']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> required maxlength="50">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>ITTY_FIRST</label>
                            <input type="text" class="form-control" name="ITTY_FIRST" value="<?php echo htmlspecialchars($ittyData['ITTY_FIRST']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="30">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>ITTY_LAST</label>
                            <input type="text" class="form-control" name="ITTY_LAST" value="<?php echo htmlspecialchars($ittyData['ITTY_LAST']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="30">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>LOC_ID</label><input type="number" class="form-control" name="LOC_ID" value="<?php echo htmlspecialchars($ittyData['LOC_ID']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>></div>
                        <div class="col-md-6 form-group"><label>CAT_ID</label><input type="number" class="form-control" name="CAT_ID" value="<?php echo htmlspecialchars($ittyData['CAT_ID']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="box box-solid box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title" style="font-size: 14px; font-weight:bold;"><i class="fa fa-list"></i> DAFTAR ITEM TYPE</h3>
                </div>
                <div class="box-body" style="padding: 5px;">
                    <input type="text" id="filterItty" class="form-control input-sm" placeholder="Filter tipe..." style="margin-bottom: 8px;">
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd;">
                        <table class="table table-bordered table-hover" id="tabelItty" style="margin-bottom:0; font-size:12px;">
                            <thead>
                                <tr style="background:#f4f4f4;"><th>KODE</th><th>DESKRIPSI</th><th>FIRST</th><th>LAST</th></tr>
                            </thead>
                            <tbody>
                                <?php if (count($listItty) > 0): foreach ($listItty as $ri): ?>
                                    <tr class="<?php echo ($ri['ITTY_CODE'] === $currentIttyCode) ? 'warning' : ''; ?>" style="cursor:pointer;" onclick="location.href='<?php echo $urlPrefix; ?>tab=item_type&itty_code=<?php echo urlencode($ri['ITTY_CODE']); ?>';">
                                        <td class="font-weight-bold text-primary"><b><?php echo htmlspecialchars($ri['ITTY_CODE']); ?></b></td>
                                        <td><?php echo htmlspecialchars($ri['ITTY_DESC']); ?></td>
                                        <td><?php echo htmlspecialchars($ri['ITTY_FIRST']); ?></td>
                                        <td><?php echo htmlspecialchars($ri['ITTY_LAST']); ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada data item type.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
$(document).ready(function(){
    $("#filterItty").on("keyup", function() {
        var v = $(this).val().toLowerCase();
        $("#tabelItty tbody tr").filter(function() { $(this).toggle($(this).text().toLowerCase().indexOf(v) > -1); });
    });
});
</script>
<?php endif; ?>

<!-- ================================================================================ -->
<!-- TAB CONTENT: TRANS. TYPE -->
<!-- ================================================================================ -->
<?php if ($activeTab == 'trans_type'): ?>
<div class="row">
    <div class="col-md-7">
        <form method="POST" action="<?php echo $urlPrefix; ?>tab=trans_type">
            <input type="hidden" name="hapus_trty_code" value="<?php echo htmlspecialchars($trtyData['TRTY_CODE']); ?>">

            <div class="box box-solid bg-gray-light" style="margin-bottom: 12px;">
                <div class="box-body" style="padding: 10px;">
                    <div class="pull-left">
                        <div class="btn-group">
                            <a href="<?php echo $urlPrefix; ?>tab=trans_type&trty_code=<?php echo urlencode($firstTrty); ?>" class="btn btn-default <?php echo (!$firstTrty || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=trans_type&trty_code=<?php echo urlencode($prevTrty); ?>" class="btn btn-default <?php echo (!$prevTrty || $isEntry)?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                            <button type="button" class="btn btn-default disabled" style="font-weight:bold; min-width:140px; color:#333;">
                                <?php echo $isEntry ? (($mode == 'new') ? 'INPUT BARU' : 'EDIT TRANS') : (!empty($trtyData['TRTY_CODE']) ? htmlspecialchars($trtyData['TRTY_CODE']) : 'KOSONG'); ?>
                            </button>
                            <a href="<?php echo $urlPrefix; ?>tab=trans_type&trty_code=<?php echo urlencode($nextTrty); ?>" class="btn btn-default <?php echo (!$nextTrty || $isEntry)?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=trans_type&trty_code=<?php echo urlencode($lastTrty); ?>" class="btn btn-default <?php echo (!$lastTrty || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                        </div>
                    </div>

                    <div class="pull-right">
                        <div class="btn-group">
                            <?php if ($isEntry): ?>
                                <?php if ($mode == 'edit'): ?>
                                    <button type="submit" name="btnUpdateTrty" class="btn btn-warning" onclick="return confirm('Simpan update tipe transaksi beserta pengaturan lokasi ini?');">
                                        <i class="fa fa-save"></i> <b>UPDATE (SIMPAN)</b>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="btnSimpanTrty" class="btn btn-success" onclick="return confirm('Simpan tipe transaksi baru?');">
                                        <i class="fa fa-save"></i> <b>SIMPAN BARU</b>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo $urlPrefix; ?>tab=trans_type<?php echo ($currentTrtyCode ? '&trty_code='.urlencode($currentTrtyCode) : ''); ?>" class="btn btn-default">
                                    <i class="fa fa-times-circle"></i> BATAL
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $urlPrefix; ?>tab=trans_type&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>">
                                    <i class="fa fa-plus"></i> BARU
                                </a>
                                <a href="<?php echo $urlPrefix; ?>tab=trans_type&mode=edit&trty_code=<?php echo urlencode($currentTrtyCode); ?>" class="btn btn-warning <?php echo (!$currentTrtyCode || $isEntry)?'disabled':''; ?>">
                                    <i class="fa fa-pencil"></i> EDIT
                                </a>
                                <?php if (!$isEntry && $currentTrtyCode): ?>
                                    <button type="submit" name="btnHapusTrty" class="btn btn-danger" onclick="return confirm('Hapus Trans. Type <?php echo htmlspecialchars($currentTrtyCode); ?>?');">
                                        <i class="fa fa-trash"></i> HAPUS
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <div class="box <?php echo $isEntry ? 'box-warning' : 'box-primary'; ?>" style="margin-bottom: 15px;">
                <div class="box-body" style="<?php echo $isEntry ? 'background-color: #fff9e6;' : ''; ?>">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px;">TRANS CODE</label>
                            <input type="text" class="form-control text-primary font-weight-bold" name="TRTY_CODE" value="<?php echo htmlspecialchars($trtyData['TRTY_CODE']); ?>" <?php echo ($mode != 'new') ? 'readonly' : ''; ?> required maxlength="10">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label style="font-size:12px;">DESC</label>
                            <input type="text" class="form-control" name="TRTY_DESC" value="<?php echo htmlspecialchars($trtyData['TRTY_DESC']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> required maxlength="60">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label style="font-size:12px;">CONTAC TYPE</label>
                            <select class="form-control" name="COTY_ID" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <option value=""></option>
                                <option value="Supplier" <?php echo ($trtyData['COTY_ID'] == 'Supplier') ? 'selected' : ''; ?>>Supplier</option>
                                <option value="Customer" <?php echo ($trtyData['COTY_ID'] == 'Customer') ? 'selected' : ''; ?>>Customer</option>
                                <option value="Subcon" <?php echo ($trtyData['COTY_ID'] == 'Subcon') ? 'selected' : ''; ?>>Subcon / Vendor</option>
                                <option value="User" <?php echo ($trtyData['COTY_ID'] == 'User') ? 'selected' : ''; ?>>User / Department</option>
                                <option value="Production" <?php echo ($trtyData['COTY_ID'] == 'Production') ? 'selected' : ''; ?>>Production</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group"><label style="font-size:12px;">TRGR ID</label><input type="number" class="form-control" name="TRGR_ID" value="<?php echo htmlspecialchars($trtyData['TRGR_ID']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>></div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px;">BC I/O</label>
                            <select class="form-control" name="TRTY_BCIO" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <option value=""></option>
                                <option value="In" <?php echo ($trtyData['TRTY_BCIO'] == 'In') ? 'selected' : ''; ?>>In</option>
                                <option value="Out" <?php echo ($trtyData['TRTY_BCIO'] == 'Out') ? 'selected' : ''; ?>>Out</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-solid" style="border: 1px solid #ccc;">
                <div class="box-header with-border" style="background:#f4f4f4; padding: 8px 10px;">
                    <h3 class="box-title" style="font-size: 13px; font-weight:bold;"><i class="fa fa-cubes"></i> PENGATURAN LOKASI GUDANG (LOC)</h3>
                    <?php if (!$isEntry && !empty($currentTrtyCode)): ?>
                        <button type="button" class="btn btn-xs btn-primary pull-right" data-toggle="modal" data-target="#modalTambahLoc">
                            <i class="fa fa-plus"></i> Tambah Lokasi
                        </button>
                    <?php endif; ?>
                </div>
                <div class="box-body no-padding table-responsive">
                    <table class="table table-bordered text-center" style="margin-bottom:0; font-size:12px;">
                        <thead>
                            <tr style="background:#eaeaea;">
                                <th rowspan="2" style="vertical-align:middle; text-align:left;">LOC. CODE</th>
                                <th rowspan="2" style="vertical-align:middle; text-align:left;">LOC. NAME</th>
                                <th rowspan="2" style="vertical-align:middle; width:70px;">I/O</th>
                                <th style="background:#d9edf7;">Ori</th>
                                <th colspan="4" style="background:#dff0d8;">New</th>
                                <th rowspan="2" style="vertical-align:middle; width:75px;">AKSI</th>
                            </tr>
                            <tr style="background:#f4f4f4;">
                                <th style="background:#eaf2f8; font-size:11px; width:65px;">Old SIGN</th>
                                <th style="background:#f0f8ed; font-size:11px; width:55px;">OK1</th>
                                <th style="background:#f0f8ed; font-size:11px; width:55px;">OK2</th>
                                <th style="background:#f0f8ed; font-size:11px; width:55px;">HOLD</th>
                                <th style="background:#f0f8ed; font-size:11px; width:55px;">NG</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listTrtyLoc) > 0): foreach ($listTrtyLoc as $rtl): $locId = intval($rtl['LOC_ID']); ?>
                                <tr style="<?php echo $isEntry ? 'background-color:#fffdf5;' : ''; ?>">
                                    <td style="text-align:left; vertical-align:middle;"><b><?php echo htmlspecialchars($rtl['LOC_CODE']); ?></b></td>
                                    <td style="text-align:left; vertical-align:middle;"><?php echo htmlspecialchars($rtl['LOC_NAME']); ?></td>
                                    <td style="vertical-align:middle;">
                                        <?php if ($isEntry): ?>
                                            <input type="text" name="loc_items[<?php echo $locId; ?>][TRTY_INOUT]" value="<?php echo htmlspecialchars($rtl['TRTY_INOUT']); ?>" class="form-control input-sm text-center" style="padding:2px; height:26px;">
                                        <?php else: ?>
                                            <span class="label <?php echo ($rtl['TRTY_INOUT'] == 'In' || $rtl['TRTY_INOUT'] == '1') ? 'label-success' : 'label-warning'; ?>"><?php echo htmlspecialchars($rtl['TRTY_INOUT']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <?php if ($isEntry): ?><input type="number" name="loc_items[<?php echo $locId; ?>][TRTY_SIGN]" value="<?php echo htmlspecialchars($rtl['TRTY_SIGN']); ?>" class="form-control input-sm text-center" style="padding:2px; height:26px;"><?php else: echo htmlspecialchars($rtl['TRTY_SIGN']); endif; ?>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <?php if ($isEntry): ?><input type="number" name="loc_items[<?php echo $locId; ?>][TRTY_OK1_SIGN]" value="<?php echo htmlspecialchars($rtl['TRTY_OK1_SIGN']); ?>" class="form-control input-sm text-center" style="padding:2px; height:26px;"><?php else: echo htmlspecialchars($rtl['TRTY_OK1_SIGN']); endif; ?>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <?php if ($isEntry): ?><input type="number" name="loc_items[<?php echo $locId; ?>][TRTY_OK2_SIGN]" value="<?php echo htmlspecialchars($rtl['TRTY_OK2_SIGN']); ?>" class="form-control input-sm text-center" style="padding:2px; height:26px;"><?php else: echo htmlspecialchars($rtl['TRTY_OK2_SIGN']); endif; ?>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <?php if ($isEntry): ?><input type="number" name="loc_items[<?php echo $locId; ?>][TRTY_HOLD_SIGN]" value="<?php echo htmlspecialchars($rtl['TRTY_HOLD_SIGN']); ?>" class="form-control input-sm text-center" style="padding:2px; height:26px;"><?php else: echo htmlspecialchars($rtl['TRTY_HOLD_SIGN']); endif; ?>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <?php if ($isEntry): ?><input type="number" name="loc_items[<?php echo $locId; ?>][TRTY_NG_SIGN]" value="<?php echo htmlspecialchars($rtl['TRTY_NG_SIGN']); ?>" class="form-control input-sm text-center" style="padding:2px; height:26px;"><?php else: echo htmlspecialchars($rtl['TRTY_NG_SIGN']); endif; ?>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <?php if (!$isEntry): ?>
                                            <button type="button" class="btn btn-xs btn-warning btn-edit-single" 
                                                    data-locid="<?php echo $locId; ?>"
                                                    data-locname="<?php echo htmlspecialchars($rtl['LOC_CODE'] . ' - ' . $rtl['LOC_NAME']); ?>"
                                                    data-inout="<?php echo htmlspecialchars($rtl['TRTY_INOUT']); ?>"
                                                    data-sign="<?php echo htmlspecialchars($rtl['TRTY_SIGN']); ?>"
                                                    data-ok1="<?php echo htmlspecialchars($rtl['TRTY_OK1_SIGN']); ?>"
                                                    data-ok2="<?php echo htmlspecialchars($rtl['TRTY_OK2_SIGN']); ?>"
                                                    data-hold="<?php echo htmlspecialchars($rtl['TRTY_HOLD_SIGN']); ?>"
                                                    data-ng="<?php echo htmlspecialchars($rtl['TRTY_NG_SIGN']); ?>"
                                                    title="Edit Baris Lokasi"><i class="fa fa-pencil"></i></button>
                                        <?php endif; ?>
                                        <button type="submit" name="btnHapusLocDetail" value="<?php echo $locId; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Hapus lokasi ini dari tipe transaksi?');" title="Hapus Lokasi"><i class="fa fa-times"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="9" class="text-center text-muted" style="padding:15px;">Belum ada lokasi yang diatur untuk transaksi <b><?php echo htmlspecialchars($currentTrtyCode); ?></b>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <div class="col-md-5">
        <div class="box box-solid box-primary">
            <div class="box-header with-border" style="padding: 9px 12px;">
                <h3 class="box-title" style="font-size: 13px; font-weight:bold;"><i class="fa fa-list"></i> DAFTAR TRANS. TYPE</h3>
                <span class="label label-primary pull-right"><?php echo count($listAllTrty); ?> Trans</span>
            </div>
            <div class="box-body" style="padding: 6px;">
                <input type="text" id="filterTrty" class="form-control input-sm" placeholder="Ketik kode / deskripsi..." style="margin-bottom: 8px;">
                <div style="max-height: 480px; overflow-y: auto; border: 1px solid #ddd;">
                    <table class="table table-bordered table-hover" id="tabelTrty" style="margin-bottom:0; font-size:12px;">
                        <thead><tr style="background:#f4f4f4; position: sticky; top:0; z-index:1;"><th style="width: 65px;">CODE</th><th>DESC</th></tr></thead>
                        <tbody>
                            <?php if (count($listAllTrty) > 0): foreach ($listAllTrty as $rat): ?>
                                <tr class="<?php echo ($rat['TRTY_CODE'] === $currentTrtyCode) ? 'warning' : ''; ?>" style="cursor:pointer;" onclick="location.href='<?php echo $urlPrefix; ?>tab=trans_type&trty_code=<?php echo urlencode($rat['TRTY_CODE']); ?>';">
                                    <td class="text-primary"><?php if ($rat['TRTY_CODE'] === $currentTrtyCode): ?><i class="fa fa-caret-right text-red"></i> <?php endif; ?><b><?php echo htmlspecialchars($rat['TRTY_CODE']); ?></b></td>
                                    <td><?php echo htmlspecialchars($rat['TRTY_DESC']); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="2" class="text-center text-muted">Belum ada data Trans. Type.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahLoc" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <form method="POST" action="<?php echo $urlPrefix; ?>tab=trans_type">
            <input type="hidden" name="DETAIL_TRTY_CODE" value="<?php echo htmlspecialchars($currentTrtyCode); ?>">
            <div class="modal-content">
                <div class="modal-header bg-primary" style="padding: 10px 15px;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" style="font-size:14px;"><i class="fa fa-map-marker"></i> Tambah Lokasi Transaksi</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pilih Lokasi:</label>
                        <select class="form-control" name="LOC_ID" required>
                            <option value="">-- Pilih Lokasi --</option>
                            <?php foreach ($listAllLoc as $loc): ?>
                                <option value="<?php echo $loc['LOC_ID']; ?>"><?php echo htmlspecialchars($loc['LOC_CODE']) . ' - ' . htmlspecialchars($loc['LOC_NAME']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-xs-6 form-group"><label>I / O:</label><input type="text" class="form-control" name="TRTY_INOUT" value="In"></div>
                        <div class="col-xs-6 form-group"><label>Ori SIGN:</label><input type="number" class="form-control" name="TRTY_SIGN" value="1"></div>
                    </div>
                    <div class="row">
                        <div class="col-xs-3 form-group"><label>OK1</label><input type="number" class="form-control input-sm" name="TRTY_OK1_SIGN" value="1"></div>
                        <div class="col-xs-3 form-group"><label>OK2</label><input type="number" class="form-control input-sm" name="TRTY_OK2_SIGN" value="0"></div>
                        <div class="col-xs-3 form-group"><label>HOLD</label><input type="number" class="form-control input-sm" name="TRTY_HOLD_SIGN" value="0"></div>
                        <div class="col-xs-3 form-group"><label>NG</label><input type="number" class="form-control input-sm" name="TRTY_NG_SIGN" value="0"></div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 8px 15px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" name="btnSimpanLocDetail" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Lokasi</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditLoc" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <form method="POST" action="<?php echo $urlPrefix; ?>tab=trans_type">
            <input type="hidden" name="EDIT_TRTY_CODE" value="<?php echo htmlspecialchars($currentTrtyCode); ?>">
            <input type="hidden" name="EDIT_LOC_ID" id="edit_loc_id" value="">
            <div class="modal-content">
                <div class="modal-header bg-warning" style="padding: 10px 15px;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" style="font-size:14px; font-weight:bold;"><i class="fa fa-pencil"></i> Edit Pengaturan Lokasi</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Lokasi:</label><input type="text" id="edit_loc_name" class="form-control" readonly style="background:#eee; font-weight:bold;"></div>
                    <div class="row">
                        <div class="col-xs-6 form-group"><label>I / O:</label><input type="text" class="form-control" name="EDIT_TRTY_INOUT" id="edit_trty_inout"></div>
                        <div class="col-xs-6 form-group"><label>Ori SIGN:</label><input type="number" class="form-control" name="EDIT_TRTY_SIGN" id="edit_trty_sign"></div>
                    </div>
                    <div class="row">
                        <div class="col-xs-3 form-group"><label>OK1</label><input type="number" class="form-control input-sm" name="EDIT_TRTY_OK1_SIGN" id="edit_trty_ok1"></div>
                        <div class="col-xs-3 form-group"><label>OK2</label><input type="number" class="form-control input-sm" name="EDIT_TRTY_OK2_SIGN" id="edit_trty_ok2"></div>
                        <div class="col-xs-3 form-group"><label>HOLD</label><input type="number" class="form-control input-sm" name="EDIT_TRTY_HOLD_SIGN" id="edit_trty_hold"></div>
                        <div class="col-xs-3 form-group"><label>NG</label><input type="number" class="form-control input-sm" name="EDIT_TRTY_NG_SIGN" id="edit_trty_ng"></div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 8px 15px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" name="btnUpdateSingleLoc" class="btn btn-warning"><i class="fa fa-save"></i> Update Lokasi</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function(){
    $("#filterTrty").on("keyup", function() {
        var v = $(this).val().toLowerCase();
        $("#tabelTrty tbody tr").filter(function() { $(this).toggle($(this).text().toLowerCase().indexOf(v) > -1); });
    });
    $(".btn-edit-single").on("click", function() {
        var btn = $(this);
        $("#edit_loc_id").val(btn.data("locid"));
        $("#edit_loc_name").val(btn.data("locname"));
        $("#edit_trty_inout").val(btn.data("inout"));
        $("#edit_trty_sign").val(btn.data("sign"));
        $("#edit_trty_ok1").val(btn.data("ok1"));
        $("#edit_trty_ok2").val(btn.data("ok2"));
        $("#edit_trty_hold").val(btn.data("hold"));
        $("#edit_trty_ng").val(btn.data("ng"));
        $("#modalEditLoc").modal("show");
    });
});
</script>
<?php endif; ?>

<!-- ================================================================================ -->
<!-- TAB CONTENT 4: LOCATIONS (LOC) -->
<!-- ================================================================================ -->
<?php if ($activeTab == 'locations'): ?>
<form method="POST" action="<?php echo $urlPrefix; ?>tab=locations">
    <input type="hidden" name="hapus_loc_id" value="<?php echo htmlspecialchars($locData['LOC_ID']); ?>">
    <input type="hidden" name="LOC_ID" value="<?php echo htmlspecialchars($locData['LOC_ID']); ?>">

    <div class="row">
        <!-- FORM KIRI -->
        <div class="col-md-7">
            <div class="box box-solid bg-gray-light" style="margin-bottom: 15px;">
                <div class="box-body" style="padding: 10px;">
                    <div class="pull-left">
                        <div class="btn-group">
                            <a href="<?php echo $urlPrefix; ?>tab=locations&loc_id=<?php echo urlencode($firstLocID); ?>" class="btn btn-default <?php echo (!$firstLocID || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=locations&loc_id=<?php echo urlencode($prevLocID); ?>" class="btn btn-default <?php echo (!$prevLocID || $isEntry)?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                            <button type="button" class="btn btn-default disabled" style="font-weight:bold; min-width:140px; color:#333;">
                                <?php echo $isEntry ? (($mode == 'new') ? 'INPUT LOKASI' : 'EDIT LOKASI') : (!empty($locData['LOC_CODE']) ? htmlspecialchars($locData['LOC_CODE']) : 'KOSONG'); ?>
                            </button>
                            <a href="<?php echo $urlPrefix; ?>tab=locations&loc_id=<?php echo urlencode($nextLocID); ?>" class="btn btn-default <?php echo (!$nextLocID || $isEntry)?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=locations&loc_id=<?php echo urlencode($lastLocID); ?>" class="btn btn-default <?php echo (!$lastLocID || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                        </div>
                    </div>

                    <div class="pull-right">
                        <div class="btn-group">
                            <?php if ($isEntry): ?>
                                <?php if ($mode == 'edit'): ?>
                                    <button type="submit" name="btnUpdateLoc" class="btn btn-warning" onclick="return confirm('Simpan update data lokasi ini?');">
                                        <i class="fa fa-save"></i> <b>UPDATE (SIMPAN)</b>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="btnSimpanLoc" class="btn btn-success" onclick="return confirm('Simpan lokasi baru?');">
                                        <i class="fa fa-save"></i> <b>SIMPAN BARU</b>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo $urlPrefix; ?>tab=locations<?php echo ($currentLocID ? '&loc_id='.urlencode($currentLocID) : ''); ?>" class="btn btn-default">
                                    <i class="fa fa-times-circle"></i> BATAL
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $urlPrefix; ?>tab=locations&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>">
                                    <i class="fa fa-plus"></i> BARU
                                </a>
                                <a href="<?php echo $urlPrefix; ?>tab=locations&mode=edit&loc_id=<?php echo urlencode($currentLocID); ?>" class="btn btn-warning <?php echo (!$currentLocID || $isEntry)?'disabled':''; ?>">
                                    <i class="fa fa-pencil"></i> EDIT
                                </a>
                                <?php if (!$isEntry && $currentLocID): ?>
                                    <button type="submit" name="btnHapusLoc" class="btn btn-danger" onclick="return confirm('Hapus Lokasi <?php echo htmlspecialchars($locData['LOC_CODE']); ?>?');">
                                        <i class="fa fa-trash"></i> HAPUS
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <div class="box <?php echo $isEntry ? 'box-warning' : 'box-primary'; ?>">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-map-marker"></i> INFORMASI LOKASI GUDANG (LOC)</h3>
                </div>
                <div class="box-body" style="<?php echo $isEntry ? 'background-color: #fff9e6;' : ''; ?>">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>LOC_CODE</label>
                            <input type="text" class="form-control text-primary font-weight-bold" name="LOC_CODE" 
                                   value="<?php echo htmlspecialchars($locData['LOC_CODE']); ?>" 
                                   <?php echo ($mode != 'new') ? 'readonly' : ''; ?> required maxlength="4" style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>LOC_NAME (Nama Lokasi)</label>
                            <input type="text" class="form-control" name="LOC_NAME" 
                                   value="<?php echo htmlspecialchars($locData['LOC_NAME']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="25">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>LOC_TYPE</label>
                            <select class="form-control" name="LOC_TYPE" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <option value="I" <?php echo ($locData['LOC_TYPE'] == 'I') ? 'selected' : ''; ?>>I - Internal</option>
                                <option value="E" <?php echo ($locData['LOC_TYPE'] == 'E') ? 'selected' : ''; ?>>E - External/Vendor</option>
                                <option value="C" <?php echo ($locData['LOC_TYPE'] == 'C') ? 'selected' : ''; ?>>C - Customer</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>ADJUST TRTY</label>
                            <input type="text" class="form-control" name="LOC_ADJTRTY" 
                                   value="<?php echo htmlspecialchars($locData['LOC_ADJTRTY']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="2">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>LOC_GROUP</label>
                            <input type="text" class="form-control" name="LOC_GROUP" 
                                   value="<?php echo htmlspecialchars($locData['LOC_GROUP']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="4">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>LOC_LEVEL</label>
                            <input type="number" class="form-control" name="LOC_LEVEL" 
                                   value="<?php echo htmlspecialchars($locData['LOC_LEVEL']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>LOC_PARENT</label>
                            <input type="number" class="form-control" name="LOC_PARENT" 
                                   value="<?php echo htmlspecialchars($locData['LOC_PARENT']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?>>
                        </div>
                    </div>

                    <div class="row" style="margin-top: 5px;">
                        <div class="col-md-12">
                            <label class="checkbox-inline" style="font-weight: bold;">
                                <input type="checkbox" name="LOC_VISIBLE" value="1" 
                                       <?php echo (intval($locData['LOC_VISIBLE']) == 1) ? 'checked' : ''; ?> 
                                       <?php echo !$isEntry ? 'disabled' : ''; ?>> VISIBLE (Tampilkan pada Transaksi)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIST KANAN -->
        <div class="col-md-5">
            <div class="box box-solid box-primary">
                <div class="box-header with-border" style="padding: 9px 12px;">
                    <h3 class="box-title" style="font-size: 13px; font-weight:bold;"><i class="fa fa-list"></i> DAFTAR LOKASI</h3>
                    <span class="label label-primary pull-right"><?php echo count($listAllLocations); ?> Lokasi</span>
                </div>
                <div class="box-body" style="padding: 6px;">
                    <input type="text" id="filterLoc" class="form-control input-sm" placeholder="Ketik kode / nama lokasi..." style="margin-bottom: 8px;">
                    <div style="max-height: 420px; overflow-y: auto; border: 1px solid #ddd;">
                        <table class="table table-bordered table-hover" id="tabelLoc" style="margin-bottom:0; font-size:12px;">
                            <thead>
                                <tr style="background:#f4f4f4; position: sticky; top:0; z-index:1;">
                                    <th style="width: 65px;">KODE</th>
                                    <th>NAMA LOKASI</th>
                                    <th style="width: 50px;">TYPE</th>
                                    <th style="width: 60px;">VIS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($listAllLocations) > 0): foreach ($listAllLocations as $rloc): ?>
                                    <tr class="<?php echo ($rloc['LOC_ID'] == $currentLocID) ? 'warning' : ''; ?>" 
                                        style="cursor:pointer;" 
                                        onclick="location.href='<?php echo $urlPrefix; ?>tab=locations&loc_id=<?php echo urlencode($rloc['LOC_ID']); ?>';">
                                        <td class="text-primary font-weight-bold"><b><?php echo htmlspecialchars($rloc['LOC_CODE']); ?></b></td>
                                        <td><?php echo htmlspecialchars($rloc['LOC_NAME']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($rloc['LOC_TYPE']); ?></td>
                                        <td class="text-center">
                                            <?php echo ($rloc['LOC_VISIBLE'] == 1) ? '<span class="label label-success">Y</span>' : '<span class="label label-default">N</span>'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada data lokasi.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
$(document).ready(function(){
    $("#filterLoc").on("keyup", function() {
        var v = $(this).val().toLowerCase();
        $("#tabelLoc tbody tr").filter(function() { 
            $(this).toggle($(this).text().toLowerCase().indexOf(v) > -1); 
        });
    });
});
</script>
<?php endif; ?>

<!-- ================================================================================ -->
<!-- TAB CONTENT 5: CURRENCY (CURR) -->
<!-- ================================================================================ -->
<?php if ($activeTab == 'currency'): ?>
<form method="POST" action="<?php echo $urlPrefix; ?>tab=currency">
    <input type="hidden" name="hapus_curr_code" value="<?php echo htmlspecialchars($currData['CURR_CODE']); ?>">

    <div class="row">
        <!-- FORM KIRI -->
        <div class="col-md-7">
            <div class="box box-solid bg-gray-light" style="margin-bottom: 15px;">
                <div class="box-body" style="padding: 10px;">
                    <div class="pull-left">
                        <div class="btn-group">
                            <a href="<?php echo $urlPrefix; ?>tab=currency&curr_code=<?php echo urlencode($firstCurr); ?>" class="btn btn-default <?php echo (!$firstCurr || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=currency&curr_code=<?php echo urlencode($prevCurr); ?>" class="btn btn-default <?php echo (!$prevCurr || $isEntry)?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                            <button type="button" class="btn btn-default disabled" style="font-weight:bold; min-width:140px; color:#333;">
                                <?php echo $isEntry ? (($mode == 'new') ? 'INPUT MATA UANG' : 'EDIT MATA UANG') : (!empty($currData['CURR_CODE']) ? htmlspecialchars($currData['CURR_CODE']) : 'KOSONG'); ?>
                            </button>
                            <a href="<?php echo $urlPrefix; ?>tab=currency&curr_code=<?php echo urlencode($nextCurr); ?>" class="btn btn-default <?php echo (!$nextCurr || $isEntry)?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=currency&curr_code=<?php echo urlencode($lastCurr); ?>" class="btn btn-default <?php echo (!$lastCurr || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                        </div>
                    </div>

                    <div class="pull-right">
                        <div class="btn-group">
                            <?php if ($isEntry): ?>
                                <?php if ($mode == 'edit'): ?>
                                    <button type="submit" name="btnUpdateCurr" class="btn btn-warning" onclick="return confirm('Update data mata uang ini?');">
                                        <i class="fa fa-save"></i> <b>UPDATE (SIMPAN)</b>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="btnSimpanCurr" class="btn btn-success" onclick="return confirm('Simpan mata uang baru?');">
                                        <i class="fa fa-save"></i> <b>SIMPAN BARU</b>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo $urlPrefix; ?>tab=currency<?php echo ($currentCurrCode ? '&curr_code='.urlencode($currentCurrCode) : ''); ?>" class="btn btn-default">
                                    <i class="fa fa-times-circle"></i> BATAL
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $urlPrefix; ?>tab=currency&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>">
                                    <i class="fa fa-plus"></i> BARU
                                </a>
                                <a href="<?php echo $urlPrefix; ?>tab=currency&mode=edit&curr_code=<?php echo urlencode($currentCurrCode); ?>" class="btn btn-warning <?php echo (!$currentCurrCode || $isEntry)?'disabled':''; ?>">
                                    <i class="fa fa-pencil"></i> EDIT
                                </a>
                                <?php if (!$isEntry && $currentCurrCode): ?>
                                    <button type="submit" name="btnHapusCurr" class="btn btn-danger" onclick="return confirm('Hapus Mata Uang <?php echo htmlspecialchars($currentCurrCode); ?>?');">
                                        <i class="fa fa-trash"></i> HAPUS
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <div class="box <?php echo $isEntry ? 'box-warning' : 'box-primary'; ?>">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-money"></i> INFORMASI MATA UANG (CURRENCY)</h3>
                </div>
                <div class="box-body" style="<?php echo $isEntry ? 'background-color: #fff9e6;' : ''; ?>">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>CURR_CODE (Kode Valuta)</label>
                            <input type="text" class="form-control text-primary font-weight-bold" name="CURR_CODE" 
                                   value="<?php echo htmlspecialchars($currData['CURR_CODE']); ?>" 
                                   <?php echo ($mode != 'new') ? 'readonly' : ''; ?> required maxlength="3" style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-8 form-group">
                            <label>CURR_DESC (Deskripsi / Nama)</label>
                            <input type="text" class="form-control" name="CURR_DESC" 
                                   value="<?php echo htmlspecialchars($currData['CURR_DESC']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="35">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>CURR_SYMBOL (Simbol)</label>
                            <input type="text" class="form-control" name="CURR_SYMBOL" 
                                   value="<?php echo htmlspecialchars($currData['CURR_SYMBOL']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="3">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>CURR_DEC (Desimal)</label>
                            <input type="number" class="form-control" name="CURR_DEC" 
                                   value="<?php echo htmlspecialchars($currData['CURR_DEC']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?> min="0" max="4">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIST KANAN -->
        <div class="col-md-5">
            <div class="box box-solid box-primary">
                <div class="box-header with-border" style="padding: 9px 12px;">
                    <h3 class="box-title" style="font-size: 13px; font-weight:bold;"><i class="fa fa-list"></i> DAFTAR CURRENCY</h3>
                    <span class="label label-primary pull-right"><?php echo count($listAllCurr); ?> Data</span>
                </div>
                <div class="box-body" style="padding: 6px;">
                    <input type="text" id="filterCurr" class="form-control input-sm" placeholder="Ketik kode / deskripsi..." style="margin-bottom: 8px;">
                    <div style="max-height: 420px; overflow-y: auto; border: 1px solid #ddd;">
                        <table class="table table-bordered table-hover" id="tabelCurr" style="margin-bottom:0; font-size:12px;">
                            <thead>
                                <tr style="background:#f4f4f4; position: sticky; top:0; z-index:1;">
                                    <th style="width: 70px;">KODE</th>
                                    <th>DESKRIPSI</th>
                                    <th style="width: 60px;">SYMBOL</th>
                                    <th style="width: 50px;">DEC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($listAllCurr) > 0): foreach ($listAllCurr as $rc): ?>
                                    <tr class="<?php echo ($rc['CURR_CODE'] === $currentCurrCode) ? 'warning' : ''; ?>" 
                                        style="cursor:pointer;" 
                                        onclick="location.href='<?php echo $urlPrefix; ?>tab=currency&curr_code=<?php echo urlencode($rc['CURR_CODE']); ?>';">
                                        <td class="text-primary font-weight-bold"><b><?php echo htmlspecialchars($rc['CURR_CODE']); ?></b></td>
                                        <td><?php echo htmlspecialchars($rc['CURR_DESC']); ?></td>
                                        <td class="text-center font-weight-bold"><?php echo htmlspecialchars($rc['CURR_SYMBOL']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($rc['CURR_DEC']); ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada data mata uang.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
$(document).ready(function(){
    $("#filterCurr").on("keyup", function() {
        var v = $(this).val().toLowerCase();
        $("#tabelCurr tbody tr").filter(function() { 
            $(this).toggle($(this).text().toLowerCase().indexOf(v) > -1); 
        });
    });
});
</script>
<?php endif; ?>

<!-- ================================================================================ -->
<!-- TAB CONTENT 6: COLOUR (COLOUR) -->
<!-- ================================================================================ -->
<?php if ($activeTab == 'colour'): ?>
<form method="POST" action="<?php echo $urlPrefix; ?>tab=colour">
    <input type="hidden" name="hapus_colour_id" value="<?php echo htmlspecialchars($colourData['ID']); ?>">
    <input type="hidden" name="ID" value="<?php echo htmlspecialchars($colourData['ID']); ?>">

    <div class="row">
        <!-- FORM KIRI -->
        <div class="col-md-7">
            <div class="box box-solid bg-gray-light" style="margin-bottom: 15px;">
                <div class="box-body" style="padding: 10px;">
                    <div class="pull-left">
                        <div class="btn-group">
                            <a href="<?php echo $urlPrefix; ?>tab=colour&colour_id=<?php echo urlencode($firstCol); ?>" class="btn btn-default <?php echo (!$firstCol || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=colour&colour_id=<?php echo urlencode($prevCol); ?>" class="btn btn-default <?php echo (!$prevCol || $isEntry)?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                            <button type="button" class="btn btn-default disabled" style="font-weight:bold; min-width:140px; color:#333;">
                                <?php echo $isEntry ? (($mode == 'new') ? 'INPUT WARNA' : 'EDIT WARNA') : (!empty($colourData['COLOUR']) ? htmlspecialchars($colourData['COLOUR']) : 'KOSONG'); ?>
                            </button>
                            <a href="<?php echo $urlPrefix; ?>tab=colour&colour_id=<?php echo urlencode($nextCol); ?>" class="btn btn-default <?php echo (!$nextCol || $isEntry)?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=colour&colour_id=<?php echo urlencode($lastCol); ?>" class="btn btn-default <?php echo (!$lastCol || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                        </div>
                    </div>

                    <div class="pull-right">
                        <div class="btn-group">
                            <?php if ($isEntry): ?>
                                <?php if ($mode == 'edit'): ?>
                                    <button type="submit" name="btnUpdateColour" class="btn btn-warning" onclick="return confirm('Update data warna ini?');">
                                        <i class="fa fa-save"></i> <b>UPDATE (SIMPAN)</b>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="btnSimpanColour" class="btn btn-success" onclick="return confirm('Simpan warna baru?');">
                                        <i class="fa fa-save"></i> <b>SIMPAN BARU</b>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo $urlPrefix; ?>tab=colour<?php echo ($currentColourID ? '&colour_id='.urlencode($currentColourID) : ''); ?>" class="btn btn-default">
                                    <i class="fa fa-times-circle"></i> BATAL
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $urlPrefix; ?>tab=colour&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>">
                                    <i class="fa fa-plus"></i> BARU
                                </a>
                                <a href="<?php echo $urlPrefix; ?>tab=colour&mode=edit&colour_id=<?php echo urlencode($currentColourID); ?>" class="btn btn-warning <?php echo (!$currentColourID || $isEntry)?'disabled':''; ?>">
                                    <i class="fa fa-pencil"></i> EDIT
                                </a>
                                <?php if (!$isEntry && $currentColourID): ?>
                                    <button type="submit" name="btnHapusColour" class="btn btn-danger" onclick="return confirm('Hapus Warna <?php echo htmlspecialchars($colourData['COLOUR']); ?>?');">
                                        <i class="fa fa-trash"></i> HAPUS
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <div class="box <?php echo $isEntry ? 'box-warning' : 'box-primary'; ?>">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-paint-brush"></i> INFORMASI WARNA (COLOUR)</h3>
                </div>
                <div class="box-body" style="<?php echo $isEntry ? 'background-color: #fff9e6;' : ''; ?>">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>ID</label>
                            <input type="text" class="form-control text-primary font-weight-bold" 
                                   value="<?php echo htmlspecialchars($colourData['ID']); ?>" readonly style="background:#eee;">
                        </div>
                        <div class="col-md-9 form-group">
                            <label>COLOUR (Nama Warna)</label>
                            <input type="text" class="form-control" name="COLOUR" 
                                   value="<?php echo htmlspecialchars($colourData['COLOUR']); ?>" 
                                   <?php echo !$isEntry ? 'readonly' : ''; ?> required maxlength="20">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIST KANAN -->
        <div class="col-md-5">
            <div class="box box-solid box-primary">
                <div class="box-header with-border" style="padding: 9px 12px;">
                    <h3 class="box-title" style="font-size: 13px; font-weight:bold;"><i class="fa fa-list"></i> DAFTAR WARNA</h3>
                    <span class="label label-primary pull-right"><?php echo count($listAllColours); ?> Warna</span>
                </div>
                <div class="box-body" style="padding: 6px;">
                    <input type="text" id="filterColour" class="form-control input-sm" placeholder="Ketik nama warna..." style="margin-bottom: 8px;">
                    <div style="max-height: 420px; overflow-y: auto; border: 1px solid #ddd;">
                        <table class="table table-bordered table-hover" id="tabelColour" style="margin-bottom:0; font-size:12px;">
                            <thead>
                                <tr style="background:#f4f4f4; position: sticky; top:0; z-index:1;">
                                    <th style="width: 60px;">ID</th>
                                    <th>NAMA WARNA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($listAllColours) > 0): foreach ($listAllColours as $rco): ?>
                                    <tr class="<?php echo ($rco['ID'] == $currentColourID) ? 'warning' : ''; ?>" 
                                        style="cursor:pointer;" 
                                        onclick="location.href='<?php echo $urlPrefix; ?>tab=colour&colour_id=<?php echo urlencode($rco['ID']); ?>';">
                                        <td class="text-center font-weight-bold"><?php echo htmlspecialchars($rco['ID']); ?></td>
                                        <td class="text-primary font-weight-bold"><b><?php echo htmlspecialchars($rco['COLOUR']); ?></b></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">Belum ada data warna.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
$(document).ready(function(){
    $("#filterColour").on("keyup", function() {
        var v = $(this).val().toLowerCase();
        $("#tabelColour tbody tr").filter(function() { 
            $(this).toggle($(this).text().toLowerCase().indexOf(v) > -1); 
        });
    });
});
</script>
<?php endif; ?>

<!-- ================================================================================ -->
<!-- TAB CONTENT 7: MASTER BARANG (PALING AKHIR) -->
<!-- ================================================================================ -->
<?php if ($activeTab == 'barang'): ?>
<form method="POST" action="<?php echo $urlPrefix; ?>tab=barang">
    <input type="hidden" name="hapus_id" value="<?php echo htmlspecialchars($currentID); ?>">

    <div class="row">
        <div class="col-md-9">
            <div class="box box-solid bg-gray-light" style="margin-bottom: 15px;">
                <div class="box-body" style="padding: 10px;">
                    <div class="pull-left">
                        <div class="btn-group">
                            <a href="<?php echo $urlPrefix; ?>tab=barang&id=<?php echo urlencode($firstID); ?>" class="btn btn-default <?php echo (!$firstID || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-backward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=barang&id=<?php echo urlencode($prevID); ?>" class="btn btn-default <?php echo (!$prevID || $isEntry)?'disabled':''; ?>"><i class="fa fa-backward"></i></a>
                            <button type="button" class="btn btn-default disabled" style="font-weight:bold; min-width:150px; color:#333;">
                                <?php echo $isEntry ? (($mode=='new')?'INPUT BARU':'EDIT DATA') : htmlspecialchars($data['ITEM_CODE']); ?>
                            </button>
                            <a href="<?php echo $urlPrefix; ?>tab=barang&id=<?php echo urlencode($nextID); ?>" class="btn btn-default <?php echo (!$nextID || $isEntry)?'disabled':''; ?>"><i class="fa fa-forward"></i></a>
                            <a href="<?php echo $urlPrefix; ?>tab=barang&id=<?php echo urlencode($lastID); ?>" class="btn btn-default <?php echo (!$lastID || $isEntry)?'disabled':''; ?>"><i class="fa fa-fast-forward"></i></a>
                        </div>
                    </div>

                    <div class="pull-right">
                        <div class="btn-group">
                            <?php if ($isEntry): ?>
                                <?php if ($mode == 'edit'): ?>
                                    <button type="submit" name="btnUpdate" class="btn btn-warning" onclick="return confirm('Update data barang ini?');">
                                        <i class="fa fa-save"></i> <b>UPDATE (SIMPAN)</b>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="btnSimpan" class="btn btn-success" onclick="return confirm('Simpan barang baru?');">
                                        <i class="fa fa-save"></i> <b>SIMPAN BARU</b>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo $urlPrefix; ?>tab=barang<?php echo ($currentID ? '&id='.urlencode($currentID) : ''); ?>" class="btn btn-default">
                                    <i class="fa fa-times-circle"></i> BATAL
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $urlPrefix; ?>tab=barang&mode=new" class="btn btn-success <?php echo ($mode=='new')?'active':''; ?>">
                                    <i class="fa fa-plus"></i> BARU
                                </a>
                                <a href="<?php echo $urlPrefix; ?>tab=barang&mode=edit&id=<?php echo urlencode($currentID); ?>" class="btn btn-warning <?php echo (!$currentID || $isEntry)?'disabled':''; ?>">
                                    <i class="fa fa-pencil"></i> EDIT
                                </a>
                                <?php if (!$isEntry && $currentID): ?>
                                    <button type="submit" name="btnHapus" class="btn btn-danger" onclick="return confirm('Hapus Barang <?php echo htmlspecialchars($currentID); ?>?');">
                                        <i class="fa fa-trash"></i> HAPUS
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-default disabled"><i class="fa fa-trash"></i> HAPUS</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>

            <?php if(!$isEntry): ?>
            <div class="box box-solid" style="margin-bottom: 15px;">
                <div class="box-body" style="padding: 10px;">
                    <div class="row">
                        <div class="col-md-3" style="line-height: 30px;"><label><i class="fa fa-search"></i> Cari Kode / Nama:</label></div>
                        <div class="col-md-9">
                            <input type="text" id="search_item" list="list_barang" class="form-control" placeholder="Ketik minimal 1 huruf untuk mencari...">
                            <datalist id="list_barang"></datalist>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="box <?php echo $isEntry ? 'box-warning' : 'box-primary'; ?>">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-cube"></i> INFORMASI BARANG <?php echo $isEntry ? (($mode=='new') ? '(INPUT BARU)' : '(MODE EDIT)') : '(READ ONLY)'; ?></h3>
                </div>
                <div class="box-body" style="<?php echo $isEntry ? 'background-color: #fff9e6;' : ''; ?>">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Kode Barang</label>
                            <input type="text" class="form-control text-primary" style="font-weight:bold;" name="ITEM_CODE" value="<?php echo htmlspecialchars($data['ITEM_CODE']); ?>" <?php echo ($mode!='new') ? 'readonly' : ''; ?> required maxlength="20">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Item No.</label>
                            <input type="text" class="form-control" name="ITEM_NO" value="<?php echo isset($data['ITEM_NO']) ? htmlspecialchars($data['ITEM_NO']) : ''; ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> maxlength="30">
                        </div>
                        <div class="col-md-5 form-group">
                            <label>Nama Barang</label>
                            <input type="text" class="form-control" name="ITEM_NAME" value="<?php echo htmlspecialchars($data['ITEM_NAME']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?> required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                             <label>Stok On Hand</label>
                             <input type="text" class="form-control text-right" style="background-color: #eee; font-weight:bold;" value="<?php echo number_format($data['ITEM_ONHAND'], 2); ?>" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Tipe Barang</label>
                            <select class="form-control" name="ITTY_CODE" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <?php echo $optItty; ?>
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Satuan</label>
                            <select class="form-control" name="ITEM_UNIT" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <option value="Pcs" <?php echo ($data['ITEM_UNIT']=='Pcs')?'selected':''; ?>>Pcs</option>
                                <option value="Kg" <?php echo ($data['ITEM_UNIT']=='Kg')?'selected':''; ?>>Kg</option>
                                <option value="Ltr" <?php echo ($data['ITEM_UNIT']=='Ltr')?'selected':''; ?>>Ltr</option>
                                <option value="Set" <?php echo ($data['ITEM_UNIT']=='Set')?'selected':''; ?>>Set</option>
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Cost</label>
                            <input type="number" step="0.001" class="form-control text-right" name="ITEM_COST" value="<?php echo htmlspecialchars($data['ITEM_COST']); ?>" <?php echo !$isEntry ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Mata Uang</label>
                            <select class="form-control" name="ITEM_CUR" <?php echo !$isEntry ? 'disabled' : ''; ?>>
                                <option value="IDR" <?php echo ($data['ITEM_CUR']=='IDR')?'selected':''; ?>>IDR</option>
                                <option value="USD" <?php echo ($data['ITEM_CUR']=='USD')?'selected':''; ?>>USD</option>
                            </select>
                        </div>
                    </div>

                    <div class="row" style="margin-top: 15px;">
                        <div class="col-md-12 border-top" style="padding-top: 15px; border-top: 1px solid #ddd;">
                            <label class="checkbox-inline" style="font-weight: bold; margin-right: 15px;">
                                <input type="checkbox" name="ITEM_INV" value="1" <?php echo ($data['ITEM_INV']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>> INV (Inventory)
                            </label>
                            <label class="checkbox-inline" style="font-weight: bold; margin-right: 15px;">
                                <input type="checkbox" name="ITEM_FORSALE" value="1" <?php echo ($data['ITEM_FORSALE']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>> SALE (For Sale)
                            </label>
                            <label class="checkbox-inline text-danger" style="font-weight: bold;">
                                <input type="checkbox" name="ITEM_INACTIVE" value="1" <?php echo ($data['ITEM_INACTIVE']==1)?'checked':''; ?> <?php echo !$isEntry ? 'disabled' : ''; ?>> NOT ACTIVE
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="box box-solid box-default">
                <div class="box-header with-border" style="background-color: #222d32; color: #fff; text-align:center;">
                    <h3 class="box-title" style="font-size: 14px; font-weight:bold;">STATUS AKSI</h3>
                </div>
                <div class="box-body" style="text-align: center;">
                    <div class="alert <?php echo $isEntry ? ($mode == 'edit' ? 'alert-warning' : 'alert-success') : 'alert-info'; ?>" style="margin-bottom: 0; padding: 10px;">
                        <i class="fa <?php echo $isEntry ? ($mode == 'edit' ? 'fa-pencil' : 'fa-plus-circle') : 'fa-eye'; ?> fa-2x"></i><br>
                        <b><?php echo $isEntry ? ($mode == 'edit' ? 'Edit Barang' : 'Barang Baru') : 'Mode Lihat Data'; ?></b>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<!-- SCRIPT PENCARIAN BARANG MENGGUNAKAN HTML5 DATALIST & JQUERY AJAX -->
<script>
$(document).ready(function() {
    $('#search_item').on('input', function() {
        var term = $(this).val();
        if (term.length >= 1) {
            $.ajax({
                url: 'api_cari_barang.php',
                dataType: 'json',
                data: { term: term },
                success: function(data) {
                    var options = '';
                    if (data && data.length > 0) {
                        $.each(data, function(i, item) {
                            options += '<option value="' + item.label + '" data-id="' + item.id + '">';
                        });
                    }
                    $('#list_barang').html(options);
                }
            });
        }
    });

    $('#search_item').on('change', function() {
        var val = $(this).val();
        var selectedOption = $('#list_barang option').filter(function() {
            return this.value == val;
        });
        
        if (selectedOption.length > 0) {
            var parts = val.split(' - ');
            var itemCode = parts[0].trim();
            if (itemCode) {
                window.location.href = '<?php echo $urlPrefix; ?>tab=barang&id=' + encodeURIComponent(itemCode);
            }
        }
    });
});
</script>