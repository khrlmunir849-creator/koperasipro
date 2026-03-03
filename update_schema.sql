-- =====================================================
-- UPDATE SCHEMA FOR MSSQL
-- Converts MySQL syntax to SQL Server (MSSQL) syntax
-- =====================================================

-- Check if we're using the right database
USE kopi_opsi_db;
GO

-- =====================================================
-- Step 1: Add new column as VARCHAR first (MSSQL doesn't support MODIFY)
-- =====================================================
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('simpanan') AND name = 'jenis_simpanan_temp')
BEGIN
    ALTER TABLE simpanan ADD jenis_simpanan_temp VARCHAR(50) NULL;
END
GO

-- =====================================================
-- Step 2: Copy data from old ENUM to new VARCHAR column
-- =====================================================
UPDATE simpanan SET jenis_simpanan_temp = 
    CASE jenis_simpananan
        WHEN 'pokok' THEN 'pokok'
        WHEN 'wajib' THEN 'wajib'
        WHEN 'sukarela' THEN 'sukarela'
        ELSE 'wajib'
    END
WHERE jenis_simpanan_temp IS NULL;
GO

-- =====================================================
-- Step 3: Drop old ENUM column and rename new column
-- =====================================================
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('simpanan') AND name = 'jenis_simpanan')
BEGIN
    -- First, drop the CHECK constraint if exists
    DECLARE @constraintName NVARCHAR(128);
    SELECT @constraintName = NAME FROM sys.default_constraints 
    WHERE parent_object_id = OBJECT_ID('simpanan') 
    AND col_name(parent_object_id, parent_column_id) = 'jenis_simpanan';
    
    IF @constraintName IS NOT NULL
    BEGIN
        EXEC('ALTER TABLE simpanan DROP CONSTRAINT ' + @constraintName);
    END
    
    ALTER TABLE simpanan DROP COLUMN jenis_simpanan;
END
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('simpanan') AND name = 'jenis_simpanan_temp')
BEGIN
    EXEC sp_rename 'simpanan.jenis_simpanan_temp', 'jenis_simpanan', 'COLUMN';
END
GO

-- =====================================================
-- Step 4: Add CHECK constraint for valid values
-- =====================================================
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_simpanan_jenis_simpanan')
BEGIN
    ALTER TABLE simpanan 
    ADD CONSTRAINT CK_simpanan_jenis_simpanan
    CHECK (jenis_simpanan IN ('pokok', 'wajib', 'wajib_pinjam', 'wajib_khusus', 'sukarela'));
END
GO

PRINT 'Schema update completed successfully!';
GO
