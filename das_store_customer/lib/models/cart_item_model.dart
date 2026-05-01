import 'product_model.dart';

class CartItemModel {
  final ProductModel product;
  int qty;

  CartItemModel({required this.product, this.qty = 1});

  double get subtotal => product.salePrice * qty;

  Map<String, dynamic> toOrderJson() => {
    'id': product.id,
    'name': product.name,
    'price': product.salePrice,
    'qty': qty,
  };
}
