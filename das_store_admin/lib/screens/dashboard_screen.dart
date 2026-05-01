import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';
import '../providers/auth_provider.dart';
import 'orders_screen.dart';
import 'low_stock_screen.dart';
import 'recent_sales_screen.dart';
import 'login_screen.dart';
import 'pos_screen.dart';
import 'cash_register_screen.dart';
import 'inventory_screen.dart';
import 'coupons_screen.dart';
import 'expenses_screen.dart';
import 'customer_ledger_screen.dart';
import 'supplier_ledger_screen.dart';
import 'reports_screen.dart';
import 'purchases_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});
  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _data;
  bool _isLoading = true;
  int _currentIndex = 0;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'dashboard'});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) _data = res['data'];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    final pages = [
      _buildDashboardBody(auth),
      const PosScreen(),
      const OrdersScreen(),
      const RecentSalesScreen(),
    ];

    return Scaffold(
      body: pages[_currentIndex],
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 10, offset: const Offset(0, -2))],
        ),
        child: NavigationBar(
          selectedIndex: _currentIndex,
          onDestinationSelected: (i) => setState(() => _currentIndex = i),
          backgroundColor: Colors.white,
          elevation: 0,
          indicatorColor: AppConstants.accentColor.withOpacity(0.12),
          destinations: [
            NavigationDestination(
              icon: const Icon(Icons.dashboard_outlined),
              selectedIcon: Icon(Icons.dashboard_rounded, color: AppConstants.accentColor),
              label: 'ড্যাশবোর্ড',
            ),
            NavigationDestination(
              icon: const Icon(Icons.point_of_sale_outlined),
              selectedIcon: Icon(Icons.point_of_sale_rounded, color: AppConstants.accentColor),
              label: 'POS',
            ),
            NavigationDestination(
              icon: Badge(
                isLabelVisible: _data != null && (_data!['pending_orders'] ?? 0) > 0,
                label: Text('${_data?['pending_orders'] ?? 0}', style: const TextStyle(fontSize: 10)),
                child: const Icon(Icons.receipt_long_outlined),
              ),
              selectedIcon: Badge(
                isLabelVisible: _data != null && (_data!['pending_orders'] ?? 0) > 0,
                label: Text('${_data?['pending_orders'] ?? 0}', style: const TextStyle(fontSize: 10)),
                child: Icon(Icons.receipt_long_rounded, color: AppConstants.accentColor),
              ),
              label: 'অর্ডার',
            ),
            NavigationDestination(
              icon: const Icon(Icons.bar_chart_outlined),
              selectedIcon: Icon(Icons.bar_chart_rounded, color: AppConstants.accentColor),
              label: 'বিক্রয়',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDashboardBody(AuthProvider auth) {
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: _loadDashboard,
        color: AppConstants.accentColor,
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: AppConstants.accentColor))
            : CustomScrollView(
                slivers: [
                  // ---- Header ----
                  SliverToBoxAdapter(
                    child: Container(
                      padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(colors: [Color(0xFF0f172a), Color(0xFF1e293b)]),
                        borderRadius: BorderRadius.only(bottomLeft: Radius.circular(28), bottomRight: Radius.circular(28)),
                      ),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          CircleAvatar(
                            backgroundColor: AppConstants.accentColor.withOpacity(0.2),
                            radius: 22,
                            child: Text(
                              (auth.user?.name ?? 'A').substring(0, 1).toUpperCase(),
                              style: const TextStyle(color: AppConstants.accentColor, fontWeight: FontWeight.w800, fontSize: 18),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('স্বাগতম, ${auth.user?.name ?? 'Admin'}', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
                            Text(auth.user?.role ?? '', style: TextStyle(color: Colors.white.withOpacity(0.5), fontSize: 13)),
                          ])),
                          IconButton(
                            icon: const Icon(Icons.logout_rounded, color: Colors.white54),
                            onPressed: () async {
                              final c = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                                title: const Text('লগআউট?'),
                                actions: [
                                  TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('না')),
                                  TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('হ্যাঁ', style: TextStyle(color: AppConstants.dangerColor))),
                                ],
                              ));
                              if (c == true) {
                                await auth.logout();
                                if (mounted) Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
                              }
                            },
                          ),
                        ]),
                      ]),
                    ),
                  ),

                  // ---- Stats Grid ----
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
                    sliver: SliverGrid(
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 1.5),
                      delegate: SliverChildListDelegate([
                        _StatCard(
                          icon: Icons.monetization_on_rounded, title: 'আজকের বিক্রয়',
                          value: '৳${_fmt(_data?['today_sales'] ?? 0)}',
                          color: AppConstants.successColor, subtitle: '${_data?['today_orders'] ?? 0} টি অর্ডার',
                        ),
                        _StatCard(
                          icon: Icons.pending_actions_rounded, title: 'পেন্ডিং অর্ডার',
                          value: '${_data?['pending_orders'] ?? 0}',
                          color: AppConstants.warningColor, subtitle: 'অনলাইন অর্ডার',
                          onTap: () => setState(() => _currentIndex = 2),
                        ),
                        _StatCard(
                          icon: Icons.warning_amber_rounded, title: 'স্টক কম',
                          value: '${_data?['low_stock'] ?? 0}',
                          color: AppConstants.dangerColor, subtitle: 'পণ্য রিঅর্ডার দরকার',
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LowStockScreen())),
                        ),
                        _StatCard(
                          icon: Icons.calendar_month_rounded, title: 'মাসিক বিক্রয়',
                          value: '৳${_fmt(_data?['month_sales'] ?? 0)}',
                          color: AppConstants.infoColor, subtitle: 'এই মাসে',
                        ),
                      ]),
                    ),
                  ),

                  // ---- Small Stats Row ----
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      child: Row(children: [
                        Expanded(child: _MiniStat(icon: Icons.inventory_2_rounded, label: 'পণ্য', value: '${_data?['total_products'] ?? 0}', color: AppConstants.accentColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const InventoryScreen())))),
                        const SizedBox(width: 12),
                        Expanded(child: _MiniStat(icon: Icons.people_rounded, label: 'কাস্টমার', value: '${_data?['total_customers'] ?? 0}', color: AppConstants.successColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CustomerLedgerScreen())))),
                      ]),
                    ),
                  ),

                  // ---- Chart ----
                  SliverToBoxAdapter(
                    child: Container(
                      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18),
                        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10)]),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('শেষ ৭ দিনের বিক্রয়', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                        const SizedBox(height: 20),
                        SizedBox(height: 180, child: _buildChart()),
                      ]),
                    ),
                  ),

                  // ---- Quick Actions ----
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('দ্রুত অ্যাকশন', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                        const SizedBox(height: 12),
                        Row(children: [
                          Expanded(child: _ActionBtn(icon: Icons.receipt_long_rounded, label: 'অর্ডার', color: AppConstants.accentColor,
                            onTap: () => setState(() => _currentIndex = 2))),
                          const SizedBox(width: 12),
                          Expanded(child: _ActionBtn(icon: Icons.point_of_sale_rounded, label: 'POS', color: AppConstants.successColor,
                            onTap: () => setState(() => _currentIndex = 1))),
                          const SizedBox(width: 12),
                          Expanded(child: _ActionBtn(icon: Icons.inventory_2_rounded, label: 'ইনভেন্টরি', color: AppConstants.infoColor,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const InventoryScreen())))),
                        ]),
                        const SizedBox(height: 12),
                        Row(children: [
                          Expanded(child: _ActionBtn(icon: Icons.monetization_on_rounded, label: 'ক্যাশ রেজিস্টার', color: AppConstants.successColor,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CashRegisterScreen())))),
                          const SizedBox(width: 12),
                          Expanded(child: _ActionBtn(icon: Icons.warning_amber_rounded, label: 'স্টক কম', color: AppConstants.dangerColor,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LowStockScreen())))),
                          const SizedBox(width: 12),
                          Expanded(child: Container()), // Empty space for layout
                        ]),
                        const SizedBox(height: 12),
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 8),
                          child: Text('ম্যানেজমেন্ট', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.grey)),
                        ),
                        Row(children: [
                          Expanded(child: _ActionBtn(icon: Icons.local_offer_rounded, label: 'কুপন', color: Colors.purple,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CouponsScreen())))),
                          const SizedBox(width: 12),
                          Expanded(child: _ActionBtn(icon: Icons.money_off_rounded, label: 'খরচ', color: Colors.redAccent,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ExpensesScreen())))),
                          const SizedBox(width: 12),
                          Expanded(child: _ActionBtn(icon: Icons.people_alt_rounded, label: 'কাস্টমার', color: AppConstants.primaryColor,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CustomerLedgerScreen())))),
                        ]),
                        const SizedBox(height: 12),
                        Row(children: [
                          Expanded(child: _ActionBtn(icon: Icons.local_shipping_rounded, label: 'সাপ্লায়ার ডিউ', color: Colors.orange.shade700,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SupplierLedgerScreen())))),
                          const SizedBox(width: 12),
                          Expanded(child: _ActionBtn(icon: Icons.shopping_cart_checkout_rounded, label: 'কেনাকাটা', color: Colors.teal,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const PurchasesScreen())))),
                          const SizedBox(width: 12),
                          Expanded(child: _ActionBtn(icon: Icons.analytics_rounded, label: 'রিপোর্টস', color: Colors.indigo,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ReportsScreen())))),
                        ]),
                      ]),
                    ),
                  ),

                  const SliverToBoxAdapter(child: SizedBox(height: 100)),
                ],
              ),
      ),
    );
  }

  String _fmt(dynamic val) {
    final n = (val is num) ? val.toInt() : int.tryParse(val.toString()) ?? 0;
    if (n >= 100000) return '${(n / 1000).toStringAsFixed(1)}K';
    return n.toString().replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},');
  }

  Widget _buildChart() {
    final chart = (_data?['chart'] as List?) ?? [];
    if (chart.isEmpty) return const Center(child: Text('No data'));

    final maxY = chart.fold<double>(0, (m, c) {
      final v = double.tryParse(c['total'].toString()) ?? 0;
      return v > m ? v : m;
    });

    return BarChart(BarChartData(
      alignment: BarChartAlignment.spaceAround,
      maxY: maxY * 1.2 + 1,
      barTouchData: BarTouchData(
        touchTooltipData: BarTouchTooltipData(
          getTooltipItem: (g, gi, r, ri) => BarTooltipItem('৳${r.toY.toInt()}', const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12)),
        ),
      ),
      titlesData: FlTitlesData(
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, getTitlesWidget: (v, m) {
          final i = v.toInt();
          if (i < 0 || i >= chart.length) return const SizedBox.shrink();
          return Padding(padding: const EdgeInsets.only(top: 6), child: Text(chart[i]['date'] ?? '', style: const TextStyle(fontSize: 10, color: AppConstants.textLightColor)));
        })),
      ),
      gridData: const FlGridData(show: false),
      borderData: FlBorderData(show: false),
      barGroups: List.generate(chart.length, (i) {
        final v = double.tryParse(chart[i]['total'].toString()) ?? 0;
        return BarChartGroupData(x: i, barRods: [
          BarChartRodData(toY: v, color: AppConstants.accentColor, width: 20, borderRadius: const BorderRadius.vertical(top: Radius.circular(6)),
            backDrawRodData: BackgroundBarChartRodData(show: true, toY: maxY * 1.2 + 1, color: AppConstants.accentColor.withOpacity(0.06))),
        ]);
      }),
    ));
  }
}

