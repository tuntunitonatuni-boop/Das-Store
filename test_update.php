<?php
require_once 'config.php';
require_once 'includes/db.php';

try {
    $id = 1;
    $name = 'Test';
    $name_bn = '';
    $sku = '';
    $barcode = '';
    $cat_id = 1;
    $company_id = 1;
    $dealer_id = null;
    $unit = 'pcs';
    $weight_size = '';
    $cost_price = 10;
    $sale_price = 20;
    $mrp = 25;
    $description = '';
    $description_bn = '';
    $is_active = 1;
    $is_published = 1;
    $track_expiry = 0;
    $allow_custom = 1;
    $min_order_amt = 0;
    $badge = 'none';
    $mfg_date = null;
    $expiry_date = null;

    $sql = "UPDATE products SET name=?,name_bn=?,sku=?,barcode=?,category_id=?,company_id=?,dealer_id=?,unit=?,weight_size=?,cost_price=?,sale_price=?,mrp=?,description=?,description_bn=?,is_active=?,is_published=?,track_expiry=?,allow_custom_qty=?,min_order_amount=?,badge=?,mfg_date=?,expiry_date=? WHERE id=?";
    $params = [$name,$name_bn,$sku,$barcode,$cat_id,$company_id,$dealer_id,$unit,$weight_size,$cost_price,$sale_price,$mrp,$description,$description_bn,$is_active,$is_published,$track_expiry,$allow_custom,$min_order_amt,$badge,$mfg_date,$expiry_date, $id];
    
    $pdo->prepare($sql)->execute($params);
    echo "SQL execution OK";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
