import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class PurchasesScreen extends StatefulWidget {
  const PurchasesScreen({super.key});
  @override
  State<PurchasesScreen> createState() => _PurchasesScreenState();
}

class _PurchasesScreenState extends State<PurchasesScreen> {
  List<dynamic> _purchases = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'purchases'});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) _purchases = res['purchases'] ?? [];
      });
    }
  }

  void _openAddPurchase() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddPurchaseSheet(onSuccess: _loadData),
    );
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'received': return AppConstants.successColor;
      case 'ordered': return AppConstants.warningColor;
      case 'pending': return AppConstants.infoColor;
      default: return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('কেনাকাটা / Purchase Orders'),
        backgroundColor: Colors.teal,
        foregroundColor: Colors.white,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openAddPurchase,
        backgroundColor: Colors.teal,
        icon: const Icon(Icons.add_shopping_cart_rounded),
        label: const Text('নতুন কেনাকাটা'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.teal))
          : _purchases.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.shopping_cart_outlined, size: 80, color: Colors.grey.shade300),
                      const SizedBox(height: 16),
                      const Text('কোন purchase নেই', style: TextStyle(fontSize: 18, color: Colors.grey)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadData,
                  color: Colors.teal,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: _purchases.length,
                    itemBuilder: (ctx, i) {
                      final p = _purchases[i];
                      final status = p['status']?.toString() ?? '';
                      final color = _statusColor(status);
                      return Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                          side: BorderSide(color: Colors.grey.shade200),
                        ),
                        elevation: 0,
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: Colors.teal.withOpacity(0.1),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(Icons.shopping_cart, color: Colors.teal, size: 22),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      p['po_number']?.toString() ?? 'N/A',
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      'Supplier: ${p['dealer_name'] ?? 'Unknown'}',
                                      style: const TextStyle(fontSize: 12, color: AppConstants.textLightColor),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      p['ordered_at']?.toString().substring(0, 10) ?? '',
                                      style: const TextStyle(fontSize: 11, color: AppConstants.textLightColor),
                                    ),
                                  ],
                                ),
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    '৳${double.tryParse(p['total']?.toString() ?? '0')?.toInt() ?? 0}',
                                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.teal),
                                  ),
                                  const SizedBox(height: 6),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: color.withOpacity(0.1),
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    child: Text(
                                      status.toUpperCase(),
                                      style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}

// ============= ADD PURCHASE BOTTOM SHEET =============
class _AddPurchaseSheet extends StatefulWidget {
  final VoidCallback onSuccess;
  const _AddPurchaseSheet({required this.onSuccess});
  @override
  State<_AddPurchaseSheet> createState() => _AddPurchaseSheetState();
}

