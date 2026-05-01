import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class SupplierLedgerScreen extends StatefulWidget {
  const SupplierLedgerScreen({super.key});
  @override
  State<SupplierLedgerScreen> createState() => _SupplierLedgerScreenState();
}

class _SupplierLedgerScreenState extends State<SupplierLedgerScreen> {
  List<dynamic> _suppliers = [];
  bool _isLoading = true;
  String _search = '';

  @override
  void initState() { super.initState(); _loadData(); }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'suppliers_ledger', 'search': _search});
    if (mounted) setState(() { _isLoading = false; if (res['success'] == true) _suppliers = res['suppliers'] ?? []; });
  }

  void _showPaymentDialog(dynamic supplier) {
    final amtCtrl = TextEditingController();
    final noteCtrl = TextEditingController();
    String method = 'cash';
    
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Pay to ${supplier['name']}'),
        content: StatefulBuilder(
          builder: (ctx, setStateDialog) => SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('We Owe: ৳${supplier['balance']}', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.orange)),
                const SizedBox(height: 16),
                TextField(controller: amtCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Amount (৳)')),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  value: method,
                  decoration: const InputDecoration(labelText: 'Payment Method'),
                  items: ['cash', 'mobile', 'bank'].map((m) => DropdownMenuItem(value: m, child: Text(m.toUpperCase()))).toList(),
                  onChanged: (v) => setStateDialog(() => method = v!),
                ),
                const SizedBox(height: 12),
                TextField(controller: noteCtrl, maxLines: 2, decoration: const InputDecoration(labelText: 'Notes')),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              setState(() => _isLoading = true);
              final res = await ApiService.post('admin.php?action=supplier_payment', {
                'supplier_id': supplier['id'],
                'amount': double.tryParse(amtCtrl.text) ?? 0,
                'method': method,
                'notes': noteCtrl.text,
              });
              if (res['success'] == true) {
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Payment sent'), backgroundColor: AppConstants.successColor));
                _loadData();
              } else {
                setState(() => _isLoading = false);
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: ${res['message']}'), backgroundColor: AppConstants.dangerColor));
              }
            },
            child: const Text('Send Payment'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Supplier Dues'), backgroundColor: Colors.orange.shade800, foregroundColor: Colors.white),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Search supplier...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(icon: const Icon(Icons.clear), onPressed: () { setState(() => _search = ''); _loadData(); }),
              ),
              onChanged: (v) { _search = v; Future.delayed(const Duration(milliseconds: 600), () { if (_search == v) _loadData(); }); },
            ),
          ),
          Expanded(
            child: _isLoading
              ? const Center(child: CircularProgressIndicator())
              : ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  itemCount: _suppliers.length,
                  itemBuilder: (ctx, i) {
                    final s = _suppliers[i];
                    final balance = double.tryParse(s['balance'].toString()) ?? 0;
                    return Card(
                      elevation: 0,
                      margin: const EdgeInsets.only(bottom: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade200)),
                      child: ListTile(
                        leading: CircleAvatar(backgroundColor: Colors.orange.withOpacity(0.1), child: const Icon(Icons.local_shipping, color: Colors.orange)),
                        title: Text(s['name'], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                        subtitle: Text(s['phone'] ?? 'No Phone'),
                        trailing: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text('৳$balance', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: balance > 0 ? Colors.orange.shade800 : AppConstants.successColor)),
                            const SizedBox(height: 4),
                            InkWell(
                              onTap: () => _showPaymentDialog(s),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                                decoration: BoxDecoration(color: Colors.orange.shade600, borderRadius: BorderRadius.circular(20)),
                                child: const Text('Pay', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
          ),
        ],
      ),
    );
  }
}
