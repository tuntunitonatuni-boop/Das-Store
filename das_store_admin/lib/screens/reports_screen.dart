import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});
  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  Map<String, dynamic>? _report;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'reports_summary'});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) _report = res['report'];
      });
    }
  }

  double _safe(dynamic val) => double.tryParse(val?.toString() ?? '0') ?? 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('রিপোর্টস (শেষ ৩০ দিন)'),
        backgroundColor: AppConstants.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _report == null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.analytics_outlined, size: 80, color: Colors.grey.shade300),
                      const SizedBox(height: 16),
                      const Text('Report load হয়নি', style: TextStyle(fontSize: 18, color: Colors.grey)),
                      const SizedBox(height: 12),
                      ElevatedButton(onPressed: _loadData, child: const Text('আবার চেষ্টা করুন')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadData,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Net Balance Big Card
                        Container(
                          padding: const EdgeInsets.all(24),
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: [AppConstants.primaryColor, AppConstants.primaryColor.withOpacity(0.8)],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: [BoxShadow(color: AppConstants.primaryColor.withOpacity(0.3), blurRadius: 15, offset: const Offset(0, 8))],
                          ),
                          child: Column(
                            children: [
                              const Text('নেট ব্যালেন্স (সরলীকৃত)', style: TextStyle(color: Colors.white70, fontSize: 14)),
                              const SizedBox(height: 8),
                              Text(
                                '৳${_safe(_report!['net_profit']).toStringAsFixed(0)}',
                                style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w900),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'আয় - খরচ (শেষ ৩০ দিন)',
                                style: TextStyle(color: Colors.white.withOpacity(0.6), fontSize: 12),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),

                        // Stats Grid
                        GridView.count(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          crossAxisCount: 2,
                          mainAxisSpacing: 14,
                          crossAxisSpacing: 14,
                          childAspectRatio: 1.1,
                          children: [
                            _StatCard(
                              title: 'মোট বিক্রয়',
                              value: '৳${_safe(_report!['revenue_30d']).toStringAsFixed(0)}',
                              icon: Icons.trending_up_rounded,
                              color: AppConstants.successColor,
                            ),
                            _StatCard(
                              title: 'মোট খরচ',
                              value: '৳${_safe(_report!['expenses_30d']).toStringAsFixed(0)}',
                              icon: Icons.trending_down_rounded,
                              color: AppConstants.dangerColor,
                            ),
                            _StatCard(
                              title: 'মোট কেনাকাটা',
                              value: '৳${_safe(_report!['purchases_30d']).toStringAsFixed(0)}',
                              icon: Icons.shopping_cart_rounded,
                              color: Colors.orange,
                            ),
                            _StatCard(
                              title: 'গ্রস প্রফিট',
                              value: '৳${(_safe(_report!['revenue_30d']) - _safe(_report!['purchases_30d'])).toStringAsFixed(0)}',
                              icon: Icons.account_balance_wallet_rounded,
                              color: AppConstants.infoColor,
                            ),
                          ],
                        ),
                        const SizedBox(height: 20),

                        // Summary Table
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('হিসাব সারাংশ', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                              const SizedBox(height: 16),
                              _SummaryRow(label: 'মোট আয় (৩০ দিন)', value: '৳${_safe(_report!['revenue_30d']).toStringAsFixed(0)}', color: AppConstants.successColor),
                              const Divider(height: 16),
                              _SummaryRow(label: 'মোট কেনাকাটা', value: '- ৳${_safe(_report!['purchases_30d']).toStringAsFixed(0)}', color: Colors.orange),
                              const Divider(height: 16),
                              _SummaryRow(label: 'মোট খরচ', value: '- ৳${_safe(_report!['expenses_30d']).toStringAsFixed(0)}', color: AppConstants.dangerColor),
                              const Divider(height: 16),
                              _SummaryRow(
                                label: 'নেট ব্যালেন্স',
                                value: '৳${_safe(_report!['net_profit']).toStringAsFixed(0)}',
                                color: _safe(_report!['net_profit']) >= 0 ? AppConstants.successColor : AppConstants.dangerColor,
                                bold: true,
                              ),
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

class _StatCard extends StatelessWidget {
  final String title, value;
  final IconData icon;
  final Color color;
  const _StatCard({required this.title, required this.value, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: color.withOpacity(0.1), blurRadius: 10, offset: const Offset(0, 4))],
        border: Border(top: BorderSide(color: color, width: 4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 28),
          const Spacer(),
          Text(value, style: TextStyle(color: color, fontSize: 20, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(title, style: const TextStyle(color: AppConstants.textLightColor, fontSize: 12, fontWeight: FontWeight.w500)),
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final String label, value;
  final Color color;
  final bool bold;
  const _SummaryRow({required this.label, required this.value, required this.color, this.bold = false});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(fontSize: 14, fontWeight: bold ? FontWeight.w700 : FontWeight.normal, color: bold ? AppConstants.textColor : AppConstants.textLightColor)),
        Text(value, style: TextStyle(fontSize: bold ? 18 : 14, fontWeight: bold ? FontWeight.w900 : FontWeight.w600, color: color)),
      ],
    );
  }
}
