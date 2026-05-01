import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../utils/constants.dart';
import '../models/category_model.dart';
import '../models/product_model.dart';
import '../services/api_service.dart';
import '../providers/cart_provider.dart';
import '../providers/auth_provider.dart';
import 'category_products_screen.dart';
import 'product_detail_screen.dart';
import 'cart_screen.dart';
import 'my_orders_screen.dart';
import 'profile_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;

  final List<Widget> _pages = const [
    _HomeBody(),
    _SearchBody(),
    _OrdersTab(),
    _ProfileTab(),
  ];

  @override
  Widget build(BuildContext context) {
    final cartCount = context.watch<CartProvider>().totalQty;

    return Scaffold(
      body: IndexedStack(index: _currentIndex, children: _pages),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 12, offset: const Offset(0, -2))],
        ),
        child: NavigationBar(
          selectedIndex: _currentIndex,
          onDestinationSelected: (i) => setState(() => _currentIndex = i),
          backgroundColor: Colors.white,
          elevation: 0,
          indicatorColor: AppConstants.primaryColor.withOpacity(0.12),
          destinations: [
            const NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home_rounded, color: AppConstants.primaryColor), label: 'হোম'),
            const NavigationDestination(icon: Icon(Icons.search_outlined), selectedIcon: Icon(Icons.search_rounded, color: AppConstants.primaryColor), label: 'খুঁজুন'),
            NavigationDestination(
              icon: Badge(
                isLabelVisible: cartCount > 0,
                label: Text('$cartCount', style: const TextStyle(fontSize: 10)),
                child: const Icon(Icons.shopping_bag_outlined),
              ),
              selectedIcon: Badge(
                isLabelVisible: cartCount > 0,
                label: Text('$cartCount', style: const TextStyle(fontSize: 10)),
                child: const Icon(Icons.shopping_bag_rounded, color: AppConstants.primaryColor),
              ),
              label: 'অর্ডার',
            ),
            const NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person_rounded, color: AppConstants.primaryColor), label: 'প্রোফাইল'),
          ],
        ),
      ),
      floatingActionButton: cartCount > 0
          ? FloatingActionButton.extended(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CartScreen())),
              backgroundColor: AppConstants.primaryColor,
              icon: const Icon(Icons.shopping_cart_rounded, color: Colors.white),
              label: Text('কার্ট ($cartCount)', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
            )
          : null,
    );
  }
}

// ======================== HOME BODY ========================
class _HomeBody extends StatefulWidget {
  const _HomeBody();
  @override
  State<_HomeBody> createState() => _HomeBodyState();
}

class _HomeBodyState extends State<_HomeBody> {
  List<CategoryModel> _categories = [];
  List<ProductModel> _products = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final catRes = await ApiService.get('store.php', params: {'action': 'categories'});
    final prodRes = await ApiService.get('store.php', params: {'action': 'products'});

