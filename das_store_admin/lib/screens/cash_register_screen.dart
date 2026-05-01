import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class CashRegisterScreen extends StatefulWidget {
  const CashRegisterScreen({super.key});
  @override
  State<CashRegisterScreen> createState() => _CashRegisterScreenState();
}

class _CashRegisterScreenState extends State<CashRegisterScreen> {
  String _status = 'unopened';
  Map<String, dynamic>? _register;
  bool _isLoading = true;

  final _openCtrl = TextEditingController();
  final _closeCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();

  @override
  void initState() { super.initState(); _loadStatus(); }
  @override
  void dispose() { _openCtrl.dispose(); _closeCtrl.dispose(); _notesCtrl.dispose(); super.dispose(); }

  Future<void> _loadStatus() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'cash_register_status'});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) {
          _status = res['status'] ?? 'unopened';
          _register = res['register'];
        }
      });
    }
  }

  Future<void> _openRegister() async {
    final amt = double.tryParse(_openCtrl.text) ?? 0;
    setState(() => _isLoading = true);
    final res = await ApiService.post('admin.php?action=cash_register_open', {'opening_cash': amt});
    if (!mounted) return;
    if (res['success'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Register Opened!'), backgroundColor: AppConstants.successColor));
      _openCtrl.clear();
      _loadStatus();
    } else {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: ${res['message']}'), backgroundColor: AppConstants.dangerColor));
    }
  }

  Future<void> _closeRegister() async {
    final amt = double.tryParse(_closeCtrl.text) ?? 0;
    setState(() => _isLoading = true);
    final res = await ApiService.post('admin.php?action=cash_register_close', {
      'closing_cash': amt,
      'notes': _notesCtrl.text
    });
    if (!mounted) return;
    if (res['success'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Register Closed!'), backgroundColor: AppConstants.successColor));
      _closeCtrl.clear();
      _notesCtrl.clear();
      _loadStatus();
    } else {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: ${res['message']}'), backgroundColor: AppConstants.dangerColor));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Cash Register'), backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
        : SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Status Header
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(colors: _status == 'open' ? [const Color(0xFF16a34a), const Color(0xFF059669)] : _status == 'closed' ? [const Color(0xFFdc2626), const Color(0xFF991b1b)] : [const Color(0xFF475569), const Color(0xFF1e293b)]),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Column(
                    children: [
                      Text(
                        _status == 'open' ? '🟢 OPEN' : _status == 'closed' ? '🔴 CLOSED' : '⬛ NOT OPENED YET',
                        style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900, letterSpacing: 2),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _register != null ? 'Opened at: ${_register!['created_at']}' : 'Please open the register to start sales.',
                        style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),

                // UNOPENED STATE
                if (_status == 'unopened' || _status == 'closed') ...[
                  if (_status == 'closed')
                    Container(
                      margin: const EdgeInsets.only(bottom: 24),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.red.shade200)),
                      child: Column(
                        children: [
                          const Text('Register is closed for today.', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold, fontSize: 16)),
                          const SizedBox(height: 12),
                          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Total Sales:'), Text('৳${_register?['total_sales'] ?? 0}', style: const TextStyle(fontWeight: FontWeight.bold))]),
                          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Closing Cash:'), Text('৳${_register?['closing_cash'] ?? 0}', style: const TextStyle(fontWeight: FontWeight.bold))]),
                        ],
                      ),
                    ),

                  if (_status == 'unopened') ...[
                    const Text('Open Register', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _openCtrl,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Opening Cash (in drawer)', prefixIcon: Icon(Icons.money)),
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: _openRegister,
                      style: ElevatedButton.styleFrom(backgroundColor: AppConstants.successColor, padding: const EdgeInsets.symmetric(vertical: 16)),
                      child: const Text('Open Register', style: TextStyle(fontSize: 16)),
                    ),
                  ]
                ],

                // OPEN STATE
                if (_status == 'open') ...[
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)]),
                    child: Column(
                      children: [
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Opening Cash:'), Text('৳${_register?['opening_cash'] ?? 0}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16))]),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  
                  const Text('Close Register', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _closeCtrl,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Actual Closing Cash', prefixIcon: Icon(Icons.money)),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _notesCtrl,
                    maxLines: 2,
                    decoration: const InputDecoration(labelText: 'Notes (optional)', prefixIcon: Icon(Icons.note)),
                  ),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: _closeRegister,
                    style: ElevatedButton.styleFrom(backgroundColor: AppConstants.dangerColor, padding: const EdgeInsets.symmetric(vertical: 16)),
                    child: const Text('Close Register', style: TextStyle(fontSize: 16)),
                  ),
                ],
              ],
            ),
          ),
    );
  }
}
