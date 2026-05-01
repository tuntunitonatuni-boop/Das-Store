import 'package:flutter/material.dart';
import '../models/cart_item_model.dart';
import '../models/product_model.dart';

class CartProvider extends ChangeNotifier {
  final List<CartItemModel> _items = [];

  List<CartItemModel> get items => List.unmodifiable(_items);
  int get itemCount => _items.length;
  int get totalQty => _items.fold(0, (sum, item) => sum + item.qty);

  double get subtotal => _items.fold(0, (sum, item) => sum + item.subtotal);
  double get total => subtotal; // Add delivery charge etc. later

  /// Add product to cart (or increment if already exists)
  void addToCart(ProductModel product, {int qty = 1}) {
    final existingIndex = _items.indexWhere((i) => i.product.id == product.id);
    if (existingIndex >= 0) {
      _items[existingIndex].qty += qty;
    } else {
      _items.add(CartItemModel(product: product, qty: qty));
    }
    notifyListeners();
  }

  /// Remove item from cart entirely
  void removeFromCart(int productId) {
    _items.removeWhere((i) => i.product.id == productId);
    notifyListeners();
  }

  /// Update quantity of an item
  void updateQty(int productId, int newQty) {
    final index = _items.indexWhere((i) => i.product.id == productId);
    if (index >= 0) {
      if (newQty <= 0) {
        _items.removeAt(index);
      } else {
        _items[index].qty = newQty;
      }
      notifyListeners();
    }
  }

  /// Increment quantity
  void increment(int productId) {
    final index = _items.indexWhere((i) => i.product.id == productId);
    if (index >= 0) {
      _items[index].qty++;
      notifyListeners();
    }
  }

  /// Decrement quantity (removes if goes to 0)
  void decrement(int productId) {
    final index = _items.indexWhere((i) => i.product.id == productId);
    if (index >= 0) {
      if (_items[index].qty <= 1) {
        _items.removeAt(index);
      } else {
        _items[index].qty--;
      }
      notifyListeners();
    }
  }

  /// Check if product is in cart
  bool isInCart(int productId) => _items.any((i) => i.product.id == productId);

  /// Get quantity of a product in cart
  int getQty(int productId) {
    final item = _items.where((i) => i.product.id == productId);
    return item.isNotEmpty ? item.first.qty : 0;
  }

  /// Clear entire cart
  void clearCart() {
    _items.clear();
    notifyListeners();
  }

  /// Convert cart to JSON for checkout API
  List<Map<String, dynamic>> toOrderJson() => _items.map((i) => i.toOrderJson()).toList();
}