class _AddPurchaseSheetState extends State<_AddPurchaseSheet> {
  List<dynamic> _dealers = [];
  List<dynamic> _products = [];
  int? _selectedDealer;
  String _status = 'received';
  final List<Map<String, dynamic>> _items = [];
  bool _isLoading = true;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    final d = await ApiService.get('admin.php', params: {'action': 'get_dealers'});
    final p = await ApiService.get('admin.php', params: {'action': 'inventory_list'});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (d['success'] == true) _dealers = d['dealers'] ?? [];
        if (p['success'] == true) _products = p['products'] ?? [];
      });
    }
  }

  void _addItem() {
    showDialog(
      context: context,
      builder: (_) => _ProductPickerDialog(
        products: _products,
        onSelected: (product, qty, cost) {
          setState(() {
            final idx = _items.indexWhere((i) => i['id'] == product['id']);
            if (idx >= 0) {
              _items[idx]['qty'] = (_items[idx]['qty'] as double) + qty;
            } else {
              _items.add({
                'id': product['id'],
                'name': product['name'],
                'qty': qty,
                'cost_price': cost,
              });
            }
          });
        },
      ),
    );
  }

  double get _total => _items.fold(0, (s, i) => s + ((i['qty'] as double) * (i['cost_price'] as double)));

  Future<void> _submit() async {
    if (_selectedDealer == null || _items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Supplier ও পণ্য বেছে নিন'), backgroundColor: AppConstants.dangerColor),
      );
      return;
    }
    setState(() => _isSaving = true);
    final res = await ApiService.post('admin.php?action=add_purchase', {
      'dealer_id': _selectedDealer,
      'status': _status,
      'items': _items,
    });
    setState(() => _isSaving = false);
    if (!mounted) return;
    if (res['success'] == true) {
      Navigator.pop(context);
      widget.onSuccess();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('✅ Purchase সফলভাবে যোগ হয়েছে'), backgroundColor: AppConstants.successColor),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'Error'), backgroundColor: AppConstants.dangerColor),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.92,
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
            decoration: BoxDecoration(
              color: Colors.teal,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
            ),
            child: Row(
              children: [
                const Expanded(child: Text('নতুন Purchase Order', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700))),
                IconButton(icon: const Icon(Icons.close, color: Colors.white), onPressed: () => Navigator.pop(context)),
              ],
            ),
          ),
          if (_isLoading)
            const Expanded(child: Center(child: CircularProgressIndicator()))
          else
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Dealer
                    const Text('Supplier বেছে নিন *', style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<int>(
                      value: _selectedDealer,
                      decoration: InputDecoration(border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)), prefixIcon: const Icon(Icons.local_shipping_rounded)),
                      hint: const Text('Supplier'),
                      items: _dealers.map((d) => DropdownMenuItem<int>(
                        value: int.parse(d['id'].toString()),
                        child: Text(d['name']),
                      )).toList(),
                      onChanged: (v) => setState(() => _selectedDealer = v),
                    ),
                    const SizedBox(height: 16),
                    // Status
                    const Text('Status', style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    Row(
                      children: ['received', 'ordered'].map((s) => Expanded(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          child: ChoiceChip(
                            label: Text(s.toUpperCase()),
                            selected: _status == s,
                            selectedColor: Colors.teal.withOpacity(0.2),
                            onSelected: (sel) { if (sel) setState(() => _status = s); },
                          ),
                        ),
                      )).toList(),
                    ),
                    const SizedBox(height: 16),
                    // Items
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('পণ্য তালিকা *', style: TextStyle(fontWeight: FontWeight.w600)),
                        TextButton.icon(
                          onPressed: _addItem,
                          icon: const Icon(Icons.add),
                          label: const Text('যোগ করুন'),
                          style: TextButton.styleFrom(foregroundColor: Colors.teal),
                        ),
                      ],
                    ),
                    ..._items.map((item) => Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        title: Text(item['name'], style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        subtitle: Text('Qty: ${item['qty']} × ৳${item['cost_price']}'),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text('৳${(item['qty'] * item['cost_price']).toInt()}', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.teal)),
                            IconButton(
                              icon: const Icon(Icons.delete_outline, color: AppConstants.dangerColor, size: 20),
                              onPressed: () => setState(() => _items.removeWhere((i) => i['id'] == item['id'])),
                            ),
                          ],
                        ),
                      ),
                    )),
                    if (_items.isEmpty)
                      Container(
                        padding: const EdgeInsets.all(20),
                        alignment: Alignment.center,
                        decoration: BoxDecoration(color: Colors.grey.shade50, borderRadius: BorderRadius.circular(10)),
                        child: const Text('কোন পণ্য যোগ করা হয়নি', style: TextStyle(color: Colors.grey)),
                      ),
                    const SizedBox(height: 16),
                    if (_items.isNotEmpty)
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(color: Colors.teal.withOpacity(0.05), borderRadius: BorderRadius.circular(10)),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('মোট', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                            Text('৳${_total.toInt()}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: Colors.teal)),
                          ],
                        ),
                      ),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        onPressed: _isSaving ? null : _submit,
                        style: ElevatedButton.styleFrom(backgroundColor: Colors.teal, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                        child: _isSaving
                            ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                            : const Text('Purchase Save করুন', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                      ),
                    ),
                    const SizedBox(height: 30),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ProductPickerDialog extends StatefulWidget {
  final List<dynamic> products;
  final void Function(dynamic product, double qty, double cost) onSelected;
  const _ProductPickerDialog({required this.products, required this.onSelected});
  @override
  State<_ProductPickerDialog> createState() => _ProductPickerDialogState();
}

class _ProductPickerDialogState extends State<_ProductPickerDialog> {
  dynamic _selected;
  final _qtyCtrl = TextEditingController(text: '1');
  final _costCtrl = TextEditingController();
  final _searchCtrl = TextEditingController();
  List<dynamic> _filtered = [];

  @override
  void initState() {
    super.initState();
    _filtered = widget.products;
  }

  void _filter(String q) {
    setState(() {
      _filtered = widget.products.where((p) => p['name'].toString().toLowerCase().contains(q.toLowerCase())).toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text('পণ্য বেছে নিন'),
      content: SizedBox(
        width: double.maxFinite,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _searchCtrl,
              onChanged: _filter,
              decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: 'পণ্যের নাম', border: OutlineInputBorder(borderRadius: BorderRadius.circular(10))),
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 200,
              child: ListView.builder(
                itemCount: _filtered.length,
                itemBuilder: (_, i) {
                  final p = _filtered[i];
                  final isSelected = _selected?['id'] == p['id'];
                  return ListTile(
                    dense: true,
                    selected: isSelected,
                    selectedColor: Colors.teal,
                    title: Text(p['name'], style: const TextStyle(fontSize: 13)),
                    subtitle: Text('Purchase: ৳${p['purchase_price'] ?? 0}'),
                    onTap: () {
                      setState(() {
                        _selected = p;
                        _costCtrl.text = (p['purchase_price'] ?? '').toString();
                      });
                    },
                  );
                },
              ),
            ),
            if (_selected != null) ...[
              const Divider(),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _qtyCtrl,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(labelText: 'Qty', border: OutlineInputBorder(borderRadius: BorderRadius.circular(8))),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: TextField(
                      controller: _costCtrl,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(labelText: 'Cost ৳', border: OutlineInputBorder(borderRadius: BorderRadius.circular(8))),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('বাতিল')),
        ElevatedButton(
          onPressed: _selected == null ? null : () {
            final qty = double.tryParse(_qtyCtrl.text) ?? 1;
            final cost = double.tryParse(_costCtrl.text) ?? 0;
            widget.onSelected(_selected!, qty, cost);
            Navigator.pop(context);
          },
          style: ElevatedButton.styleFrom(backgroundColor: Colors.teal, foregroundColor: Colors.white),
          child: const Text('যোগ করুন'),
        ),
      ],
    );
  }
}
