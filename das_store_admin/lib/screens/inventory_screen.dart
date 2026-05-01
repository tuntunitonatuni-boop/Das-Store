import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';
import 'product_form_screen.dart';

class InventoryScreen extends StatefulWidget {
  const InventoryScreen({super.key});
  @override
  State<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends State<InventoryScreen> {
  List<dynamic> _products = [];
  bool _isLoading = true;
  String _search = '';

  @override
  void initState() { super.initState(); _loadData(); }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'inventory_list', 'search': _search});
    if (mounted) setState(() { _isLoading = false; if (res['success'] == true) _products = res['products'] ?? []; });
  }

  void _showUpdateDialog(dynamic product) {
    final dCtrl = TextEditingController(text: product['display_qty'].toString());
    final wCtrl = TextEditingController(text: product['warehouse_qty'].toString());
    final pCtrl = TextEditingController(text: product['sale_price'].toString());

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(product['name'], style: const TextStyle(fontSize: 16)),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: pCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Sale Price (৳)')),
              const SizedBox(height: 12),
              TextField(controller: dCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Display Quantity')),
              const SizedBox(height: 12),
              TextField(controller: wCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Warehouse Quantity')),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              setState(() => _isLoading = true);
              final res = await ApiService.post('admin.php?action=update_inventory', {
                'product_id': product['id'],
                'sale_price': double.tryParse(pCtrl.text) ?? 0,
                'display_qty': int.tryParse(dCtrl.text) ?? 0,
                'warehouse_qty': int.tryParse(wCtrl.text) ?? 0,
              });
              if (res['success'] == true) {
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Updated successfully'), backgroundColor: AppConstants.successColor));
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
      appBar: AppBar(title: const Text('Inventory Management'), backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Search products...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(icon: const Icon(Icons.clear), onPressed: () { setState(() => _search = ''); _loadData(); }),
              ),
              onChanged: (v) { _search = v; Future.delayed(const Duration(milliseconds: 600), () { if (_search == v) _loadData(); }); },
            ),
          ),
          Expanded(
            child: _isLoading
              ? const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
              : ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  itemCount: _products.length,
                  itemBuilder: (ctx, i) {
                    final p = _products[i];
                    final dQty = (double.tryParse(p['display_qty'].toString()) ?? 0.0).toInt();
                    final wQty = (double.tryParse(p['warehouse_qty'].toString()) ?? 0.0).toInt();
                    final total = dQty + wQty;
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 4)]),
                      child: ListTile(
                        title: Text(p['name'], style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                        subtitle: Text('Price: ৳${p['sale_price']} | Total Stock: $total'),
                        trailing: IconButton(icon: const Icon(Icons.edit, color: AppConstants.accentColor), onPressed: () => _showUpdateDialog(p)),
                      ),
                    );
                  },
                ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          final res = await Navigator.push(context, MaterialPageRoute(builder: (_) => const ProductFormScreen()));
          if (res == true) _loadData(); // reload if product was added
        },
        backgroundColor: AppConstants.primaryColor,
        child: const Icon(Icons.add),
      ),
    );
  }
}
