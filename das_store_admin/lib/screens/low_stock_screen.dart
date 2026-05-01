import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class LowStockScreen extends StatefulWidget {
  const LowStockScreen({super.key});
  @override
  State<LowStockScreen> createState() => _LowStockScreenState();
}

class _LowStockScreenState extends State<LowStockScreen> {
  List<dynamic> _products = [];
  bool _isLoading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'low_stock'});
    if (mounted) setState(() { _isLoading = false; if (res['success'] == true) _products = res['products'] ?? []; });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('স্টক কম আছে'), backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
          : _products.isEmpty
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.check_circle_outline_rounded, size: 72, color: AppConstants.successColor.withOpacity(0.5)),
                  const SizedBox(height: 16),
                  const Text('সব পণ্যের স্টক আছে! ✅', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: AppConstants.textLightColor)),
                ]))
              : RefreshIndicator(
                  onRefresh: _load,
                  color: AppConstants.accentColor,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: _products.length,
                    itemBuilder: (ctx, i) {
                      final p = _products[i];
                      final display = int.tryParse(p['display_qty'].toString()) ?? 0;
                      final warehouse = int.tryParse(p['warehouse_qty'].toString()) ?? 0;
                      final total = display + warehouse;
                      final reorder = int.tryParse(p['reorder_level'].toString()) ?? 0;
                      final isZero = total <= 0;

                      return Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)],
                          border: Border(left: BorderSide(color: isZero ? AppConstants.dangerColor : AppConstants.warningColor, width: 4)),
                        ),
                        child: Row(children: [
                          Container(
                            width: 44, height: 44,
                            decoration: BoxDecoration(
                              color: (isZero ? AppConstants.dangerColor : AppConstants.warningColor).withOpacity(0.1),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Icon(
                              isZero ? Icons.error_rounded : Icons.warning_amber_rounded,
                              color: isZero ? AppConstants.dangerColor : AppConstants.warningColor, size: 22,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(p['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 4),
                            Text('ডিসপ্লে: $display | গুদাম: $warehouse | রিঅর্ডার: $reorder',
                              style: const TextStyle(fontSize: 11, color: AppConstants.textLightColor)),
                          ])),
                          Column(children: [
                            Text('$total', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900,
                              color: isZero ? AppConstants.dangerColor : AppConstants.warningColor)),
                            Text(p['unit'] ?? 'pcs', style: const TextStyle(fontSize: 11, color: AppConstants.textLightColor)),
                          ]),
                        ]),
                      );
                    },
                  ),
                ),
    );
  }
}
