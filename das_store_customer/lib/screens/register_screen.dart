import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../utils/constants.dart';
import '../providers/auth_provider.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _usernameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  bool _isPasswordVisible = false;

  void _register() async {
    if (!_formKey.currentState!.validate()) return;

    if (_passwordController.text != _confirmController.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('পাসওয়ার্ড মিলছে না!'),
          backgroundColor: AppConstants.dangerColor,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    final success = await auth.register(
      _nameController.text.trim(),
      _usernameController.text.trim(),
      _phoneController.text.trim(),
      _passwordController.text,
    );

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('রেজিস্ট্রেশন সফল! এখন লগইন করুন।'),
          backgroundColor: AppConstants.primaryColor,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(auth.error ?? 'রেজিস্ট্রেশন ব্যর্থ হয়েছে'),
          backgroundColor: AppConstants.dangerColor,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _usernameController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
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
            stops: [0.0, 0.35],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                children: [
                  // Logo
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 16, offset: const Offset(0, 6)),
                      ],
                    ),
                    child: const Icon(Icons.person_add_alt_1_rounded, size: 48, color: AppConstants.primaryColor),
                  ),
                  const SizedBox(height: 16),
                  const Text('নতুন একাউন্ট তৈরি করুন', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: AppConstants.textColor)),
                  const SizedBox(height: 28),

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
                          _buildField('আপনার নাম', _nameController, Icons.badge_outlined, 'পুরো নাম দিন'),
                          const SizedBox(height: 14),
                          _buildField('ইউজারনেম', _usernameController, Icons.alternate_email_rounded, 'ইউজারনেম দিন'),
                          const SizedBox(height: 14),
                          _buildField('ফোন নম্বর', _phoneController, Icons.phone_outlined, '01XXXXXXXXX', keyboard: TextInputType.phone),
                          const SizedBox(height: 14),
                          _buildField('পাসওয়ার্ড', _passwordController, Icons.lock_outline_rounded, 'পাসওয়ার্ড দিন', isPassword: true),
                          const SizedBox(height: 14),
                          _buildField('পাসওয়ার্ড নিশ্চিত করুন', _confirmController, Icons.lock_outline_rounded, 'আবার পাসওয়ার্ড দিন', isPassword: true),
                          const SizedBox(height: 28),

                          SizedBox(
                            height: 52,
                            child: ElevatedButton(
                              onPressed: isLoading ? null : _register,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppConstants.primaryColor,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                              ),
                              child: isLoading
                                  ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                                  : const Text('রেজিস্টার', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: Colors.white)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text("একাউন্ট আছে?", style: TextStyle(color: AppConstants.textLightColor)),
                      TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('লগইন করুন', style: TextStyle(color: AppConstants.primaryColor, fontWeight: FontWeight.bold)),
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

  Widget _buildField(String label, TextEditingController ctrl, IconData icon, String hint, {TextInputType? keyboard, bool isPassword = false}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppConstants.textLightColor)),
        const SizedBox(height: 6),
        TextFormField(
          controller: ctrl,
          keyboardType: keyboard ?? TextInputType.text,
          obscureText: isPassword && !_isPasswordVisible,
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: Icon(icon),
            suffixIcon: isPassword
                ? IconButton(
                    icon: Icon(_isPasswordVisible ? Icons.visibility_off_rounded : Icons.visibility_rounded, color: Colors.grey),
                    onPressed: () => setState(() => _isPasswordVisible = !_isPasswordVisible),
                  )
                : null,
          ),
          validator: (v) => (v == null || v.isEmpty) ? '$label দিন' : null,
        ),
      ],
    );
  }
}
