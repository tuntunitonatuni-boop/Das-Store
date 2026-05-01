import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class PosScreen extends StatefulWidget {
  const PosScreen({super.key});
  @override
  State<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends State<PosScreen> {
  List<dynamic> _products = [];
  List<dynamic> _customers = [];
  List<Map<String, dynamic>> _cart = [];
  
  bool _isLoading = true;
  String _searchQuery = '';
  int? _selectedCustomer;
  String _paymentMethod = 'cash';
  double _discount = 0;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final pRes = await ApiService.get('admin.php', params: {'action': 'pos_products', 'search': _searchQuery});
    final cRes = await ApiService.get('admin.php', params: {'action': 'customers'});
    
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (pRes['success'] == true) _products = pRes['products'] ?? [];
        if (cRes['success'] == true) _customers = cRes['customers'] ?? [];
      });
    }
  }

  void _addToCart(dynamic product) {
    setState(() {
      final idx = _cart.indexWhere((item) => item['id'] == product['id']);
      if (idx >= 0) {
        _cart[idx]['qty'] += 1;
      } else {
        _cart.add({
          'id': product['id'],
          'name': product['name'],
          'price': double.tryParse(product['sale_price'].toString()) ?? 0,
          'qty': 1,
          'unit': product['unit']
        });
      }
    });
  }

  void _updateCartQty(int index, int delta) {
    setState(() {
      _cart[index]['qty'] += delta;
      if (_cart[index]['qty'] <= 0) {
        _cart.removeAt(index);
      }
    });
  }

  double get _subtotal => _cart.fold(0, (sum, item) => sum + (item['price'] * item['qty']));
  double get _total => _subtotal - _discount;

  Future<void> _checkout() async {
    if (_cart.isEmpty) return;
    
    showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator(color: AppConstants.accentColor)));
    
    final payload = {
      'customer_id': _selectedCustomer,
      'payment_method': _paymentMethod,
      'discount': _discount,
      'amount_paid': _total,
      'items': _cart,
    };
    
    final res = await ApiService.post('admin.php?action=pos_checkout', payload);
    if (!mounted) return;
    Navigator.pop(context); // close loading
    
    if (res['success'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('✅ Sale Completed!'), backgroundColor: AppConstants.successColor));
      setState(() {
        _cart.clear();
        _discount = 0;
        _selectedCustomer = null;
      });
      _loadData(); // reload stock
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: ${res['message']}'), backgroundColor: AppConstants.dangerColor));
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDesktop = MediaQuery.of(context).size.width > 800;
    
    final productsGrid = Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            decoration: InputDecoration(
              hintText: 'Search products or scan barcode...',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(icon: const Icon(Icons.clear), onPressed: () {
                setState(() => _searchQuery = '');
                _loadData();
              }),
            ),
            onChanged: (val) {
              _searchQuery = val;
              // Debounce could be added here
              Future.delayed(const Duration(milliseconds: 500), () {
                if (_searchQuery == val) _loadData();
              });
            },
          ),
        ),
        Expanded(
          child: _isLoading 
            ? const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
            : GridView.builder(
                padding: const EdgeInsets.all(12),
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: isDesktop ? 4 : 2,
                  childAspectRatio: 0.85,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                ),
                itemCount: _products.length,
                itemBuilder: (ctx, i) {
                  final p = _products[i];
                  final stock = (double.tryParse(p['total_qty'].toString()) ?? 0.0).toInt();
                  return InkWell(
                    onTap: stock > 0 ? () => _addToCart(p) : null,
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.grey.shade200),
                        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 4)],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Expanded(
                            child: Container(
                              decoration: BoxDecoration(
                                color: Colors.grey.shade100,
                                borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                              ),
                              child: const Icon(Icons.inventory_2_rounded, size: 40, color: Colors.grey), // Placeholder
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.all(8.0),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(p['name'], maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                                const SizedBox(height: 4),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text('৳${p['sale_price']}', style: const TextStyle(fontWeight: FontWeight.w800, color: AppConstants.accentColor)),
                                    Text('Stock: $stock', style: TextStyle(fontSize: 11, color: stock > 0 ? AppConstants.textLightColor : AppConstants.dangerColor)),
                                  ],
                                ),
                              ],
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
    );

    final cartPanel = Container(
      width: isDesktop ? 400 : double.infinity,
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(left: BorderSide(color: Colors.grey.shade200)),
        boxShadow: isDesktop ? [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)] : null,
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            color: AppConstants.primaryColor,
            child: const Row(
              children: [
                Icon(Icons.shopping_cart_checkout_rounded, color: Colors.white),
                SizedBox(width: 8),
                Text('Current Sale', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          
          // Customer Select
          Padding(
            padding: const EdgeInsets.all(12),
            child: DropdownButtonFormField<int>(
              value: _selectedCustomer,
              decoration: const InputDecoration(labelText: 'Select Customer', prefixIcon: Icon(Icons.person_outline)),
              items: [
                const DropdownMenuItem(value: null, child: Text('Walk-in Customer')),
                ..._customers.map((c) => DropdownMenuItem(value: int.parse(c['id'].toString()), child: Text('${c['name']} (${c['phone']})'))),
              ],
              onChanged: (v) => setState(() => _selectedCustomer = v),
            ),
          ),
          
          const Divider(height: 1),
          
          // Cart Items
          Expanded(
            child: _cart.isEmpty 
              ? const Center(child: Text('Cart is empty', style: TextStyle(color: Colors.grey)))
              : ListView.builder(
                  itemCount: _cart.length,
                  itemBuilder: (ctx, i) {
                    final item = _cart[i];
                    return ListTile(
                      title: Text(item['name'], style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                      subtitle: Text('৳${item['price']} / ${item['unit']}'),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(icon: const Icon(Icons.remove_circle_outline, color: AppConstants.dangerColor), onPressed: () => _updateCartQty(i, -1)),
                          Text('${item['qty']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                          IconButton(icon: const Icon(Icons.add_circle_outline, color: AppConstants.successColor), onPressed: () => _updateCartQty(i, 1)),
                        ],
                      ),
                    );
                  },
                ),
          ),
          
          // Checkout Panel
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: Colors.grey.shade50, border: Border(top: BorderSide(color: Colors.grey.shade200))),
            child: Column(
              children: [
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  const Text('Subtotal'),
                  Text('৳${_subtotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w600)),
                ]),
                const SizedBox(height: 8),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  const Text('Discount'),
                  SizedBox(
                    width: 100,
                    height: 36,
                    child: TextField(
                      keyboardType: TextInputType.number,
                      textAlign: TextAlign.right,
                      decoration: const InputDecoration(contentPadding: EdgeInsets.symmetric(horizontal: 8), prefixText: '৳'),
                      onChanged: (v) => setState(() => _discount = double.tryParse(v) ?? 0),
                    ),
                  ),
                ]),
                const SizedBox(height: 12),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  const Text('Total to Pay', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                  Text('৳${_total.toStringAsFixed(2)}', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: AppConstants.accentColor)),
                ]),
                const SizedBox(height: 16),
                
                // Payment Methods
                Row(
                  children: ['cash', 'mobile', 'card'].map((m) => Expanded(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      child: ChoiceChip(
                        label: Text(m.toUpperCase(), style: const TextStyle(fontSize: 11)),
                        selected: _paymentMethod == m,
                        onSelected: (s) { if (s) setState(() => _paymentMethod = m); },
                        selectedColor: AppConstants.accentColor.withOpacity(0.2),
                      ),
                    ),
                  )).toList(),
                ),
                
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: _cart.isEmpty ? null : _checkout,
                    icon: const Icon(Icons.payment),
                    label: const Text('Checkout & Print', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(backgroundColor: AppConstants.accentColor, foregroundColor: Colors.white),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );

    return Scaffold(
      appBar: isDesktop ? null : AppBar(title: const Text('POS System'), backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white),
      body: isDesktop 
        ? Row(children: [Expanded(child: productsGrid), cartPanel])
        : productsGrid, // On mobile, we need a floating button or bottom sheet for cart
      floatingActionButton: isDesktop ? null : FloatingActionButton.extended(
        onPressed: () {
          showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.transparent, builder: (_) => Container(
            height: MediaQuery.of(context).size.height * 0.85,
            decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
            child: ClipRRect(borderRadius: const BorderRadius.vertical(top: Radius.circular(20)), child: cartPanel),
          ));
        },
        icon: const Icon(Icons.shopping_cart),
        label: Text('${_cart.length} items = ৳${_total.toStringAsFixed(0)}'),
        backgroundColor: AppConstants.accentColor,
      ),
    );
  }
}
