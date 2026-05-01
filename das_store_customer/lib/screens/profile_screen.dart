import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../utils/constants.dart';
import '../providers/auth_provider.dart';
import 'login_screen.dart';
import 'my_orders_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(title: const Text('প্রোফাইল'), backgroundColor: AppConstants.primaryColor, foregroundColor: Colors.white, elevation: 0),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(children: [
          // Profile Card
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [Color(0xFF16a34a), Color(0xFF059669)]),
              borderRadius: BorderRadius.circular(20),
              boxShadow: [BoxShadow(color: AppConstants.primaryColor.withOpacity(0.3), blurRadius: 16, offset: const Offset(0, 6))],
            ),
            child: Column(children: [
              CircleAvatar(
                radius: 40,
                backgroundColor: Colors.white24,
                child: Text(
                  (user?.name ?? 'U').substring(0, 1).toUpperCase(),
                  style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: Colors.white),
                ),
              ),
              const SizedBox(height: 12),
              Text(user?.name ?? '', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Colors.white)),
              const SizedBox(height: 4),
              Text(user?.phone ?? '', style: TextStyle(fontSize: 14, color: Colors.white.withOpacity(0.8))),
              const SizedBox(height: 4),
              Text('@${user?.username ?? ''}', style: TextStyle(fontSize: 13, color: Colors.white.withOpacity(0.7))),
            ]),
          ),
          const SizedBox(height: 24),

          // Menu Items
          _menuItem(context, Icons.shopping_bag_rounded, 'আমার অর্ডার', 'অর্ডার ইতিহাস দেখুন',
            () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MyOrdersScreen()))),
          _menuItem(context, Icons.location_on_rounded, 'ঠিকানা', user?.address?.isNotEmpty == true ? user!.address! : 'ডেলিভারি ঠিকানা',
            () => ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Checkout-এ ঠিকানা দিন'), behavior: SnackBarBehavior.floating))),
          _menuItem(context, Icons.headset_mic_rounded, 'সাপোর্ট', 'যোগাযোগ করুন',
            () => ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('WhatsApp: 01XXXXXXXXX'), behavior: SnackBarBehavior.floating))),
          const SizedBox(height: 16),

          // Logout
          SizedBox(
            width: double.infinity,
            height: 52,
            child: OutlinedButton.icon(
              onPressed: () async {
                final confirm = await showDialog<bool>(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    title: const Text('লগআউট করবেন?'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('না')),
                      TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('হ্যাঁ', style: TextStyle(color: AppConstants.dangerColor))),
                    ],
                  ),
                );
                if (confirm == true) {
                  await auth.logout();
                  if (context.mounted) {
                    Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
                  }
                }
              },
              icon: const Icon(Icons.logout_rounded, color: AppConstants.dangerColor),
              label: const Text('লগআউট', style: TextStyle(color: AppConstants.dangerColor, fontWeight: FontWeight.w700, fontSize: 16)),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: AppConstants.dangerColor),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
          const SizedBox(height: 24),
          const Text('Das Store v1.0.0', style: TextStyle(color: AppConstants.textLightColor, fontSize: 12)),
          const SizedBox(height: 40),
        ]),
      ),
    );
  }

  Widget _menuItem(BuildContext context, IconData icon, String title, String subtitle, VoidCallback onTap) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 6)],
      ),
      child: ListTile(
        onTap: onTap,
        leading: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: AppConstants.primaryColor.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: AppConstants.primaryColor, size: 22),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
        subtitle: Text(subtitle, style: const TextStyle(fontSize: 12, color: AppConstants.textLightColor)),
        trailing: const Icon(Icons.chevron_right_rounded, color: Colors.grey),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }
}
