import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../utils/constants.dart';
import '../providers/auth_provider.dart';
import 'dashboard_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _userCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  bool _showPass = false;

  void _login() async {
    if (!_formKey.currentState!.validate()) return;
    final auth = context.read<AuthProvider>();
    final ok = await auth.login(_userCtrl.text.trim(), _passCtrl.text);
    if (!mounted) return;
    if (ok) {
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const DashboardScreen()));
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(auth.error ?? 'Login failed'),
        backgroundColor: AppConstants.dangerColor,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ));
    }
  }

  @override
  void dispose() { _userCtrl.dispose(); _passCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final loading = context.watch<AuthProvider>().isLoading;
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter,
            colors: [Color(0xFF0f172a), Color(0xFF1e293b)], stops: [0.0, 0.5]),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(28),
              child: Column(children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppConstants.accentColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppConstants.accentColor.withOpacity(0.25)),
                  ),
                  child: const Icon(Icons.admin_panel_settings_rounded, size: 52, color: AppConstants.accentColor),
                ),
                const SizedBox(height: 20),
                const Text('Admin Login', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.white)),
                const SizedBox(height: 6),
                Text('Das Store Management', style: TextStyle(fontSize: 14, color: Colors.white.withOpacity(0.5))),
                const SizedBox(height: 36),

                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20),
                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 20, offset: const Offset(0, 8))]),
                  child: Form(
                    key: _formKey,
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      const Text('Username', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppConstants.textLightColor)),
                      const SizedBox(height: 8),
                      TextFormField(
                        controller: _userCtrl,
                        decoration: const InputDecoration(hintText: 'Enter admin username', prefixIcon: Icon(Icons.person_outline_rounded)),
                        validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                      ),
                      const SizedBox(height: 18),
                      const Text('Password', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppConstants.textLightColor)),
                      const SizedBox(height: 8),
                      TextFormField(
                        controller: _passCtrl,
                        obscureText: !_showPass,
                        decoration: InputDecoration(
                          hintText: 'Enter password',
                          prefixIcon: const Icon(Icons.lock_outline_rounded),
                          suffixIcon: IconButton(
                            icon: Icon(_showPass ? Icons.visibility_off_rounded : Icons.visibility_rounded, color: Colors.grey),
                            onPressed: () => setState(() => _showPass = !_showPass),
                          ),
                        ),
                        validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                      ),
                      const SizedBox(height: 28),
                      SizedBox(height: 52, child: ElevatedButton(
                        onPressed: loading ? null : _login,
                        style: ElevatedButton.styleFrom(backgroundColor: AppConstants.primaryColor, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                        child: loading
                            ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                            : const Text('Login', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: Colors.white)),
                      )),
                    ]),
                  ),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
