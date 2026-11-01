USE `quanlykhohang_db`;

INSERT INTO `inventory_audits`
    (`audit_code`, `warehouse_id`, `title`, `audit_date`, `created_by`, `verified_by`, `status`, `notes`)
SELECT samples.`audit_code`, w.`id`, samples.`title`, '2026-11-01',
       creator.`id`, verifier.`id`, 'WAITING_DIRECTOR', samples.`notes`
FROM (
    SELECT 'KK-2026-11-02' AS `audit_code`, 'KNL-01' AS `warehouse_code`,
           'Kiểm kê lô đường tinh luyện LOT-SIRO-2026-111' AS `title`,
           'Biên bản mẫu có chênh lệch giảm tồn.' AS `notes`
    UNION ALL
    SELECT 'KK-2026-11-03', 'KNL-03',
           'Kiểm kê CO₂ thực phẩm - kiểm tra đối chiếu số lượng',
           'Biên bản mẫu không có chênh lệch tồn.'
    UNION ALL
    SELECT 'KK-2026-11-04', 'KTP-01',
           'Kiểm kê thành phẩm Coca-Cola chai PET',
           'Biên bản mẫu có chênh lệch tăng tồn.'
) samples
INNER JOIN `warehouses` w ON w.`code` = samples.`warehouse_code`
INNER JOIN `users` creator ON creator.`username` = 'thukho'
INNER JOIN `users` verifier ON verifier.`username` = 'quanlykho'
WHERE NOT EXISTS (
    SELECT 1 FROM `inventory_audits` a WHERE a.`audit_code` = samples.`audit_code`
);

INSERT INTO `inventory_audit_items`
    (`audit_id`, `product_id`, `lot_id`, `location_id`, `system_quantity`, `actual_quantity`,
     `discrepancy_reason`, `recommended_action`)
SELECT a.`id`, i.`product_id`, i.`lot_id`, i.`location_id`, i.`quantity`,
       i.`quantity` + samples.`quantity_delta`,
       samples.`discrepancy_reason`, samples.`recommended_action`
FROM (
    SELECT 'KK-2026-11-02' AS `audit_code`, 1 AS `warehouse_id`, 2 AS `location_id`,
           1 AS `product_id`, 2 AS `lot_id`, -5 AS `quantity_delta`,
           'Chênh lệch khi đối chiếu bao nguyên liệu tại vị trí lưu trữ' AS `discrepancy_reason`,
           'Điều chỉnh giảm tồn 5 Kg sau khi được phê duyệt.' AS `recommended_action`
    UNION ALL
    SELECT 'KK-2026-11-03', 3, 8, 3, 4, 0,
           'Đã kiểm đếm và đối chiếu, số lượng khớp với sổ sách',
           'Không cần điều chỉnh tồn kho.'
    UNION ALL
    SELECT 'KK-2026-11-04', 6, 4, 5, 6, 2,
           'Phát hiện thêm 2 thùng tại khu vực chờ xuất, chưa được ghi nhận vào sổ sách',
           'Điều chỉnh tăng tồn 2 thùng sau khi được phê duyệt.'
) samples
INNER JOIN `inventory_audits` a ON a.`audit_code` = samples.`audit_code`
INNER JOIN `inventory` i
    ON i.`warehouse_id` = samples.`warehouse_id`
   AND i.`location_id` = samples.`location_id`
   AND i.`product_id` = samples.`product_id`
   AND i.`lot_id` = samples.`lot_id`
WHERE i.`quantity` + samples.`quantity_delta` >= 0
  AND NOT EXISTS (
      SELECT 1 FROM `inventory_audit_items` ai WHERE ai.`audit_id` = a.`id`
  );