    if (mounted) {
      setState(() {
        if (catRes['success'] == true) {
          _categories = (catRes['categories'] as List).map((c) => CategoryModel.fromJson(c)).toList();
        }
        if (prodRes['success'] == true) {
          _products = (prodRes['products'] as List).map((p) => ProductModel.fromJson(p)).toList();
        }
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;

    return SafeArea(
      child: RefreshIndicator(
        onRefresh: _loadData,
        color: AppConstants.primaryColor,
        child: CustomScrollView(
          slivers: [
            // ---- Header ----
            SliverToBoxAdapter(
              child: Container(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                decoration: const BoxDecoration(
                  gradient: LinearGradient(colors: [Color(0xFF16a34a), Color(0xFF059669)]),
                  borderRadius: BorderRadius.only(bottomLeft: Radius.circular(28), bottomRight: Radius.circular(28)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const CircleAvatar(
                          backgroundColor: Colors.white24,
                          radius: 22,
                          child: Icon(Icons.storefront_rounded, color: Colors.white, size: 24),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('স্বাগতম, ${user?.name ?? 'Customer'}! 👋',
                                style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
                              const SizedBox(height: 2),
                              Text('Das Store', style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13)),
                            ],
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CartScreen())),
                          icon: Badge(
                            isLabelVisible: context.watch<CartProvider>().totalQty > 0,
                            label: Text('${context.watch<CartProvider>().totalQty}', style: const TextStyle(fontSize: 10)),
                            child: const Icon(Icons.shopping_cart_outlined, color: Colors.white, size: 26),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            // ---- Categories Header ----
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.fromLTRB(20, 24, 20, 12),
                child: Text('ক্যাটাগরি', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppConstants.textColor)),
              ),
            ),

            // ---- Categories Grid ----
            if (_isLoading)
              const SliverToBoxAdapter(child: Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator(color: AppConstants.primaryColor))))
            else if (_categories.isEmpty)
              const SliverToBoxAdapter(child: Center(child: Padding(padding: EdgeInsets.all(20), child: Text('কোন ক্যাটাগরি পাওয়া যায়নি'))))
            else
              SliverToBoxAdapter(
                child: SizedBox(
                  height: 110,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: _categories.length,
                    itemBuilder: (ctx, i) {
                      final cat = _categories[i];
                      final icons = [
                        Icons.rice_bowl_rounded, Icons.local_drink_rounded, Icons.bakery_dining_rounded,
                        Icons.egg_rounded, Icons.kitchen_rounded, Icons.spa_rounded,
                        Icons.cleaning_services_rounded, Icons.cookie_rounded,
                      ];
                      return GestureDetector(
                        onTap: () => Navigator.push(context, MaterialPageRoute(
                          builder: (_) => CategoryProductsScreen(categoryId: cat.id, categoryName: cat.name),
                        )),
                        child: Container(
                          width: 85,
                          margin: const EdgeInsets.only(right: 12),
                          child: Column(
                            children: [
                              Container(
                                width: 62,
                                height: 62,
                                decoration: BoxDecoration(
                                  gradient: LinearGradient(
                                    colors: [AppConstants.primaryColor.withOpacity(0.1), AppConstants.primaryColor.withOpacity(0.05)],
                                  ),
                                  borderRadius: BorderRadius.circular(18),
                                  border: Border.all(color: AppConstants.primaryColor.withOpacity(0.15)),
                                ),
                                child: Icon(icons[i % icons.length], color: AppConstants.primaryColor, size: 28),
                              ),
                              const SizedBox(height: 8),
                              Text(cat.name, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppConstants.textColor)),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),

            // ---- Products Header ----
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.fromLTRB(20, 16, 20, 12),
                child: Text('সকল পণ্য', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppConstants.textColor)),
              ),
            ),

            // ---- Products Grid ----
            if (!_isLoading && _products.isNotEmpty)
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 0.68,
                  ),
                  delegate: SliverChildBuilderDelegate(
                    (ctx, i) => _ProductCard(product: _products[i]),
                    childCount: _products.length,
                  ),
                ),
              ),

            const SliverToBoxAdapter(child: SizedBox(height: 100)),
          ],
        ),
      ),
    );
  }
}

