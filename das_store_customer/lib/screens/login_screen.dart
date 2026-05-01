import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../utils/constants.dart';
import '../providers/auth_provider.dart';
import 'home_screen.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isPasswordVisible = false;

  void _login() async {
    if (!_formKey.currentState!.validate()) return;

    final auth = context.read<AuthProvider>();
    final success = await auth.login(
      _phoneController.text.trim(),
      _passwordController.text,
    );

    if (!mounted) return;

    if (success) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const HomeScreen()),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(auth.error ?? 'Login failed'),
          backgroundColor: AppConstants.dangerColor,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
    }
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isLoading = context.watch<AuthProvider>().isLoading;

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFF16a34a), Color(0xFFf0fdf4)],
            stops: [0.0, 0.4],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                children: [
                  // Logo Section
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 20, offset: const Offset(0, 8)),
                      ],
                    ),
                    child: const Icon(Icons.storefront_rounded, size: 56, color: AppConstants.primaryColor),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Welcome Back!',
                    style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: AppConstants.textColor),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Das Store এ লগইন করুন',
                    style: TextStyle(fontSize: 15, color: AppConstants.textLightColor),
                  ),
                  const SizedBox(height: 36),

                  // Form Card
                  Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 16, offset: const Offset(0, 6)),
                      ],
                    ),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          // Phone Input
                          const Text('ফোন / ইউজারনেম', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppConstants.textLightColor)),
                          const SizedBox(height: 8),
                          TextFormField(
                            controller: _phoneController,
                            keyboardType: TextInputType.text,
                            decoration: const InputDecoration(
                              hintText: 'ফোন অথবা ইউজারনেম দিন',
                              prefixIcon: Icon(Icons.person_outline_rounded),
                            ),
                            validator: (v) => (v == null || v.isEmpty) ? 'এই ফিল্ড আবশ্যক' : null,
                          ),
                          const SizedBox(height: 18),

                          // Password Input
                          const Text('পাসওয়ার্ড', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppConstants.textLightColor)),
                          const SizedBox(height: 8),
                          TextFormField(
                            controller: _passwordController,
                            obscureText: !_isPasswordVisible,
                            decoration: InputDecoration(
                              hintText: 'আপনার পাসওয়ার্ড দিন',
                              prefixIcon: const Icon(Icons.lock_outline_rounded),
                              suffixIcon: IconButton(
                                icon: Icon(
                                  _isPasswordVisible ? Icons.visibility_off_rounded : Icons.visibility_rounded,
                                  color: Colors.grey,
                                ),
                                onPressed: () => setState(() => _isPasswordVisible = !_isPasswordVisible),
                              ),
                            ),
                            validator: (v) => (v == null || v.isEmpty) ? 'পাসওয়ার্ড দিন' : null,
                          ),

                          const SizedBox(height: 28),

                          // Login Button
                          SizedBox(
                            height: 52,
                            child: ElevatedButton(
                              onPressed: isLoading ? null : _login,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppConstants.primaryColor,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                                elevation: 2,
                              ),
                              child: isLoading
                                  ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                                  : const Text('লগইন', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: Colors.white)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 28),

                  // Register Link
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text("একাউন্ট নেই?", style: TextStyle(color: AppConstants.textLightColor)),
                      TextButton(
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen())),
                        child: const Text(
                          'রেজিস্টার করুন',
                          style: TextStyle(color: AppConstants.primaryColor, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
