-- =====================================================
-- UPDATE SIMPANAN TYPES FOR MSSQL
-- Adds new simpanan types: wajib_pinjam, wajib_khusus
-- =====================================================

USE kopi_opsi_db;
GO

-- =====================================================
-- Insert new simpanan types for existing anggota
-- =====================================================

-- Insert wajib_pinjam if not exists
INSERT INTO simpanan (anggota_id, jenis_simpananan, saldo, is_active, dibuka_tanggal)
SELECT a.id, 'wajib_pinjam', 0, 1, CAST(GETDATE() AS DATE)
FROM anggota a
WHERE NOT EXISTS (
    SELECT 1 FROM simpanan s 
    WHERE s.anggota_id = a.id AND s.jenis_simpananan = 'wajib_pinjam'
);
GO

-- Insert wajib_khusus if not exists
INSERT INTO simpanan (anggota_id, jenis_simpananan, saldo, is_active, dibuka_tanggal)
SELECT a.id, 'wajib_khusus', 0, 1, CAST(GETDATE() AS DATE)
FROM anggota a
WHERE NOT EXISTS (
    SELECT 1 FROM simpanan s 
    WHERE s.anggota_id = a.id AND s.jenis_simpananan = 'wajib_khusus'
);
GO

PRINT 'Simpanan types update completed successfully!';
GO
