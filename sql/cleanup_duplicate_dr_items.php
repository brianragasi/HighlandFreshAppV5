<?php
/**
 * Cleanup script for duplicate delivery_receipt_items
 * This will merge duplicate rows by keeping the one with batch info
 */

require_once dirname(__DIR__) . '/api/bootstrap.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== Duplicate Delivery Receipt Items Cleanup ===\n\n";
    
    // Step 1: Find all DRs with duplicate items
    $findDuplicates = $db->query("
        SELECT 
            dri.delivery_receipt_id,
            dr.dr_number,
            dri.product_id,
            p.product_name,
            COUNT(*) as duplicate_count,
            GROUP_CONCAT(dri.id) as item_ids,
            GROUP_CONCAT(COALESCE(dri.batch_id, 'NULL')) as batch_ids,
            GROUP_CONCAT(dri.quantity_ordered) as quantities
        FROM delivery_receipt_items dri
        JOIN delivery_receipts dr ON dri.delivery_receipt_id = dr.id
        LEFT JOIN products p ON dri.product_id = p.id
        GROUP BY dri.delivery_receipt_id, dri.product_id
        HAVING COUNT(*) > 1
    ");
    
    $duplicates = $findDuplicates->fetchAll();
    
    if (empty($duplicates)) {
        echo "✓ No duplicate items found!\n";
        exit(0);
    }
    
    echo "Found " . count($duplicates) . " product(s) with duplicates:\n\n";
    
    foreach ($duplicates as $dup) {
        echo "DR: {$dup['dr_number']} (ID: {$dup['delivery_receipt_id']})\n";
        echo "Product: {$dup['product_name']} (ID: {$dup['product_id']})\n";
        echo "Duplicates: {$dup['duplicate_count']} rows\n";
        echo "Item IDs: {$dup['item_ids']}\n";
        echo "Batch IDs: {$dup['batch_ids']}\n";
        echo "Quantities: {$dup['quantities']}\n";
        echo "---\n";
    }
    
    // Step 2: Merge duplicates
    echo "\n=== Merging Duplicates ===\n\n";
    
    $mergedCount = 0;
    
    foreach ($duplicates as $dup) {
        $itemIds = explode(',', $dup['item_ids']);
        $batchIds = explode(',', $dup['batch_ids']);
        $quantities = explode(',', $dup['quantities']);
        
        // Find the item with batch info (not NULL)
        $keepId = null;
        $keepBatchId = null;
        $keepQty = null;
        $deleteIds = [];
        
        for ($i = 0; $i < count($itemIds); $i++) {
            if ($batchIds[$i] !== 'NULL' && $batchIds[$i] !== '') {
                $keepId = $itemIds[$i];
                $keepBatchId = $batchIds[$i];
                $keepQty = $quantities[$i];
            } else {
                $deleteIds[] = $itemIds[$i];
            }
        }
        
        // If no item has batch, keep the first one
        if ($keepId === null) {
            $keepId = $itemIds[0];
            $keepQty = $quantities[0];
            $deleteIds = array_slice($itemIds, 1);
        }
        
        echo "DR {$dup['dr_number']}: Keep item #$keepId, delete items [" . implode(', ', $deleteIds) . "]\n";
        
        // Delete duplicates
        if (!empty($deleteIds)) {
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $deleteStmt = $db->prepare("DELETE FROM delivery_receipt_items WHERE id IN ($placeholders)");
            $deleteStmt->execute($deleteIds);
            $mergedCount += $deleteStmt->rowCount();
        }
    }
    
    echo "\n✓ Cleanup complete! Deleted {$mergedCount} duplicate rows.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
