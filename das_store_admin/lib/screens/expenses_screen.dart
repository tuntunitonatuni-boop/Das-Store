import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class ExpensesScreen extends StatefulWidget {
  const ExpensesScreen({super.key});
  @override
  State<ExpensesScreen> createState() => _ExpensesScreenState();
}

class _ExpensesScreenState extends State<ExpensesScreen> {
  List<dynamic> _expenses = [];
  bool _isLoading = true;

  @override
  void initState() { super.initState(); _loadData(); }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'expenses'});
    if (mounted) setState(() { _isLoading = false; if (res['success'] == true) _expenses = res['expenses'] ?? []; });
  }

  void _showAddDialog() {
    final amtCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    String cat = 'shop';
    
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Add Expense'),
        content: StatefulBuilder(
          builder: (ctx, setStateDialog) => SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  value: cat,
                  decoration: const InputDecoration(labelText: 'Category'),
                  items: const [
                    DropdownMenuItem(value: 'shop', child: Text('Shop (Rent, Utility)')),
                    DropdownMenuItem(value: 'salary', child: Text('Salary')),
                    DropdownMenuItem(value: 'supplies', child: Text('Supplies/Packaging')),
                    DropdownMenuItem(value: 'transport', child: Text('Transport')),
                    DropdownMenuItem(value: 'other', child: Text('Other')),
                  ],
                  onChanged: (v) => setStateDialog(() => cat = v!),
                ),
                const SizedBox(height: 12),
                TextField(controller: amtCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Amount (৳)')),
                const SizedBox(height: 12),
                TextField(controller: descCtrl, maxLines: 2, decoration: const InputDecoration(labelText: 'Description')),
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
              final res = await ApiService.post('admin.php?action=add_expense', {
                'amount': double.tryParse(amtCtrl.text) ?? 0,
                'category': cat,
                'description': descCtrl.text,
              });
              if (res['success'] == true) {
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Added successfully'), backgroundColor: AppConstants.successColor));
                _loadData();
              } else {
                setState(() => _isLoading = false);
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: ${res['message']}'), backgroundColor: AppConstants.dangerColor));
              }
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Expenses'), backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white),
      body: _isLoading
        ? const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
        : ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: _expenses.length,
            itemBuilder: (ctx, i) {
              final e = _expenses[i];
              return Card(
                elevation: 0,
                margin: const EdgeInsets.only(bottom: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade200)),
                child: ListTile(
                  leading: CircleAvatar(backgroundColor: AppConstants.dangerColor.withOpacity(0.1), child: const Icon(Icons.money_off, color: AppConstants.dangerColor)),
                  title: Text(e['category'].toString().toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  subtitle: Text('${e['description']}\n${e['expense_date']}'),
                  isThreeLine: true,
                  trailing: Text('৳${e['amount']}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppConstants.dangerColor)),
                ),
              );
            },
          ),
      floatingActionButton: FloatingActionButton(
        onPressed: _showAddDialog,
        backgroundColor: AppConstants.dangerColor,
        child: const Icon(Icons.add),
      ),
    );
  }
}
