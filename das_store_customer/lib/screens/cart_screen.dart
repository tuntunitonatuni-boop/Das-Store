import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../utils/constants.dart';
import '../providers/cart_provider.dart';
import 'checkout_screen.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('আমার কার্ট'),
        backgroundColor: AppConstants.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          if (cart.itemCount > 0)
            TextButton(
              onPressed: () {
                showDialog(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    title: const Text('কার্ট খালি করুন?'),
                    content: const Text('সব পণ্য কার্ট থেকে মুছে যাবে।'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('না')),
                      TextButton(
                        onPressed: () { cart.clearCart(); Navigator.pop(ctx); },
                        child: const Text('হ্যাঁ', style: TextStyle(color: AppConstants.dangerColor)),
                      ),
                    ],
                  ),
                );
              },
              child: const Text('সব মুছুন', style: TextStyle(color: Colors.white70, fontSize: 13)),
            ),
        ],
      ),
      body: cart.itemCount == 0
          ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.shopping_cart_outlined, size: 80, color: Colors.grey.shade300),
                  const SizedBox(height: 16),
                  const Text('আপনার কার্ট খালি!', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: AppConstants.textLightColor)),
                  const SizedBox(height: 8),
                  const Text('পণ্য যোগ করুন এবং অর্ডার দিন', style: TextStyle(color: AppConstants.textLightColor)),
                  const SizedBox(height: 24),
                  ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    style: ElevatedButton.styleFrom(backgroundColor: AppConstants.primaryColor, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                    child: const Text('শপিং করুন', style: TextStyle(color: Colors.white)),
                  ),
                ],
              ),
            )
          : Column(
              children: [
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: cart.items.length,
                    itemBuilder: (ctx, i) {
                      final item = cart.items[i];
                      return Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8, offset: const Offset(0, 2))],
                        ),
                        child: Row(
                          children: [
                            // Product Image
                            Container(
                              width: 68,
                              height: 68,
                              decoration: BoxDecoration(
                                color: const Color(0xFFf1f5f9),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: item.product.imageUrl != null
                                  ? ClipRRect(
                                      borderRadius: BorderRadius.circular(12),
                                      child: Image.network(item.product.imageUrl!, fit: BoxFit.cover,
                                        errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_rounded, color: Colors.grey)),
                                    )
                                  : const Icon(Icons.inventory_2_rounded, color: Color(0xFFcbd5e1), size: 32),
                            ),
                            const SizedBox(width: 12),

                            // Name + Price
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(item.product.name, maxLines: 2, overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppConstants.textColor)),
                                  const SizedBox(height: 4),
                                  Text('৳${item.product.salePrice.toInt()} × ${item.qty}',
                                    style: const TextStyle(fontSize: 13, color: AppConstants.textLightColor)),
                                  const SizedBox(height: 4),
                                  Text('৳${item.subtotal.toInt()}',
                                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppConstants.primaryColor)),
                                ],
                              ),
                            ),

                            // Qty controls
                            Column(
                              children: [
                                // Delete
                                InkWell(
                                  onTap: () => cart.removeFromCart(item.product.id),
                                  child: Container(
                                    padding: const EdgeInsets.all(4),
                                    child: const Icon(Icons.delete_outline_rounded, size: 20, color: AppConstants.dangerColor),
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Container(
                                  decoration: BoxDecoration(
                                    border: Border.all(color: Colors.grey.shade300),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      InkWell(
                                        onTap: () => cart.decrement(item.product.id),
                                        child: const Padding(padding: EdgeInsets.all(6), child: Icon(Icons.remove, size: 16)),
                                      ),
                                      Padding(
                                        padding: const EdgeInsets.symmetric(horizontal: 10),
                                        child: Text('${item.qty}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                      ),
                                      InkWell(
                                        onTap: () => cart.increment(item.product.id),
                                        child: const Padding(padding: EdgeInsets.all(6), child: Icon(Icons.add, size: 16)),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),

                // ---- Bottom Checkout Bar ----
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 12, offset: const Offset(0, -3))],
                  ),
                  child: SafeArea(
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('সর্বমোট (${cart.itemCount} পণ্য)', style: const TextStyle(fontSize: 15, color: AppConstants.textLightColor)),
                            Text('৳${cart.total.toInt()}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: AppConstants.primaryColor)),
                          ],
                        ),
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          height: 52,
                          child: ElevatedButton(
                            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CheckoutScreen())),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppConstants.primaryColor,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            ),
                            child: const Text('চেকআউট করুন', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: Colors.white)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}
