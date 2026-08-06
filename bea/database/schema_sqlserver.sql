/*
  Tabel contoh dashboard IT Inventory untuk SQL Server 2008.
  Jalankan pada database msdata hanya bila tabel yang sesuai belum tersedia.
  Sesuaikan nama tabel/kolom pada inc/repository.php bila memakai tabel produksi lain.
*/

IF OBJECT_ID('dbo.dokumen_bc', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.dokumen_bc (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        no_dokumen NVARCHAR(60) NOT NULL,
        tanggal DATE NOT NULL,
        jenis NVARCHAR(20) NOT NULL,
        arah NVARCHAR(20) NOT NULL,
        partner NVARCHAR(150) NULL,
        jumlah DECIMAL(18,2) NOT NULL CONSTRAINT DF_dokumen_bc_jumlah DEFAULT (0),
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_dokumen_bc_status DEFAULT ('Pending'),
        created_at DATETIME NOT NULL CONSTRAINT DF_dokumen_bc_created DEFAULT (GETDATE())
    );

    CREATE INDEX IX_dokumen_bc_tanggal ON dbo.dokumen_bc(tanggal);
    CREATE INDEX IX_dokumen_bc_jenis ON dbo.dokumen_bc(jenis);
    CREATE INDEX IX_dokumen_bc_arah ON dbo.dokumen_bc(arah);
END;
GO

IF OBJECT_ID('dbo.aset_it', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.aset_it (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        kode_aset NVARCHAR(50) NOT NULL,
        nama_aset NVARCHAR(150) NOT NULL,
        kategori NVARCHAR(80) NULL,
        lokasi NVARCHAR(100) NULL,
        kondisi NVARCHAR(30) NOT NULL CONSTRAINT DF_aset_it_kondisi DEFAULT ('Aktif'),
        tanggal_perolehan DATE NULL,
        created_at DATETIME NOT NULL CONSTRAINT DF_aset_it_created DEFAULT (GETDATE())
    );

    CREATE UNIQUE INDEX UX_aset_it_kode ON dbo.aset_it(kode_aset);
END;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.dokumen_bc)
BEGIN
    INSERT INTO dbo.dokumen_bc
        (no_dokumen, tanggal, jenis, arah, partner, jumlah, status)
    VALUES
        ('BC23-2026-0712', '2026-07-29', 'BC2.3', 'Pemasukan', 'PT Sumber Komponen', 1250, 'Selesai'),
        ('BC25-2026-0321', '2026-07-29', 'BC2.5', 'Pengeluaran', 'PT Mitra Logistik', 420, 'Selesai'),
        ('BC40-2026-0198', '2026-07-28', 'BC4.0', 'Pemasukan', 'CV Teknik Mandiri', 73, 'Proses'),
        ('INT-2026-0884', '2026-07-28', 'Internal', 'Mutasi', 'Gudang Bahan Baku', 315, 'Selesai'),
        ('PEB-2026-0049', '2026-07-27', 'PEB', 'Pengeluaran', 'Global Parts Pte. Ltd.', 880, 'Pending');
END;
GO
