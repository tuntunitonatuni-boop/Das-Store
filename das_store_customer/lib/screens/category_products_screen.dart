import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../models/product_model.dart';
import '../services/api_service.dart';
import '../providers/cart_provider.dart';
import 'package:provider/provider.dart';
import 'product_detail_screen.dart';
import 'package:cached_network_image/cached_network_image.dart';

class CategoryProductsScreen extends StatefulWidget {
  final int categoryId;
  final String categoryName;

  const CategoryProductsScreen({super.key, required this.categoryId, required this.categoryName});

  @override
  State<CategoryProductsScreen> createState() => _CategoryProductsScreenState();
}

class _CategoryProductsScreenState extends State<CategoryProductsScreen> {
  List<ProductModel> _products = [];
  bool _isLoading = true;
  int _page = 1;
  bool _hasMore = true;

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  Future<void> _loadProducts({bool loadMore = false}) async {
    if (loadMore) _page++;
    if (!loadMore) {
      _page = 1;
      setState(() => _isLoading = true);
    }

    final res = await ApiService.get('store.php', params: {
      'action': 'products',
      'cat_id': widget.categoryId.toString(),
      'page': _page.toString(),
    });

    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) {
          final newProducts = (res['products'] as List).map((p) => ProductModel.fromJson(p)).toList();
          if (loadMore) {
            _products.addAll(newProducts);
          } else {
            _products = newProducts;
          }
          _hasMore = newProducts.length >= 20;
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.categoryName),
        backgroundColor: AppConstants.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppConstants.primaryColor))
          : _products.isEmpty
              ? const Center(child: Text('এই ক্যাটাগরিতে কোন পণ্য নেই', style: TextStyle(fontSize: 16, color: AppConstants.textLightColor)))
              : RefreshIndicator(
                  onRefresh: () => _loadProducts(),
                  color: AppConstants.primaryColor,
                  child: NotificationListener<ScrollNotification>(
                    onNotification: (scroll) {
                      if (scroll is ScrollEndNotification && scroll.metrics.extentAfter < 100 && _hasMore && !_isLoading) {
                        _loadProducts(loadMore: true);
                      }
                      return false;
                    },
                    child: GridView.builder(
                      padding: const EdgeInsets.all(16),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        childAspectRatio: 0.68,
                      ),
                      itemCount: _products.length,
                      itemBuilder: (ctx, i) => _CatProductCard(product: _products[i]),
                    ),
                  ),
                ),
    );
  }
}

class _CatProductCard extends StatelessWidget {
  final ProductModel product;
  const _CatProductCard({required this.product});

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final inCart = cart.isInCart(product.id);

    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProductDetailScreen(productId: product.id))),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, 3))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              flex: 5,
              child: Stack(
                children: [
                  Container(
                    width: double.infinity,
                    decoration: const BoxDecoration(
                      color: Color(0xFFf1f5f9),
                      borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
                    ),
                    child: product.imageUrl != null
                        ? ClipRRect(
                            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                            child: CachedNetworkImage(imageUrl: product.imageUrl!, fit: BoxFit.cover,
                              placeholder: (_, __) => const Center(child: Icon(Icons.image, size: 40, color: Colors.grey)),
                              errorWidget: (_, __, ___) => const Center(child: Icon(Icons.inventory_2_rounded, size: 40, color: Colors.grey)),
                            ),
                          )
                        : const Center(child: Icon(Icons.inventory_2_rounded, size: 44, color: Color(0xFFcbd5e1))),
                  ),
                  if (product.hasDiscount)
                    Positioned(
                      top: 8, left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(color: AppConstants.dangerColor, borderRadius: BorderRadius.circular(8)),
                        child: Text('-${product.discountPercent}%', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                      ),
                    ),
                ],
              ),
            ),
            Expanded(
              flex: 4,
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(product.name, maxLines: 2, overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppConstants.textColor)),
                    const Spacer(),
                    Row(
                      children: [
                        Text('৳${product.salePrice.toInt()}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppConstants.primaryColor)),
                        if (product.hasDiscount) ...[
                          const SizedBox(width: 6),
                          Text('৳${product.mrp.toInt()}', style: const TextStyle(fontSize: 12, decoration: TextDecoration.lineThrough, color: Colors.grey)),
                        ],
                      ],
                    ),
                    const SizedBox(height: 6),
                    SizedBox(
                      width: double.infinity, height: 32,
                      child: ElevatedButton(
                        onPressed: product.inStock ? () => cart.addToCart(product) : null,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: inCart ? Colors.grey.shade200 : AppConstants.primaryColor,
                          foregroundColor: inCart ? AppConstants.textColor : Colors.white,
                          padding: EdgeInsets.zero,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        child: Text(inCart ? 'যোগ করা হয়েছে ✓' : 'কার্টে যোগ করুন', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
