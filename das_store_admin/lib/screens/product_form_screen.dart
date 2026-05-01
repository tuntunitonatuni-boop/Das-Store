import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class ProductFormScreen extends StatefulWidget {
  const ProductFormScreen({super.key});
  @override
  State<ProductFormScreen> createState() => _ProductFormScreenState();
}

class _ProductFormScreenState extends State<ProductFormScreen> {
  List<dynamic> _categories = [];
  bool _isLoading = true;
  bool _isSaving = false;

  final _nameCtrl = TextEditingController();
  final _priceCtrl = TextEditingController();
  final _barcodeCtrl = TextEditingController();
  String _unit = 'pcs';
  int? _selectedCategory;

  @override
  void initState() { super.initState(); _loadCategories(); }
  @override
  void dispose() { _nameCtrl.dispose(); _priceCtrl.dispose(); _barcodeCtrl.dispose(); super.dispose(); }

  Future<void> _loadCategories() async {
    final res = await ApiService.get('admin.php', params: {'action': 'get_categories'});
    if (mounted) setState(() { _isLoading = false; if (res['success'] == true) _categories = res['categories'] ?? []; });
  }

  Future<void> _saveProduct() async {
    if (_nameCtrl.text.isEmpty || _selectedCategory == null || _priceCtrl.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please fill all required fields')));
      return;
    }

    setState(() => _isSaving = true);
    final res = await ApiService.post('admin.php?action=add_product', {
      'name': _nameCtrl.text,
      'category_id': _selectedCategory,
      'sale_price': double.tryParse(_priceCtrl.text) ?? 0,
      'unit': _unit,
      'barcode': _barcodeCtrl.text,
    });
    
    if (!mounted) return;
    setState(() => _isSaving = false);
    
    if (res['success'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('Product Added Successfully!'), backgroundColor: AppConstants.successColor));
      Navigator.pop(context, true); // return true to refresh list
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: ${res['message']}'), backgroundColor: AppConstants.dangerColor));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Add New Product'), backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator())
        : SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)]),
                  child: Column(
                    children: [
                      TextField(controller: _nameCtrl, decoration: const InputDecoration(labelText: 'Product Name *', prefixIcon: Icon(Icons.inventory))),
                      const SizedBox(height: 16),
                      DropdownButtonFormField<int>(
                        value: _selectedCategory,
                        decoration: const InputDecoration(labelText: 'Category *', prefixIcon: Icon(Icons.category)),
                        items: _categories.map((c) => DropdownMenuItem(value: int.parse(c['id'].toString()), child: Text(c['name']))).toList(),
                        onChanged: (v) => setState(() => _selectedCategory = v),
                      ),
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(child: TextField(controller: _priceCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Sale Price (৳) *', prefixIcon: Icon(Icons.money)))),
                          const SizedBox(width: 12),
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              value: _unit,
                              decoration: const InputDecoration(labelText: 'Unit *'),
                              items: ['pcs', 'kg', 'gm', 'ltr', 'ml', 'box', 'pack'].map((u) => DropdownMenuItem(value: u, child: Text(u))).toList(),
                              onChanged: (v) => setState(() => _unit = v!),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      TextField(controller: _barcodeCtrl, decoration: const InputDecoration(labelText: 'Barcode (Optional)', prefixIcon: Icon(Icons.qr_code))),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                SizedBox(
                  height: 52,
                  child: ElevatedButton(
                    onPressed: _isSaving ? null : _saveProduct,
                    style: ElevatedButton.styleFrom(backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white),
                    child: _isSaving ? const CircularProgressIndicator(color: Colors.white) : const Text('Save Product', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  ),
                ),
              ],
            ),
          ),
    );
  }
}
