import 'dart:convert';
import 'package:http/http.dart' as http;
import '../utils/constants.dart';

class ApiService {
  static String? _token;

  static void setToken(String? token) => _token = token;
  static String? get token => _token;

  static Map<String, String> get _headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    if (_token != null) 'Authorization': 'Bearer $_token',
  };

  static Future<Map<String, dynamic>> get(String endpoint, {Map<String, String>? params}) async {
    try {
      var uri = Uri.parse('${AppConstants.baseUrl}$endpoint');
      if (params != null) uri = uri.replace(queryParameters: params);

      final response = await http.get(uri, headers: _headers).timeout(const Duration(seconds: 15));
      return json.decode(response.body);
    } catch (e) {
      return {'success': false, 'message': 'Network error: ${e.toString()}'};
    }
  }

  static Future<Map<String, dynamic>> post(String endpoint, Map<String, dynamic> body) async {
    try {
      final uri = Uri.parse('${AppConstants.baseUrl}$endpoint');
      final response = await http.post(uri, headers: _headers, body: json.encode(body)).timeout(const Duration(seconds: 15));
      return json.decode(response.body);
    } catch (e) {
      return {'success': false, 'message': 'Network error: ${e.toString()}'};
    }
  }
}
