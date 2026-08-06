<?php
$documentTypes = array(
    '' => '--Pilih Dokumen--',
    'BC2.3' => 'BC2.3',
    'BC4.0' => 'BC4.0',
    'PIB' => 'PIB',
    'PEB' => 'PEB',
    'BC2.5' => 'BC2.5',
    'BC2.6.1' => 'BC2.6.1',
    'BC2.6.2' => 'BC2.6.2',
    'BC2.7' => 'BC2.7',
    'BC4.1' => 'BC4.1',
    'BC3.0' => 'BC3.0',
    'Internal' => 'Internal'
);

$reportList = array(
    1 => 'Laporan Pemasukan Barang Perdokumen',
    2 => 'Laporan Pengeluaran Barang Perdokumen',
    3 => 'Laporan Mutasi Bahan Baku & Penolong',
    4 => 'Laporan Mutasi Barang Jadi',
    5 => 'Laporan Mutasi Mesin & Peralatan Kantor',
    6 => 'Laporan Mutasi Barang Sisa/Scrap',
    7 => 'Laporan Barang WIP'
);

$summaryDemo = array(
    'pemasukan_bulan' => 128,
    'pengeluaran_bulan' => 96,
    'stok_bahan_baku' => 12450,
    'stok_barang_jadi' => 3820,
    'dokumen_pending' => 7,
    'aset_it' => 214
);

$recentDocumentsDemo = array(
    array('no_dokumen' => 'BC23-2026-0712', 'tanggal' => '2026-07-29', 'jenis' => 'BC2.3', 'arah' => 'Pemasukan', 'supplier' => 'PT Sumber Komponen', 'jumlah' => 1250, 'status' => 'Selesai'),
    array('no_dokumen' => 'BC25-2026-0321', 'tanggal' => '2026-07-29', 'jenis' => 'BC2.5', 'arah' => 'Pengeluaran', 'supplier' => 'PT Mitra Logistik', 'jumlah' => 420, 'status' => 'Selesai'),
    array('no_dokumen' => 'BC40-2026-0198', 'tanggal' => '2026-07-28', 'jenis' => 'BC4.0', 'arah' => 'Pemasukan', 'supplier' => 'CV Teknik Mandiri', 'jumlah' => 73, 'status' => 'Proses'),
    array('no_dokumen' => 'INT-2026-0884', 'tanggal' => '2026-07-28', 'jenis' => 'Internal', 'arah' => 'Mutasi', 'supplier' => 'Gudang Bahan Baku', 'jumlah' => 315, 'status' => 'Selesai'),
    array('no_dokumen' => 'PEB-2026-0049', 'tanggal' => '2026-07-27', 'jenis' => 'PEB', 'arah' => 'Pengeluaran', 'supplier' => 'Global Parts Pte. Ltd.', 'jumlah' => 880, 'status' => 'Pending')
);

$monthlyFlowDemo = array(
    array('bulan' => 'Feb', 'masuk' => 88, 'keluar' => 72),
    array('bulan' => 'Mar', 'masuk' => 102, 'keluar' => 84),
    array('bulan' => 'Apr', 'masuk' => 94, 'keluar' => 89),
    array('bulan' => 'Mei', 'masuk' => 118, 'keluar' => 91),
    array('bulan' => 'Jun', 'masuk' => 110, 'keluar' => 101),
    array('bulan' => 'Jul', 'masuk' => 128, 'keluar' => 96)
);
