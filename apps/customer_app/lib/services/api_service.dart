import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService extends ChangeNotifier {
  // Use your real server URL here. For local testing, use 10.0.2.2 for Android Emulator.
  final String _baseUrl = 'http://10.0.2.2/Das%20Store/api'; 
  
  Map? _user;
  String? _token;
  bool _isLoading = false;

  Map? get user => _user;
  String? get token => _token;
  bool get isLoading => _isLoading;

  void setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }

  Future<bool> checkSavedUser() async {
    final prefs = await SharedPreferences.getInstance();
    final userStr = prefs.getString('user');
    final tokenStr = prefs.getString('token');
    
    if (userStr != null && tokenStr != null) {
      _user = json.decode(userStr);
      _token = tokenStr;
      return true;
    }
    return false;
  }

  Future<Map> login(String username, String password) async {
    setLoading(true);
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/auth.php?action=login'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'username': username, 'password': password}),
      );
      
      final data = json.decode(response.body);
      if (data['success']) {
        _user = data['user'];
        _token = data['token'];
        
        final prefs = await SharedPreferences.getInstance();
        prefs.setString('user', json.encode(_user));
        prefs.setString('token', _token!);
        notifyListeners();
      }
      setLoading(false);
      return data;
    } catch (e) {
      setLoading(false);
      return {'success': false, 'message': 'Network error. Check connection.'};
    }
  }
  Future<void> logout() async {
    _user = null;
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    prefs.remove('user');
    prefs.remove('token');
    notifyListeners();
  }

  Future<List> getProducts({int? catId, String? search}) async {
    String url = '$_baseUrl/store.php?action=products';
    if (catId != null) url += '&cat_id=$catId';
    if (search != null) url += '&q=$search';
    
    try {
      final response = await http.get(Uri.parse(url));
      final data = json.decode(response.body);
      return data['products'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<List> getCategories() async {
    try {
      final response = await http.get(Uri.parse('$_baseUrl/store.php?action=categories'));
      final data = json.decode(response.body);
      return data['categories'] ?? [];
    } catch (e) {
      return [];
    }
  }


  Map _cart = {};
  Map get cart => _cart;

  Future<Map> register(String name, String username, String phone, String password) async {
    setLoading(true);
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/auth.php?action=register'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'name': name,
          'username': username,
          'phone': phone,
          'password': password
        }),
      );
      setLoading(false);
      return json.decode(response.body);
    } catch (e) {
      setLoading(false);
      return {'success': false, 'message': 'Network error'};
    }
  }

  // --- Cart Management ---
  void addToCart(Map p) {
    String id = p['id'].toString();
    if (_cart.containsKey(id)) {
      _cart[id]['qty']++;
    } else {
      _cart[id] = {
        'id': p['id'],
        'name': p['name'],
        'price': double.parse(p['sale_price'].toString()),
        'qty': 1,
        'image': p['image_url']
      };
    }
    notifyListeners();
  }

  void updateCartQty(String id, int qty) {
    if (qty <= 0) { _cart.remove(id); }
    else { _cart[id]['qty'] = qty; }
    notifyListeners();
  }

  void clearCart() { _cart = {}; notifyListeners(); }

  double get cartTotal {
    double total = 0;
    _cart.forEach((k, v) => total += v['price'] * v['qty']);
    return total;
  }

  int get cartCount {
    int count = 0;
    _cart.forEach((k, v) => count += (v['qty'] as int));
    return count;
  }

  // --- Checkout ---
  Future<Map> placeOrder(String address) async {
    if (_token == null) return {'success': false, 'message': 'Please login first'};
    setLoading(true);
    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/store.php?action=checkout'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $_token'
        },
        body: json.encode({
          'items': _cart.values.toList(),
          'total': cartTotal,
          'address': address
        }),
      );
      final data = json.decode(response.body);
      if (data['success']) clearCart();
      setLoading(false);
      return data;
    } catch (e) {
      setLoading(false);
      return {'success': false, 'message': 'Checkout failed'};
    }
  }

  Future<List> getMyOrders() async {
    if (_token == null) return [];
    try {
      final response = await http.get(
        Uri.parse('$_baseUrl/store.php?action=my_orders'),
        headers: {'Authorization': 'Bearer $_token'},
      );
      final data = json.decode(response.body);
      return data['orders'] ?? [];
    } catch (e) {
      return [];
    }
  }
}
