import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class RecentSalesScreen extends StatefulWidget {
  const RecentSalesScreen({super.key});
  @override
  State<RecentSalesScreen> createState() => _RecentSalesScreenState();
}

class _RecentSalesScreenState extends State<RecentSalesScreen> {
  List<dynamic> _sales = [];
  bool _isLoading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'recent_sales', 'limit': '30'});
    if (mounted) setState(() { _isLoading = false; if (res['success'] == true) _sales = res['sales'] ?? []; });
  }

  IconData _payIcon(String method) {
    switch (method) {
      case 'cash': return Icons.money_rounded;
      case 'card': return Icons.credit_card_rounded;
      case 'mobile': return Icons.phone_android_rounded;
      case 'credit': return Icons.account_balance_rounded;
      case 'cod': return Icons.local_shipping_rounded;
      default: return Icons.payments_rounded;
    }
  }

  Color _payColor(String method) {
    switch (method) {
      case 'cash': return AppConstants.successColor;
      case 'card': return AppConstants.accentColor;
      case 'mobile': return Colors.purple;
      case 'credit': return AppConstants.warningColor;
      case 'cod': return AppConstants.infoColor;
      default: return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    // Calculate total
    double totalAmount = 0;
    for (var s in _sales) {
      totalAmount += double.tryParse(s['total'].toString()) ?? 0;
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('সাম্প্রতিক বিক্রয়'),
        backgroundColor: AppConstants.primaryColor,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
          : Column(
              children: [
                // Summary card
                Container(
                  margin: const EdgeInsets.all(12),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: [Color(0xFF0f172a), Color(0xFF1e293b)]),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${_sales.length} টি বিক্রয়', style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 13)),
                      const SizedBox(height: 4),
                      Text('৳${totalAmount.toInt().toString().replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},')}',
                        style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900)),
                    ]),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: AppConstants.accentColor.withOpacity(0.2), borderRadius: BorderRadius.circular(14)),
                      child: const Icon(Icons.trending_up_rounded, color: AppConstants.accentColor, size: 28),
                    ),
                  ]),
                ),

                // Sales list
                Expanded(
                  child: RefreshIndicator(
                    onRefresh: _load,
                    color: AppConstants.accentColor,
                    child: _sales.isEmpty
                        ? const Center(child: Text('কোন বিক্রয় নেই', style: TextStyle(color: AppConstants.textLightColor)))
                        : ListView.builder(
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            itemCount: _sales.length,
                            itemBuilder: (ctx, i) {
                              final s = _sales[i];
                              final method = s['payment_method'] ?? 'cash';
                              return Container(
                                margin: const EdgeInsets.only(bottom: 8),
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(12),
                                  boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 6)],
                                ),
                                child: Row(children: [
                                  Container(
                                    padding: const EdgeInsets.all(10),
                                    decoration: BoxDecoration(color: _payColor(method).withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
                                    child: Icon(_payIcon(method), color: _payColor(method), size: 20),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Text(s['invoice_no'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                                    const SizedBox(height: 2),
                                    Text('${s['customer_name'] ?? 'Walk-in'} • $method',
                                      style: const TextStyle(fontSize: 11, color: AppConstants.textLightColor)),
                                    Text(s['created_at'] ?? '', style: const TextStyle(fontSize: 10, color: AppConstants.textLightColor)),
                                  ])),
                                  Text('৳${double.tryParse(s['total'].toString())?.toInt() ?? 0}',
                                    style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppConstants.accentColor)),
                                ]),
                              );
                            },
                          ),
                  ),
                ),
              ],
            ),
    );
  }
}
