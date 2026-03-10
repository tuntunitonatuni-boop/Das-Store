-- ============================================================
--  Smart Grocery ERP — Sample Seed Data
-- ============================================================

SET NAMES utf8mb4;

-- Owner user (password: admin123)
INSERT IGNORE INTO `users` (`name`, `username`, `email`, `password`, `role`) VALUES
('Store Owner', 'admin', 'admin@dasstore.com', '$2y$10$y653wqBXy36avm2wIEiBO.eqAKp07hFi/CS96A509gfmLsstkBSPC', 'owner'),
('Staff User',  'staff', 'staff@dasstore.com', '$2y$10$y653wqBXy36avm2wIEiBO.eqAKp07hFi/CS96A509gfmLsstkBSPC', 'staff');

-- Categories
INSERT IGNORE INTO `categories` (`name`, `slug`, `icon`, `sort_order`) VALUES
('Rice & Grains',  'rice-grains',  '🌾', 1),
('Dairy & Eggs',   'dairy-eggs',   '🥛', 2),
('Vegetables',     'vegetables',   '🥦', 3),
('Fruits',         'fruits',       '🍎', 4),
('Beverages',      'beverages',    '🧃', 5),
('Snacks',         'snacks',       '🍪', 6),
('Cooking Oil',    'cooking-oil',  '🫙', 7),
('Spices',         'spices',       '🌶️', 8),
('Personal Care',  'personal-care','🧴', 9),
('Cleaning',       'cleaning',     '🧹', 10);

-- Companies
INSERT IGNORE INTO `companies` (`name`, `country`) VALUES
('Pran Group',     'Bangladesh'),
('ACI Limited',    'Bangladesh'),
('Square Group',   'Bangladesh'),
('Nestlé',         'Switzerland'),
('Unilever',       'UK'),
('Local Brand',    'Bangladesh');

-- Suppliers
INSERT IGNORE INTO `dealers` (`name`, `company`, `phone`, `whatsapp`, `address`) VALUES
('Karim Trading',  'Pran Distributor', '01711111111', '01711111111', 'Kawran Bazar, Dhaka'),
('Rahman Brothers','ACI Dealer',       '01722222222', '01722222222', 'Karwan Bazar, Dhaka'),
('City Suppliers', 'Multi-brand',      '01733333333', '01733333333', 'Dholaikhal, Dhaka');

-- Products
INSERT IGNORE INTO `products` (`name`, `sku`, `barcode`, `category_id`, `company_id`, `unit`, `cost_price`, `sale_price`, `mrp`) VALUES
('Miniket Rice 5kg',         'RIC-001', '8901234567890', 1, 6, 'bag',  280, 320, 350),
('Full Cream Milk 1L',       'DAI-001', '8901234567891', 2, 4, 'ltr',   95, 120, 130),
('Fresh Eggs (12pcs)',        'DAI-002', '8901234567892', 2, 6, 'doz',   95, 115, 125),
('Sunflower Oil 1L',         'OIL-001', '8901234567893', 7, 1, 'ltr',  130, 155, 165),
('Pran Mango Juice 200ml',   'BEV-001', '8901234567894', 5, 1, 'pcs',   22,  30,  35),
('Lays Classic 28g',         'SNK-001', '8901234567895', 6, 5, 'pcs',   25,  35,  40),
('Turmeric Powder 200g',     'SPI-001', '8901234567896', 8, 6, 'pcs',   40,  55,  60),
('Surf Excel 500g',          'CLN-001', '8901234567897', 10, 5,'pcs',   95, 120, 130),
('Dove Shampoo 170ml',       'PER-001', '8901234567898', 9, 5, 'pcs',  180, 220, 240),
('Tomato 1kg',               'VEG-001', '8901234567899', 3, 6, 'kg',    40,  60,  70),
('Banana (Dozen)',            'FRU-001', '8901234567900', 4, 6, 'doz',   40,  60,  70),
('Nescafé Classic 50g',      'BEV-002', '8901234567901', 5, 4, 'pcs',  180, 220, 250),
('ACI Pure Salt 1kg',        'SPI-002', '8901234567902', 8, 2, 'pcs',   18,  25,  30),
('Pran Chanachur 150g',      'SNK-002', '8901234567903', 6, 1, 'pcs',   30,  45,  50),
('Teer Maida 1kg',           'RIC-002', '8901234567904', 1, 6, 'pcs',   45,  60,  65);

-- Inventory (all products default stock)
INSERT IGNORE INTO `inventory` (`product_id`, `display_qty`, `warehouse_qty`, `reorder_level`)
SELECT id, 50, 150, 20 FROM products WHERE NOT EXISTS (
  SELECT 1 FROM inventory i WHERE i.product_id = products.id
);

-- Sample customers
INSERT IGNORE INTO `customers` (`name`, `phone`, `address`, `credit_limit`, `balance`) VALUES
('Rahim Mia',     '01800000001', 'Mirpur, Dhaka',  5000, 1200),
('Karim Sheikh',  '01800000002', 'Mohakhali, Dhaka', 3000, 500),
('Sita Rani',     '01800000003', 'Gulshan, Dhaka',   8000,  0);
