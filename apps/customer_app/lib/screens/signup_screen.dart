import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';

class SignupScreen extends StatefulWidget {
  const SignupScreen({Key? key}) : super(key: key);

  @override
  _SignupScreenState createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _usernameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();

  void _signup() async {
    if (_formKey.currentState!.validate()) {
      final api = Provider.of<ApiService>(context, listen: false);
      final result = await api.register(
        _nameController.text,
        _usernameController.text,
        _phoneController.text,
        _passwordController.text,
      );

      if (result['success']) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('🎉 Registration successful! Please login.')),
        );
        Navigator.pop(context);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('🛑 ${result['message']}')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final api = Provider.of<ApiService>(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Create Account'), backgroundColor: Colors.green),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(labelText: 'Full Name', prefixIcon: Icon(Icons.person)),
                validator: (v) => v!.isEmpty ? 'Enter your name' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _usernameController,
                decoration: const InputDecoration(labelText: 'Username', prefixIcon: Icon(Icons.alternate_email)),
                validator: (v) => v!.isEmpty ? 'Enter a username' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _phoneController,
                decoration: const InputDecoration(labelText: 'Phone Number', prefixIcon: Icon(Icons.phone)),
                keyboardType: TextInputType.phone,
                validator: (v) => v!.isEmpty ? 'Enter your phone' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _passwordController,
                decoration: const InputDecoration(labelText: 'Password', prefixIcon: Icon(Icons.lock)),
                obscureText: true,
                validator: (v) => v!.length < 6 ? 'Min 6 characters' : null,
              ),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: api.isLoading ? null : _signup,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: api.isLoading 
                    ? const CircularProgressIndicator(color: Colors.white) 
                    : const Text('Register', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
