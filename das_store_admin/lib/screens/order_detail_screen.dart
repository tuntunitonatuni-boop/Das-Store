import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  const OrderDetailScreen({super.key, required this.orderId});
  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Map<String, dynamic>? _order;
  bool _isLoading = true;
  bool _isUpdating = false;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'order_detail', 'id': widget.orderId.toString()});
    if (mounted) setState(() { _isLoading = false; if (res['success'] == true) _order = res['order']; });
  }

  Future<void> _updateStatus(String newStatus) async {
    setState(() => _isUpdating = true);
    final res = await ApiService.post('admin.php?action=update_order_status', {'order_id': widget.orderId, 'status': newStatus});
    setState(() => _isUpdating = false);
    if (!mounted) return;
    if (res['success'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: const Text('স্ট্যাটাস আপডেট হয়েছে!'), backgroundColor: AppConstants.successColor,
        behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))));
      _load();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message'] ?? 'Failed'), backgroundColor: AppConstants.dangerColor,
        behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))));
    }
  }

  Color _statusColor(String s) {
    switch (s) { case 'pending': return AppConstants.warningColor; case 'confirmed': return AppConstants.accentColor;
      case 'packaging': return Colors.purple; case 'delivered': return AppConstants.successColor;
      case 'cancelled': return AppConstants.dangerColor; default: return Colors.grey; }
  }

  String _statusBn(String s) {
    switch (s) { case 'pending': return 'পেন্ডিং'; case 'confirmed': return 'কনফার্মড';
      case 'packaging': return 'প্যাকেজিং'; case 'delivered': return 'ডেলিভারড';
      case 'cancelled': return 'বাতিল'; default: return s; }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_order?['invoice_no'] ?? 'অর্ডার ডিটেইলস'), backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
          : _order == null
              ? const Center(child: Text('অর্ডার পাওয়া যায়নি'))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    // Status Card
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
                        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          const Text('স্ট্যাটাস: ', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                            decoration: BoxDecoration(color: _statusColor(_order!['status']).withOpacity(0.1), borderRadius: BorderRadius.circular(20)),
                            child: Text(_statusBn(_order!['status']), style: TextStyle(color: _statusColor(_order!['status']), fontWeight: FontWeight.w700)),
                          ),
                        ]),
                        const SizedBox(height: 12),
                        Text('Invoice: ${_order!['invoice_no']}', style: const TextStyle(fontSize: 14, color: AppConstants.textLightColor)),
                        Text('তারিখ: ${_order!['created_at'] ?? ''}', style: const TextStyle(fontSize: 13, color: AppConstants.textLightColor)),
                        Text('পেমেন্ট: ${_order!['payment_method'] ?? 'cod'}', style: const TextStyle(fontSize: 13, color: AppConstants.textLightColor)),
                      ]),
                    ),
                    const SizedBox(height: 12),

                    // Customer Info
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
                        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('কাস্টমার', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                        const SizedBox(height: 8),
                        _infoRow(Icons.person_outline, _order!['customer_name'] ?? 'Walk-in'),
                        if ((_order!['customer_phone'] ?? '').isNotEmpty) _infoRow(Icons.phone_outlined, _order!['customer_phone']),
                        if ((_order!['shipping_address'] ?? (_order!['customer_address'] ?? '')).isNotEmpty)
                          _infoRow(Icons.location_on_outlined, _order!['shipping_address'] ?? _order!['customer_address'] ?? ''),
                      ]),
                    ),
                    const SizedBox(height: 12),

                    // Items
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
                        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('অর্ডার আইটেম', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                        const SizedBox(height: 12),
                        ...(_order!['items'] as List? ?? []).map((item) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Row(children: [
                            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(item['product_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                              Text('৳${double.tryParse(item['unit_price'].toString())?.toInt() ?? 0} × ${item['qty']}',
                                style: const TextStyle(fontSize: 12, color: AppConstants.textLightColor)),
                            ])),
                            Text('৳${double.tryParse(item['subtotal'].toString())?.toInt() ?? 0}',
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                          ]),
                        )),
                        const Divider(),
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                          const Text('সর্বমোট', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                          Text('৳${double.tryParse(_order!['total'].toString())?.toInt() ?? 0}',
                            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: AppConstants.accentColor)),
                        ]),
                      ]),
                    ),
                    const SizedBox(height: 16),

                    // Status Actions
                    if (_order!['status'] != 'delivered' && _order!['status'] != 'cancelled')
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
                          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          const Text('স্ট্যাটাস পরিবর্তন', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                          const SizedBox(height: 12),
                          if (_isUpdating)
                            const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
                          else
                            Wrap(spacing: 8, runSpacing: 8, children: [
                              if (_order!['status'] == 'pending')
                                _statusBtn('কনফার্ম করুন', AppConstants.accentColor, () => _updateStatus('confirmed')),
                              if (_order!['status'] == 'confirmed')
                                _statusBtn('প্যাকেজিং', Colors.purple, () => _updateStatus('packaging')),
                              if (_order!['status'] == 'packaging' || _order!['status'] == 'confirmed')
                                _statusBtn('ডেলিভারড', AppConstants.successColor, () => _updateStatus('delivered')),
                              _statusBtn('বাতিল', AppConstants.dangerColor, () => _updateStatus('cancelled')),
                            ]),
                        ]),
                      ),
                    const SizedBox(height: 40),
                  ]),
                ),
    );
  }

  Widget _infoRow(IconData icon, String text) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(children: [
      Icon(icon, size: 16, color: AppConstants.textLightColor),
      const SizedBox(width: 8),
      Expanded(child: Text(text, style: const TextStyle(fontSize: 14, color: AppConstants.textColor))),
    ]),
  );

  Widget _statusBtn(String label, Color color, VoidCallback onTap) => ElevatedButton(
    onPressed: onTap,
    style: ElevatedButton.styleFrom(backgroundColor: color, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10)),
    child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13)),
  );
}
