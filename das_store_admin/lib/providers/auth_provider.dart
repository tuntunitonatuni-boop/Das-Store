import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/admin_user.dart';
import '../services/api_service.dart';

class AuthProvider extends ChangeNotifier {
  AdminUser? _user;
  String? _token;
  bool _isLoading = false;
  String? _error;

  AdminUser? get user => _user;
  String? get token => _token;
  bool get isLoggedIn => _user != null && _token != null;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<bool> tryAutoLogin() async {
    final prefs = await SharedPreferences.getInstance();
    final savedToken = prefs.getString('admin_token');
    final savedUser = prefs.getString('admin_user');
    if (savedToken != null && savedUser != null) {
      _token = savedToken;
      _user = AdminUser.fromJson(json.decode(savedUser));
      ApiService.setToken(_token);
      notifyListeners();
      return true;
    }
    return false;
  }

  Future<bool> login(String username, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    final result = await ApiService.post('admin.php', {
      'action': 'login',
      'username': username,
      'password': password,
    });

    _isLoading = false;

    if (result['success'] == true) {
      _token = result['token'];
      _user = AdminUser.fromJson(result['user']);
      ApiService.setToken(_token);

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('admin_token', _token!);
      await prefs.setString('admin_user', json.encode(_user!.toJson()));

      notifyListeners();
      return true;
    } else {
      _error = result['message'] ?? 'Login failed';
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    _user = null;
    _token = null;
    ApiService.setToken(null);
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('admin_token');
    await prefs.remove('admin_user');
    notifyListeners();
  }
}
