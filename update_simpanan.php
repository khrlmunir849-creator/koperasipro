<?php
/**
 * Update Jenis Simpanan - Run via Browser
 * URL: http://localhost/koperasi/update_simpanan.php
 * 
 * Compatible with SQL Server (MSSQL)
 */

require_once __DIR__ . '/config/database.php';

echo "<h2>Update Jenis Simpanan (MSSQL)</h2>";
echo "<pre>";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Step 1: Add temp VARCHAR column (MSSQL doesn't support MODIFY COLUMN)
    echo "Step 1: Adding temp VARCHAR column...\n";
    $pdo->exec("
        IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('simpanan') AND name = 'jenis_simpanan_temp')
        BEGIN
            ALTER TABLE simpanan ADD jenis_simpanan_temp VARCHAR(50) NULL
        END
    ");
    echo "OK\n";
    
    // Step 2: Copy data to temp column
    echo "Step 2: Copying data to temp column...\n";
    $pdo->exec("
        UPDATE simpanan SET jenis_simpanan_temp = 
            CASE jenis_simpananan
                WHEN 'pokok' THEN 'pokok'
                WHEN 'wajib' THEN 'wajib'
                WHEN 'sukarela' THEN 'sukarela'
                ELSE 'wajib'
            END
        WHERE jenis_simpanan_temp IS NULL
    ");
    echo "OK\n";
    
    // Step 3: Drop old column and rename temp
    echo "Step 3: Replacing old column with new VARCHAR column...\n";
    $pdo->exec("
        IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('simpanan') AND name = 'jenis_simpananan')
        BEGIN
            ALTER TABLE simpanan DROP COLUMN jenis_simpananan
        END
    ");
    $pdo->exec("
        IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('simpanan') AND name = 'jenis_simpanan_temp')
        BEGIN
            EXEC sp_rename 'simpanan.jenis_simpanan_temp', 'jenis_simpananan', 'COLUMN'
        END
    ");
    echo "OK\n";
    
    // Step 4: Add CHECK constraint for valid values
    echo "Step 4: Adding CHECK constraint...\n";
    $pdo->exec("
        IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_simpanan_jenis_simpananan')
        BEGIN
            ALTER TABLE simpanan 
            ADD CONSTRAINT CK_simpanan_jenis_simpananan 
            CHECK (jenis_simpananan IN ('pokok', 'wajib', 'wajib_pinjam', 'wajib_khusus', 'sukarela'))
        END
    ");
    echo "OK\n";
    
    // Step 5: Insert wajib_pinjam (use GETDATE() instead of CURDATE())
    echo "Step 5: Inserting wajib_pinjam...\n";
    $stmt = $pdo->prepare("
        INSERT INTO simpanan (anggota_id, jenis_simpananan, saldo, is_active, dibuka_tanggal)
        SELECT a.id, 'wajib_pinjam', 0, 1, CAST(GETDATE() AS DATE)
        FROM anggota a
        WHERE NOT EXISTS (
            SELECT 1 FROM simpanan s 
            WHERE s.anggota_id = a.id AND s.jenis_simpananan = 'wajib_pinjam'
        )
    ");
    $stmt->execute();
    $count1 = $stmt->rowCount();
    echo "Inserted $count1 rows\n";
    
    // Step 6: Insert wajib_khusus
    echo "Step 6: Inserting wajib_khusus...\n";
    $stmt = $pdo->prepare("
        INSERT INTO simpanan (anggota_id, jenis_simpananan, saldo, is_active, dibuka_tanggal)
        SELECT a.id, 'wajib_khusus', 0, 1, CAST(GETDATE() AS DATE)
        FROM anggota a
        WHERE NOT EXISTS (
            SELECT 1 FROM simpanan s 
            WHERE s.anggota_id = a.id AND s.jenis_simpananan = 'wajib_khusus'
        )
    ");
    $stmt->execute();
    $count2 = $stmt->rowCount();
    echo "Inserted $count2 rows\n";
    
    echo "\n=== SUCCESS ===\n";
    echo "Update completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n=== ERROR ===\n";
    echo $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='index.php'>Back to Home</a></p>";
