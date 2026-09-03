-- Recipe-authorized Production -> Warehouse requisition flow.
-- Active master recipes are maintained only by GM/Admin. Ordinary requests
-- generated from those recipes therefore do not need another GM decision.

SET @has_authorization_basis := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'material_requisitions'
      AND COLUMN_NAME = 'authorization_basis'
);

SET @add_authorization_basis_sql := IF(
    @has_authorization_basis = 0,
    'ALTER TABLE material_requisitions ADD COLUMN authorization_basis VARCHAR(40) NULL AFTER approved_at',
    'SELECT 1'
);

PREPARE add_authorization_basis_stmt FROM @add_authorization_basis_sql;
EXECUTE add_authorization_basis_stmt;
DEALLOCATE PREPARE add_authorization_basis_stmt;
