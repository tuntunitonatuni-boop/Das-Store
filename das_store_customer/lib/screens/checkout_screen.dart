import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../utils/constants.dart';
import '../providers/cart_provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import 'home_screen.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _addressController = TextEditingController();
  final _couponController = TextEditingController();
  bool _isPlacing = false;
  bool _isValidatingCoupon = false;

  // Coupon state
  String? _couponCode;
  double _couponDiscount = 0;
  String? _couponError;
  String? _couponSuccess;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    if (user?.address != null) _addressController.text = user!.address!;
  }

  @override
  void dispose() {
    _addressController.dispose();
    _couponController.dispose();
    super.dispose();
  }

  Future<void> _validateCoupon() async {
    final code = _couponController.text.trim().toUpperCase();
    if (code.isEmpty) return;

    setState(() {
      _isValidatingCoupon = true;
      _couponError = null;
      _couponSuccess = null;
      _couponDiscount = 0;
      _couponCode = null;
    });

    final cart = context.read<CartProvider>();
    final res = await ApiService.post('store.php?action=validate_coupon', {
      'code': code,
      'cart_total': cart.subtotal,
    });

    if (!mounted) return;
    setState(() {
      _isValidatingCoupon = false;
      if (res['success'] == true) {
        _couponCode = code;
        _couponDiscount = double.tryParse(res['discount']?.toString() ?? '0') ?? 0;
        _couponSuccess = '✅ ${res['message'] ?? 'কুপন প্রযোজ্য হয়েছে'}';
        _couponError = null;
      } else {
        _couponCode = null;
        _couponDiscount = 0;
        _couponError = res['message'] ?? 'অবৈধ কুপন';
        _couponSuccess = null;
      }
    });
  }

  void _removeCoupon() {
    setState(() {
      _couponCode = null;
      _couponDiscount = 0;
      _couponError = null;
      _couponSuccess = null;
      _couponController.clear();
    });
  }

  void _placeOrder() async {
    final cart = context.read<CartProvider>();
    if (cart.itemCount == 0) return;
    if (_addressController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('ডেলিভারি ঠিকানা দিন'),
          backgroundColor: AppConstants.dangerColor,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
      return;
    }

    setState(() => _isPlacing = true);
    final total = cart.subtotal - _couponDiscount;
    final res = await ApiService.post('store.php?action=checkout', {
      'items': cart.toOrderJson(),
      'total': total < 0 ? 0 : total,
      'address': _addressController.text.trim(),
      'coupon_code': _couponCode,
      'discount': _couponDiscount,
    });
    setState(() => _isPlacing = false);

    if (!mounted) return;

    if (res['success'] == true) {
      cart.clearCart();
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.check_circle_rounded, size: 72, color: AppConstants.primaryColor),
              const SizedBox(height: 16),
              const Text('অর্ডার সফল! 🎉', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              Text('Invoice: ${res['invoice_no'] ?? ''}', style: const TextStyle(color: AppConstants.textLightColor)),
              const SizedBox(height: 8),
              const Text('আপনার অর্ডার প্রসেস হচ্ছে', style: TextStyle(color: AppConstants.textLightColor)),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(ctx);
                    Navigator.pushAndRemoveUntil(
                      context,
                      MaterialPageRoute(builder: (_) => const HomeScreen()),
                      (_) => false,
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppConstants.primaryColor,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('হোমে যান', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                ),
              ),
            ],
          ),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'অর্ডার ব্যর্থ'),
          backgroundColor: AppConstants.dangerColor,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final user = context.watch<AuthProvider>().user;
    final finalTotal = (cart.subtotal - _couponDiscount).clamp(0, double.infinity);

    return Scaffold(
      appBar: AppBar(
        title: const Text('চেকআউট'),
        backgroundColor: AppConstants.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // User Info
            _SectionCard(
              child: Row(
                children: [
                  CircleAvatar(
                    backgroundColor: AppConstants.primaryColor.withOpacity(0.1),
                    child: const Icon(Icons.person, color: AppConstants.primaryColor),
                  ),
                  const SizedBox(width: 12),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user?.name ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                      Text(user?.phone ?? '', style: const TextStyle(color: AppConstants.textLightColor, fontSize: 13)),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),

            // Address
            _SectionCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('ডেলিভারি ঠিকানা', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _addressController,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      hintText: 'আপনার সম্পূর্ণ ঠিকানা লিখুন',
                      prefixIcon: Padding(
                        padding: EdgeInsets.only(bottom: 40),
                        child: Icon(Icons.location_on_outlined),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),

            // Coupon Section
            _SectionCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.local_offer_rounded, color: Colors.purple, size: 20),
                      SizedBox(width: 8),
                      Text('কুপন কোড', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  if (_couponCode != null)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(
                        color: Colors.green.shade50,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.green.shade300),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.check_circle_rounded, color: Colors.green, size: 20),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(_couponCode!, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, letterSpacing: 1)),
                                Text('৳${_couponDiscount.toInt()} ছাড় পেয়েছেন', style: const TextStyle(color: Colors.green, fontSize: 12)),
                              ],
                            ),
                          ),
                          TextButton(
                            onPressed: _removeCoupon,
                            child: const Text('Remove', style: TextStyle(color: AppConstants.dangerColor, fontSize: 12)),
                          ),
                        ],
                      ),
                    )
                  else
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _couponController,
                            textCapitalization: TextCapitalization.characters,
                            decoration: InputDecoration(
                              hintText: 'কুপন কোড দিন',
                              prefixIcon: const Icon(Icons.confirmation_number_outlined),
                              errorText: _couponError,
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        ElevatedButton(
                          onPressed: _isValidatingCoupon ? null : _validateCoupon,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.purple,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                          ),
                          child: _isValidatingCoupon
                              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                              : const Text('Apply'),
                        ),
                      ],
                    ),
                ],
              ),
            ),
            const SizedBox(height: 12),

            // Order Summary
            _SectionCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('অর্ডার সারাংশ', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  const SizedBox(height: 12),
                  ...cart.items.map((item) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${item.product.name} × ${item.qty}',
                            style: const TextStyle(fontSize: 13, color: AppConstants.textLightColor),
                          ),
                        ),
                        Text('৳${item.subtotal.toInt()}', style: const TextStyle(fontWeight: FontWeight.w600)),
                      ],
                    ),
                  )),
                  const Divider(),
                  _PriceRow(label: 'সাবটোটাল', value: '৳${cart.subtotal.toInt()}'),
                  if (_couponDiscount > 0) ...[
                    const SizedBox(height: 6),
                    _PriceRow(label: 'কুপন ছাড়', value: '- ৳${_couponDiscount.toInt()}', valueColor: Colors.green),
                  ],
                  const Divider(),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('সর্বমোট', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                      Text(
                        '৳${finalTotal.toInt()}',
                        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: AppConstants.primaryColor),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),

            // Payment Method
            _SectionCard(
              child: Row(
                children: [
                  Icon(Icons.money_rounded, color: AppConstants.primaryColor),
                  const SizedBox(width: 12),
                  const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('পেমেন্ট মেথড', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                      Text('ক্যাশ অন ডেলিভারি', style: TextStyle(color: AppConstants.textLightColor, fontSize: 13)),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Place Order Button
            SizedBox(
              width: double.infinity,
              height: 56,
              child: ElevatedButton(
                onPressed: _isPlacing ? null : _placeOrder,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppConstants.primaryColor,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _isPlacing
                    ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                    : const Text('অর্ডার দিন', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Colors.white)),
              ),
            ),
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final Widget child;
  const _SectionCard({required this.child});
  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)],
      ),
      child: child,
    );
  }
}

class _PriceRow extends StatelessWidget {
  final String label, value;
  final Color? valueColor;
  const _PriceRow({required this.label, required this.value, this.valueColor});
  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(fontSize: 14, color: AppConstants.textLightColor)),
        Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: valueColor)),
      ],
    );
  }
}
