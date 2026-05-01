import 'package:flutter/material.dart';
import 'dart:async';
import '../utils/constants.dart';
import '../providers/auth_provider.dart';
import 'package:provider/provider.dart';
import 'login_screen.dart';
import 'dashboard_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scale;
  late Animation<double> _fade;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1000));
    _scale = Tween<double>(begin: 0.6, end: 1.0).animate(CurvedAnimation(parent: _ctrl, curve: Curves.elasticOut));
    _fade = Tween<double>(begin: 0.0, end: 1.0).animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeIn));
    _ctrl.forward();
    _navigate();
  }

  Future<void> _navigate() async {
    await Future.delayed(const Duration(seconds: 2));
    if (!mounted) return;
    final auth = context.read<AuthProvider>();
    final loggedIn = await auth.tryAutoLogin();
    if (!mounted) return;
    Navigator.pushReplacement(context, PageRouteBuilder(
      pageBuilder: (_, __, ___) => loggedIn ? const DashboardScreen() : const LoginScreen(),
      transitionsBuilder: (_, a, __, c) => FadeTransition(opacity: a, child: c),
      transitionDuration: const Duration(milliseconds: 400),
    ));
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft, end: Alignment.bottomRight,
            colors: [Color(0xFF0f172a), Color(0xFF1e293b), Color(0xFF334155)],
          ),
        ),
        child: Center(
          child: FadeTransition(
            opacity: _fade,
            child: ScaleTransition(
              scale: _scale,
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppConstants.accentColor.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: AppConstants.accentColor.withOpacity(0.3)),
                  ),
                  child: const Icon(Icons.admin_panel_settings_rounded, size: 72, color: AppConstants.accentColor),
                ),
                const SizedBox(height: 24),
                const Text('Das Store', style: TextStyle(fontSize: 34, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: 1.5)),
                const SizedBox(height: 6),
                Text('Admin Panel', style: TextStyle(fontSize: 16, color: Colors.white.withOpacity(0.6), letterSpacing: 2, fontWeight: FontWeight.w300)),
                const SizedBox(height: 48),
                SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 2.5, valueColor: AlwaysStoppedAnimation(AppConstants.accentColor.withOpacity(0.7)))),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
