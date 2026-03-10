import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'cart_screen.dart';
import 'orders_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({Key? key}) : super(key: key);

  @override
  _HomeScreenState createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int? _selectedCategory;
  List _products = [];
  List _categories = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  void _loadData() async {
    final api = Provider.of<ApiService>(context, listen: false);
    final cats = await api.getCategories();
    final prods = await api.getProducts(catId: _selectedCategory);
    
    setState(() {
      _categories = cats;
      _products = prods;
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final api = Provider.of<ApiService>(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('🛒 Das Store', style: TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(icon: const Icon(Icons.search), onPressed: () {}),
          IconButton(icon: const Icon(Icons.notifications_none), onPressed: () {}),
        ],
      ),
      drawer: Drawer(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            UserAccountsDrawerHeader(
              accountName: Text(api.user?['name'] ?? 'User'),
              accountEmail: Text('@${api.user?['username'] ?? 'guest'}'),
              currentAccountPicture: CircleAvatar(
                backgroundColor: Colors.white,
                child: Text(
                  api.user?['name']?[0]?.toUpperCase() ?? 'U',
                  style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                ),
              ),
              decoration: const BoxDecoration(color: Colors.green),
            ),
            ListTile(
              leading: const Icon(Icons.list_alt),
              title: const Text('My Orders'),
              onTap: () {
                Navigator.pop(context);
                Navigator.push(context, MaterialPageRoute(builder: (_) => const OrdersScreen()));
              },
            ),
            ListTile(
              leading: const Icon(Icons.person_outline),
              title: const Text('Account Details'),
              onTap: () {},
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.exit_to_app, color: Colors.red),
              title: const Text('Logout', style: TextStyle(color: Colors.red)),
              onTap: () {
                api.logout();
                Navigator.of(context).pushReplacementNamed('/');
              },
            ),
          ],
        ),
      ),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator())
        : Column(
            children: [
              // Category Bar
              SizedBox(
                height: 100,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  itemCount: _categories.length + 1,
                  itemBuilder: (ctx, i) {
                    if (i == 0) {
                      return _buildCategoryItem('All', null);
                    }
                    final cat = _categories[i-1];
                    return _buildCategoryItem(cat['name'], cat['id']);
                  },
                ),
              ),
              
              // Product Grid
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () async => _loadData(),
                  child: GridView.builder(
                    padding: const EdgeInsets.all(16),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      childAspectRatio: 0.75,
                      crossAxisSpacing: 16,
                      mainAxisSpacing: 16,
                    ),
                    itemCount: _products.length,
                    itemBuilder: (ctx, i) {
                      final p = _products[i];
                      return _buildProductCard(p);
                    },
                  ),
                ),
              ),
            ],
          ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          Navigator.push(context, MaterialPageRoute(builder: (_) => const CartScreen()));
        },
        label: Text('Cart (${api.cartCount})'),
        icon: const Icon(Icons.shopping_basket),
        backgroundColor: Colors.green.shade600,
      ),
    );
  }

  Widget _buildCategoryItem(String name, int? id) {
    bool isSelected = _selectedCategory == id;
    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedCategory = id;
          _isLoading = true;
        });
        _loadData();
      },
      child: Container(
        margin: const EdgeInsets.only(right: 12),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        decoration: BoxDecoration(
          color: isSelected ? Colors.green.shade100 : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: isSelected ? Colors.green.shade400 : Colors.transparent),
        ),
        child: Center(
          child: Text(
            name,
            style: TextStyle(
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
              color: isSelected ? Colors.green.shade900 : Colors.grey.shade800,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildProductCard(Map p) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: Colors.grey.shade200),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Stack(
              children: [
                Center(
                  child: p['image_url'] != null
                    ? CachedNetworkImage(
                        imageUrl: p['image_url'],
                        fit: BoxFit.cover,
                        width: double.infinity,
                        height: double.infinity,
                        placeholder: (ctx, url) => Container(color: Colors.grey.shade100),
                        errorWidget: (ctx, url, err) => const Icon(Icons.image_not_supported),
                      )
                    : const Icon(Icons.shopping_bag_outlined, size: 50, color: Colors.grey),
                ),
                if (p['stock'] <= 0)
                  Container(
                    color: Colors.black.withOpacity(0.5),
                    child: const Center(child: Text('OUT OF STOCK', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold))),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  p['name'],
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                ),
                const SizedBox(height: 4),
                Text(
                  '${p['sale_price']} / ${p['unit']}',
                  style: TextStyle(color: Colors.green.shade700, fontWeight: FontWeight.w900, fontSize: 15),
                ),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: p['stock'] <= 0 ? null : () {
                      Provider.of<ApiService>(context, listen: false).addToCart(p);
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('${p['name']} added to cart!'), duration: const Duration(seconds: 1)),
                      );
                    },
                    style: OutlinedButton.styleFrom(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      padding: const EdgeInsets.symmetric(vertical: 0),
                    ),
                    child: const Text('Add 🛒', style: TextStyle(fontSize: 12)),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
