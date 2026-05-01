import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../utils/constants.dart';
import '../models/order_model.dart';
import '../services/api_service.dart';
import '../providers/auth_provider.dart';
import 'order_detail_screen.dart';

class MyOrdersScreen extends StatefulWidget {
  const MyOrdersScreen({super.key});
  @override
  State<MyOrdersScreen> createState() => _MyOrdersScreenState();
}

class _MyOrdersScreenState extends State<MyOrdersScreen> {
  List<OrderModel> _orders = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadOrders();
  }

  Future<void> _loadOrders() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('store.php', params: {'action': 'my_orders'});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) {
          _orders = (res['orders'] as List).map((o) => OrderModel.fromJson(o)).toList();
        }
      });
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'pending': return Colors.orange;
      case 'confirmed': return Colors.blue;
      case 'packaging': return Colors.purple;
      case 'delivered': return AppConstants.primaryColor;
      case 'cancelled': return AppConstants.dangerColor;
      default: return Colors.grey;
    }
  }

  IconData _statusIcon(String status) {
    switch (status) {
      case 'pending': return Icons.schedule_rounded;
      case 'confirmed': return Icons.thumb_up_rounded;
      case 'packaging': return Icons.inventory_rounded;
      case 'delivered': return Icons.check_circle_rounded;
      case 'cancelled': return Icons.cancel_rounded;
      default: return Icons.info_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('আমার অর্ডার'),
        backgroundColor: AppConstants.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppConstants.primaryColor))
          : _orders.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.receipt_long_rounded, size: 80, color: Colors.grey.shade300),
                      const SizedBox(height: 16),
                      const Text('কোন অর্ডার নেই', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: AppConstants.textLightColor)),
                      const SizedBox(height: 8),
                      const Text('পণ্য অর্ডার করুন!', style: TextStyle(color: AppConstants.textLightColor)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadOrders,
                  color: AppConstants.primaryColor,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _orders.length,
                    itemBuilder: (ctx, i) {
                      final order = _orders[i];
                      final color = _statusColor(order.status);
                      return GestureDetector(
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => CustomerOrderDetailScreen(
                              orderId: order.id,
                              invoiceNo: order.invoiceNo,
                            ),
                          ),
                        ),
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8, offset: const Offset(0, 2))],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Icon(_statusIcon(order.status), color: color, size: 20),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      order.invoiceNo,
                                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: color.withOpacity(0.1),
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    child: Text(
                                      order.statusLabel,
                                      style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    order.createdAt?.substring(0, 10) ?? '',
                                    style: const TextStyle(fontSize: 12, color: AppConstants.textLightColor),
                                  ),
                                  Text(
                                    '৳${order.total.toInt()}',
                                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppConstants.primaryColor),
                                  ),
                                ],
                              ),
                              if (order.shippingAddress != null && order.shippingAddress!.isNotEmpty) ...[
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    const Icon(Icons.location_on_outlined, size: 14, color: AppConstants.textLightColor),
                                    const SizedBox(width: 4),
                                    Expanded(
                                      child: Text(
                                        order.shippingAddress!,
                                        style: const TextStyle(fontSize: 12, color: AppConstants.textLightColor),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  Text(
                                    'বিস্তারিত দেখুন →',
                                    style: TextStyle(fontSize: 12, color: AppConstants.primaryColor, fontWeight: FontWeight.w600),
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
