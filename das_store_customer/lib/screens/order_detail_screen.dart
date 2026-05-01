import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class CustomerOrderDetailScreen extends StatefulWidget {
  final int orderId;
  final String invoiceNo;
  const CustomerOrderDetailScreen({super.key, required this.orderId, required this.invoiceNo});
  @override
  State<CustomerOrderDetailScreen> createState() => _CustomerOrderDetailScreenState();
}

class _CustomerOrderDetailScreenState extends State<CustomerOrderDetailScreen> {
  Map<String, dynamic>? _order;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('store.php', params: {'action': 'order_detail', 'id': widget.orderId.toString()});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) _order = res['order'];
      });
    }
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'pending': return Colors.orange;
      case 'confirmed': return Colors.blue;
      case 'packaging': return Colors.purple;
      case 'delivered': return AppConstants.primaryColor;
      case 'cancelled': return AppConstants.dangerColor;
      default: return Colors.grey;
    }
  }

  String _statusBn(String s) {
    switch (s) {
      case 'pending': return '⏳ অপেক্ষায় আছে';
      case 'confirmed': return '✅ কনফার্মড';
      case 'packaging': return '📦 প্যাকেজিং চলছে';
      case 'delivered': return '🎉 ডেলিভারড';
      case 'cancelled': return '❌ বাতিল';
      default: return s;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.invoiceNo),
        backgroundColor: AppConstants.primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppConstants.primaryColor))
          : _order == null
              ? const Center(child: Text('অর্ডার পাওয়া যায়নি'))
              : RefreshIndicator(
                  onRefresh: _load,
                  color: AppConstants.primaryColor,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Status Card
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(20),
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: [_statusColor(_order!['status']).withOpacity(0.15), _statusColor(_order!['status']).withOpacity(0.05)],
                            ),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: _statusColor(_order!['status']).withOpacity(0.3)),
                          ),
                          child: Column(
                            children: [
                              Text(
                                _statusBn(_order!['status'] ?? 'pending'),
                                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: _statusColor(_order!['status'] ?? 'pending')),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                _order!['invoice_no'] ?? '',
                                style: const TextStyle(fontSize: 14, color: AppConstants.textLightColor, fontWeight: FontWeight.w600),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _order!['created_at']?.toString().substring(0, 16) ?? '',
                                style: const TextStyle(fontSize: 12, color: AppConstants.textLightColor),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Delivery Address
                        if (_order!['shipping_address'] != null && (_order!['shipping_address'] as String).isNotEmpty)
                          _InfoCard(
                            title: 'ডেলিভারি ঠিকানা',
                            icon: Icons.location_on_rounded,
                            child: Text(_order!['shipping_address'], style: const TextStyle(fontSize: 14, color: AppConstants.textColor)),
                          ),
                        const SizedBox(height: 14),

                        // Order Items
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Row(
                                children: [
                                  Icon(Icons.shopping_bag_outlined, color: AppConstants.primaryColor, size: 20),
                                  SizedBox(width: 8),
                                  Text('অর্ডার আইটেম', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                                ],
                              ),
                              const SizedBox(height: 14),
                              ...(_order!['items'] as List? ?? []).map((item) => Container(
                                margin: const EdgeInsets.only(bottom: 10),
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFf8fafc),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(item['product_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                                          const SizedBox(height: 3),
                                          Text(
                                            '৳${double.tryParse(item['unit_price']?.toString() ?? '0')?.toInt() ?? 0} × ${item['qty']}',
                                            style: const TextStyle(fontSize: 12, color: AppConstants.textLightColor),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Text(
                                      '৳${double.tryParse(item['subtotal']?.toString() ?? '0')?.toInt() ?? 0}',
                                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: AppConstants.primaryColor),
                                    ),
                                  ],
                                ),
                              )),
                              const Divider(height: 20),
                              if ((_order!['discount'] ?? 0) != 0 && double.tryParse(_order!['discount'].toString())! > 0) ...[
                                _TotalRow(label: 'সাবটোটাল', value: '৳${double.tryParse(_order!['subtotal']?.toString() ?? '0')?.toInt() ?? 0}'),
                                const SizedBox(height: 6),
                                _TotalRow(label: 'ছাড়', value: '- ৳${double.tryParse(_order!['discount'].toString())?.toInt() ?? 0}', color: Colors.green),
                                const Divider(height: 14),
                              ],
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('সর্বমোট', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                                  Text(
                                    '৳${double.tryParse(_order!['total']?.toString() ?? '0')?.toInt() ?? 0}',
                                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: AppConstants.primaryColor),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Payment Info
                        _InfoCard(
                          title: 'পেমেন্ট তথ্য',
                          icon: Icons.payment_rounded,
                          child: Column(
                            children: [
                              _TotalRow(label: 'মেথড', value: (_order!['payment_method'] ?? 'cod').toString().toUpperCase()),
                              const SizedBox(height: 6),
                              _TotalRow(label: 'স্ট্যাটাস', value: 'ক্যাশ অন ডেলিভারি'),
                            ],
                          ),
                        ),
                        const SizedBox(height: 40),
                      ],
                    ),
                  ),
                ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final Widget child;
  const _InfoCard({required this.title, required this.icon, required this.child});

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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Icon(icon, color: AppConstants.primaryColor, size: 20),
            const SizedBox(width: 8),
            Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          ]),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _TotalRow extends StatelessWidget {
  final String label, value;
  final Color? color;
  const _TotalRow({required this.label, required this.value, this.color});
  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(fontSize: 13, color: AppConstants.textLightColor)),
        Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: color)),
      ],
    );
  }
}
