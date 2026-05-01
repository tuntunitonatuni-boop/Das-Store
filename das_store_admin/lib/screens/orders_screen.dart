import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';
import 'order_detail_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});
  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> with SingleTickerProviderStateMixin {
  late TabController _tabCtrl;
  final _tabs = ['all', 'pending', 'confirmed', 'packaging', 'delivered', 'cancelled'];
  final _tabLabels = ['সব', 'পেন্ডিং', 'কনফার্মড', 'প্যাকেজিং', 'ডেলিভারড', 'বাতিল'];

  @override
  void initState() { super.initState(); _tabCtrl = TabController(length: _tabs.length, vsync: this); }
  @override
  void dispose() { _tabCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('অর্ডার ম্যানেজমেন্ট'),
        backgroundColor: AppConstants.primaryColor,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
        bottom: TabBar(
          controller: _tabCtrl,
          isScrollable: true,
          indicatorColor: AppConstants.accentColor,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white54,
          tabAlignment: TabAlignment.start,
          tabs: _tabLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: TabBarView(
        controller: _tabCtrl,
        children: _tabs.map((status) => _OrderList(status: status == 'all' ? '' : status)).toList(),
      ),
    );
  }
}

class _OrderList extends StatefulWidget {
  final String status;
  const _OrderList({required this.status});
  @override
  State<_OrderList> createState() => _OrderListState();
}

class _OrderListState extends State<_OrderList> with AutomaticKeepAliveClientMixin {
  List<dynamic> _orders = [];
  bool _isLoading = true;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _isLoading = true);
    final params = <String, String>{'action': 'orders'};
    if (widget.status.isNotEmpty) params['status'] = widget.status;
    final res = await ApiService.get('admin.php', params: params);
    if (mounted) setState(() { _isLoading = false; if (res['success'] == true) _orders = res['orders'] ?? []; });
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'pending': return AppConstants.warningColor;
      case 'confirmed': return AppConstants.accentColor;
      case 'packaging': return Colors.purple;
      case 'delivered': return AppConstants.successColor;
      case 'cancelled': return AppConstants.dangerColor;
      default: return Colors.grey;
    }
  }

  String _statusLabel(String s) {
    switch (s) {
      case 'pending': return 'পেন্ডিং';
      case 'confirmed': return 'কনফার্মড';
      case 'packaging': return 'প্যাকেজিং';
      case 'delivered': return 'ডেলিভারড';
      case 'cancelled': return 'বাতিল';
      case 'completed': return 'সম্পন্ন';
      default: return s;
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    if (_isLoading) return const Center(child: CircularProgressIndicator(color: AppConstants.accentColor));
    if (_orders.isEmpty) return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(Icons.receipt_long_outlined, size: 64, color: Colors.grey.shade300),
      const SizedBox(height: 12),
      const Text('কোন অর্ডার নেই', style: TextStyle(color: AppConstants.textLightColor, fontSize: 16)),
    ]));

    return RefreshIndicator(
      onRefresh: _load,
      color: AppConstants.accentColor,
      child: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: _orders.length,
        itemBuilder: (ctx, i) {
          final o = _orders[i];
          final status = o['status'] ?? '';
          final color = _statusColor(status);
          return GestureDetector(
            onTap: () async {
              await Navigator.push(context, MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: int.tryParse(o['id'].toString()) ?? 0)));
              _load(); // Refresh after returning
            },
            child: Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
                boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)],
                border: Border(left: BorderSide(color: color, width: 4))),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Expanded(child: Text(o['invoice_no'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15))),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(20)),
                    child: Text(_statusLabel(status), style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700)),
                  ),
                ]),
                const SizedBox(height: 8),
                Row(children: [
                  Icon(Icons.person_outline, size: 15, color: AppConstants.textLightColor),
                  const SizedBox(width: 4),
                  Text(o['customer_name'] ?? 'Walk-in', style: const TextStyle(fontSize: 13, color: AppConstants.textLightColor)),
                  const Spacer(),
                  Text('৳${double.tryParse(o['total'].toString())?.toInt() ?? 0}',
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppConstants.accentColor)),
                ]),
                const SizedBox(height: 4),
                Text(o['created_at'] ?? '', style: const TextStyle(fontSize: 11, color: AppConstants.textLightColor)),
              ]),
            ),
          );
        },
      ),
    );
  }
}
