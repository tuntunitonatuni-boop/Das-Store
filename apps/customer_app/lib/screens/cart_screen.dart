import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final api = Provider.of<ApiService>(context);
    final cartItems = api.cart.values.toList();

    return Scaffold(
      appBar: AppBar(title: const Text('Your Cart'), backgroundColor: Colors.green),
      body: cartItems.isEmpty
          ? const Center(child: Text('Your cart is empty!'))
          : Column(
              children: [
                Expanded(
                  child: ListView.builder(
                    itemCount: cartItems.length,
                    itemBuilder: (ctx, i) {
                      final item = cartItems[i];
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundImage: item['image'] != null ? NetworkImage(item['image']) : null,
                          child: item['image'] == null ? const Icon(Icons.shopping_bag) : null,
                        ),
                        title: Text(item['name']),
                        subtitle: Text('${item['price']} x ${item['qty']}'),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.remove_circle_outline),
                              onPressed: () => api.updateCartQty(item['id'].toString(), item['qty'] - 1),
                            ),
                            Text('${item['qty']}'),
                            IconButton(
                              icon: const Icon(Icons.add_circle_outline),
                              onPressed: () => api.updateCartQty(item['id'].toString(), item['qty'] + 1),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 10)],
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Total:', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          Text('৳${api.cartTotal.toStringAsFixed(2)}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.green)),
                        ],
                      ),
                      const SizedBox(height: 16),
                      SizedBox(
                        width: double.infinity,
                        height: 50,
                        child: ElevatedButton(
                          onPressed: () => _checkout(context, api),
                          style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white),
                          child: const Text('Checkout Now', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  void _checkout(BuildContext context, ApiService api) {
    final addressController = TextEditingController(text: api.user?['address'] ?? '');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm Order'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Deliver to:'),
            TextField(controller: addressController, decoration: const InputDecoration(hintText: 'Enter full address')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final result = await api.placeOrder(addressController.text);
              if (result['success']) {
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('✅ Order placed successfully!')));
                Navigator.pop(context); // Go back to Home
              } else {
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('🛑 ${result['message']}')));
              }
            },
            child: const Text('Place Order'),
          ),
        ],
      ),
    );
  }
}
