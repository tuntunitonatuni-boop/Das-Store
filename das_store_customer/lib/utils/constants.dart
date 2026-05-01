import 'package:flutter/material.dart';

class AppConstants {
  // API URL Config
  // NOTE: When running on Android emulator, use 10.0.2.2 instead of 127.0.0.1
  // If running on a real device, replace this with your computer's IP address (e.g., 192.168.0.x)
  static const String baseUrl = 'http://192.168.0.8/Das%20Store/api/';
  
  // App Colors (Matched with main.css)
  static const Color primaryColor = Color(0xFF16a34a); // #16a34a
  static const Color primaryDark = Color(0xFF15803d); // #15803d
  static const Color secondaryColor = Color(0xFF475569); // #475569
  static const Color backgroundColor = Color(0xFFf8fafc); // #f8fafc
  static const Color cardColor = Colors.white;
  static const Color textColor = Color(0xFF0f172a); // #0f172a
  static const Color textLightColor = Color(0xFF64748b); // #64748b
  static const Color dangerColor = Color(0xFFdc2626); // #dc2626
  static const Color warningColor = Color(0xFF854d0e); // #854d0e
}
