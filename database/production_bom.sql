-- Dinh muc nguyen lieu minh hoa cho do an.
-- Khong phai cong thuc san xuat Coca-Cola thuc te.
-- Chay sau khi bang products da co du lieu.

CREATE TABLE IF NOT EXISTS production_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    material_id INT NOT NULL,
    quantity_per_unit DECIMAL(18,6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_product_material (product_id, material_id),

    CONSTRAINT fk_bom_product
        FOREIGN KEY (product_id) REFERENCES products(id),

    CONSTRAINT fk_bom_material
        FOREIGN KEY (material_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Du lieu demo cho Coca-Cola Original 500 ml (24 chai/thung).
-- ID 5 la thanh pham; ID 1-4 la nguyen lieu.
-- Can kiem tra ID trong products truoc khi chay.

INSERT INTO production_bom
    (product_id, material_id, quantity_per_unit)
VALUES
    (5, 1, 0.50),
    (5, 2, 0.05),
    (5, 3, 0.01),
    (5, 4, 24)
ON DUPLICATE KEY UPDATE
    quantity_per_unit = VALUES(quantity_per_unit);