// ======================== PRODUCT CARD ========================
class _ProductCard extends StatelessWidget {
  final ProductModel product;
  const _ProductCard({required this.product});

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
            // Image
            Expanded(
              flex: 5,
              child: Stack(
                children: [
                  Container(
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: const Color(0xFFf1f5f9),
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                    ),
                    child: product.imageUrl != null
                        ? ClipRRect(
                            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                            child: CachedNetworkImage(
                              imageUrl: product.imageUrl!,
                              fit: BoxFit.cover,
                              placeholder: (_, __) => const Center(child: Icon(Icons.image_rounded, size: 40, color: Colors.grey)),
                              errorWidget: (_, __, ___) => const Center(child: Icon(Icons.inventory_2_rounded, size: 40, color: Colors.grey)),
                            ),
                          )
                        : const Center(child: Icon(Icons.inventory_2_rounded, size: 44, color: Color(0xFFcbd5e1))),
                  ),
                  if (product.hasDiscount)
                    Positioned(
                      top: 8,
                      left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: AppConstants.dangerColor,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text('-${product.discountPercent}%', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                      ),
                    ),
                  if (!product.inStock)
                    Positioned.fill(
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.black45,
                          borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                        ),
                        child: const Center(child: Text('স্টক নেই', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold))),
                      ),
                    ),
                ],
              ),
            ),

            // Info
            Expanded(
              flex: 4,
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppConstants.textColor, height: 1.3),
                    ),
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
                      width: double.infinity,
                      height: 32,
                      child: inCart
                          ? Row(
                              children: [
                                _QtyBtn(icon: Icons.remove, onTap: () => cart.decrement(product.id)),
                                Expanded(child: Center(child: Text('${cart.getQty(product.id)}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)))),
                                _QtyBtn(icon: Icons.add, onTap: () => cart.increment(product.id)),
                              ],
                            )
                          : ElevatedButton(
                              onPressed: product.inStock ? () => cart.addToCart(product) : null,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppConstants.primaryColor,
                                padding: EdgeInsets.zero,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                textStyle: const TextStyle(fontSize: 12),
                              ),
                              child: const Text('কার্টে যোগ করুন', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
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

class _QtyBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _QtyBtn({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 32,
        height: 32,
        decoration: BoxDecoration(
          color: AppConstants.primaryColor.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(icon, size: 18, color: AppConstants.primaryColor),
      ),
    );
  }
}

// ======================== SEARCH BODY ========================
class _SearchBody extends StatefulWidget {
  const _SearchBody();
  @override
  State<_SearchBody> createState() => _SearchBodyState();
}

class _SearchBodyState extends State<_SearchBody> {
  final _searchController = TextEditingController();
  List<ProductModel> _results = [];
  bool _isSearching = false;

  void _search(String q) async {
    if (q.trim().length < 2) {
      setState(() => _results = []);
      return;
    }
    setState(() => _isSearching = true);
    final res = await ApiService.get('store.php', params: {'action': 'products', 'q': q.trim()});
    if (mounted) {
      setState(() {
        _isSearching = false;
        if (res['success'] == true) {
          _results = (res['products'] as List).map((p) => ProductModel.fromJson(p)).toList();
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchController,
              onChanged: _search,
              decoration: InputDecoration(
                hintText: 'পণ্য খুঁজুন...',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(icon: const Icon(Icons.close), onPressed: () { _searchController.clear(); _search(''); })
                    : null,
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: Colors.grey.shade200)),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppConstants.primaryColor, width: 2)),
              ),
            ),
          ),
          if (_isSearching)
            const Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator(color: AppConstants.primaryColor))
          else if (_results.isEmpty && _searchController.text.length >= 2)
            const Expanded(child: Center(child: Text('কোন পণ্য পাওয়া যায়নি', style: TextStyle(color: AppConstants.textLightColor, fontSize: 16))))
          else
            Expanded(
              child: GridView.builder(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 0.68),
                itemCount: _results.length,
                itemBuilder: (ctx, i) => _ProductCard(product: _results[i]),
              ),
            ),
        ],
      ),
    );
  }
}

// ======================== ORDERS TAB (wrapper) ========================
class _OrdersTab extends StatelessWidget {
  const _OrdersTab();

  @override
  Widget build(BuildContext context) {
    return const MyOrdersScreen();
  }
}

// ======================== PROFILE TAB (wrapper) ========================
class _ProfileTab extends StatelessWidget {
  const _ProfileTab();

  @override
  Widget build(BuildContext context) {
    return const ProfileScreen();
  }
}
