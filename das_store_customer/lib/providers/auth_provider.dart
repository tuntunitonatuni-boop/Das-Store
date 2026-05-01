import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';

class AuthProvider extends ChangeNotifier {
  UserModel? _user;
  String? _token;
  bool _isLoading = false;
  String? _error;

  UserModel? get user => _user;
  String? get token => _token;
  bool get isLoggedIn => _user != null && _token != null;
  bool get isLoading => _isLoading;
  String? get error => _error;

  /// Try to load saved session from SharedPreferences
  Future<bool> tryAutoLogin() async {
    final prefs = await SharedPreferences.getInstance();
    final savedToken = prefs.getString('auth_token');
    final savedUser = prefs.getString('auth_user');

    if (savedToken != null && savedUser != null) {
      _token = savedToken;
      _user = UserModel.fromJson(json.decode(savedUser));
      ApiService.setToken(_token);
      notifyListeners();
      return true;
    }
    return false;
  }

  /// Login with username/phone + password
  Future<bool> login(String username, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    final result = await ApiService.post('auth.php', {
      'action': 'login',
      'username': username,
      'password': password,
    });

    _isLoading = false;

    if (result['success'] == true) {
      _token = result['token'];
      _user = UserModel.fromJson(result['user']);
      ApiService.setToken(_token);

      // Save to local storage
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', _token!);
      await prefs.setString('auth_user', json.encode(_user!.toJson()));

      notifyListeners();
      return true;
    } else {
      _error = result['message'] ?? 'Login failed';
      notifyListeners();
      return false;
    }
  }

  /// Register a new customer account
  Future<bool> register(String name, String username, String phone, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    final result = await ApiService.post('auth.php', {
      'action': 'register',
      'name': name,
      'username': username,
      'phone': phone,
      'password': password,
    });

    _isLoading = false;

    if (result['success'] == true) {
      notifyListeners();
      return true;
    } else {
      _error = result['message'] ?? 'Registration failed';
      notifyListeners();
      return false;
    }
  }

  /// Logout and clear saved session
  Future<void> logout() async {
    _user = null;
    _token = null;
    ApiService.setToken(null);

    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await prefs.remove('auth_user');

    notifyListeners();
  }
}