class _StatCard extends StatelessWidget {
  final IconData icon; final String title, value, subtitle; final Color color; final VoidCallback? onTap;
  const _StatCard({required this.icon, required this.title, required this.value, required this.color, required this.subtitle, this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Row(children: [
            Container(padding: const EdgeInsets.all(7), decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
              child: Icon(icon, color: color, size: 18)),
            const Spacer(),
            if (onTap != null) Icon(Icons.arrow_forward_ios_rounded, size: 12, color: Colors.grey.shade400),
          ]),
          Text(value, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: color)),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppConstants.textColor)),
            Text(subtitle, style: const TextStyle(fontSize: 10, color: AppConstants.textLightColor)),
          ]),
        ]),
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  final IconData icon; final String label, value; final Color color; final VoidCallback? onTap;
  const _MiniStat({required this.icon, required this.label, required this.value, required this.color, this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)]),
        child: Row(children: [
          Container(padding: const EdgeInsets.all(8), decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, color: color, size: 20)),
          const SizedBox(width: 12),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: color)),
            Text(label, style: const TextStyle(fontSize: 12, color: AppConstants.textLightColor)),
          ]),
        ]),
      ),
    );
  }
}

class _ActionBtn extends StatelessWidget {
  final IconData icon; final String label; final Color color; final VoidCallback onTap;
  const _ActionBtn({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(color: color.withOpacity(0.08), borderRadius: BorderRadius.circular(14), border: Border.all(color: color.withOpacity(0.15))),
        child: Column(children: [
          Icon(icon, color: color, size: 26),
          const SizedBox(height: 6),
          Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color)),
        ]),
      ),
    );
  }
}
