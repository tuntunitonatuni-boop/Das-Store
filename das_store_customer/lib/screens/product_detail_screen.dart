import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../utils/constants.dart';
import '../models/product_model.dart';
import '../services/api_service.dart';
import '../providers/cart_provider.dart';

class ProductDetailScreen extends StatefulWidget {
  final int productId;
  const ProductDetailScreen({super.key, required this.productId});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  ProductModel? _product;
  bool _isLoading = true;
  int _qty = 1;

  @override
  void initState() {
    super.initState();
    _loadProduct();
  }

  Future<void> _loadProduct() async {
    final res = await ApiService.get('store.php', params: {'action': 'product_detail', 'id': widget.productId.toString()});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) {
          _product = ProductModel.fromJson(res['product']);
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();

    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(backgroundColor: Colors.transparent, elevation: 0, foregroundColor: AppConstants.textColor),
        body: const Center(child: CircularProgressIndicator(color: AppConstants.primaryColor)),
      );
    }

    if (_product == null) {
      return Scaffold(
        appBar: AppBar(backgroundColor: Colors.transparent, elevation: 0, foregroundColor: AppConstants.textColor),
        body: const Center(child: Text('পণ্য পাওয়া যায়নি', style: TextStyle(fontSize: 18, color: AppConstants.textLightColor))),
      );
    }

    final p = _product!;
    final inCart = cart.isInCart(p.id);

    return Scaffold(
      backgroundColor: Colors.white,
      body: CustomScrollView(
        slivers: [
          // ---- Image Header ----
          SliverAppBar(
            expandedHeight: 300,
            pinned: true,
            backgroundColor: Colors.white,
            foregroundColor: AppConstants.textColor,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                color: const Color(0xFFf1f5f9),
                child: p.imageUrl != null
                    ? CachedNetworkImage(
                        imageUrl: p.imageUrl!,
                        fit: BoxFit.contain,
                        placeholder: (_, __) => const Center(child: CircularProgressIndicator(color: AppConstants.primaryColor)),
                        errorWidget: (_, __, ___) => const Center(child: Icon(Icons.inventory_2_rounded, size: 80, color: Color(0xFFcbd5e1))),
                      )
                    : const Center(child: Icon(Icons.inventory_2_rounded, size: 100, color: Color(0xFFcbd5e1))),
              ),
            ),
            actions: [
              if (p.hasDiscount)
                Container(
                  margin: const EdgeInsets.only(right: 12),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(color: AppConstants.dangerColor, borderRadius: BorderRadius.circular(20)),
                  child: Text('-${p.discountPercent}% ছাড়', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                ),
            ],
          ),

          // ---- Product Info ----
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Category badge
                  if (p.categoryName != null)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                      decoration: BoxDecoration(
                        color: AppConstants.primaryColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(p.categoryName!, style: const TextStyle(color: AppConstants.primaryColor, fontSize: 12, fontWeight: FontWeight.w600)),
                    ),
                  const SizedBox(height: 12),

                  // Name
                  Text(p.name, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: AppConstants.textColor, height: 1.3)),
                  const SizedBox(height: 8),

                  // Company
                  if (p.companyName != null)
                    Text(p.companyName!, style: const TextStyle(fontSize: 14, color: AppConstants.textLightColor)),

                  const SizedBox(height: 16),

                  // Price
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text('৳${p.salePrice.toInt()}', style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w900, color: AppConstants.primaryColor)),
                      if (p.hasDiscount) ...[
                        const SizedBox(width: 12),
                        Padding(
                          padding: const EdgeInsets.only(bottom: 4),
                          child: Text('৳${p.mrp.toInt()}', style: const TextStyle(fontSize: 18, decoration: TextDecoration.lineThrough, color: Colors.grey)),
                        ),
                      ],
                      const Spacer(),
                      Text('/${p.unit}', style: const TextStyle(fontSize: 14, color: AppConstants.textLightColor)),
                    ],
                  ),

                  const SizedBox(height: 16),

                  // Stock status
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: p.inStock ? const Color(0xFFf0fdf4) : const Color(0xFFfef2f2),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: p.inStock ? const Color(0xFF86efac) : const Color(0xFFfecaca)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(p.inStock ? Icons.check_circle_rounded : Icons.cancel_rounded,
                            size: 18, color: p.inStock ? AppConstants.primaryColor : AppConstants.dangerColor),
                        const SizedBox(width: 8),
                        Text(
                          p.inStock ? 'স্টকে আছে' : 'স্টক নেই',
                          style: TextStyle(fontWeight: FontWeight.w600, color: p.inStock ? AppConstants.primaryColor : AppConstants.dangerColor),
                        ),
                      ],
                    ),
                  ),

                  // Description
                  if (p.description != null && p.description!.isNotEmpty) ...[
                    const SizedBox(height: 24),
                    const Text('বিবরণ', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppConstants.textColor)),
                    const SizedBox(height: 8),
                    Text(p.description!, style: const TextStyle(fontSize: 14, color: AppConstants.textLightColor, height: 1.6)),
                  ],

                  const SizedBox(height: 120),
                ],
              ),
            ),
          ),
        ],
      ),

      // ---- Bottom Bar ----
      bottomNavigationBar: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 12, offset: const Offset(0, -3))],
        ),
        child: SafeArea(
          child: Row(
            children: [
              // Quantity Selector
              Container(
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.grey.shade300),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    InkWell(
                      onTap: () { if (_qty > 1) setState(() => _qty--); },
                      child: Container(
                        padding: const EdgeInsets.all(10),
                        child: const Icon(Icons.remove, size: 20),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Text('$_qty', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                    ),
                    InkWell(
                      onTap: () => setState(() => _qty++),
                      child: Container(
                        padding: const EdgeInsets.all(10),
                        child: const Icon(Icons.add, size: 20),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 16),
              // Add to Cart Button
              Expanded(
                child: SizedBox(
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: p.inStock
                        ? () {
                            cart.addToCart(p, qty: _qty);
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('${p.name} কার্টে যোগ হয়েছে!'),
                                backgroundColor: AppConstants.primaryColor,
                                behavior: SnackBarBehavior.floating,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                duration: const Duration(seconds: 2),
                              ),
                            );
                          }
                        : null,
                    icon: Icon(inCart ? Icons.check_rounded : Icons.shopping_cart_rounded, color: Colors.white),
                    label: Text(
                      inCart ? 'আরও যোগ করুন' : 'কার্টে যোগ করুন',
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppConstants.primaryColor,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
