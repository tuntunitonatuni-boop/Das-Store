import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../services/api_service.dart';

class CouponsScreen extends StatefulWidget {
  const CouponsScreen({super.key});
  @override
  State<CouponsScreen> createState() => _CouponsScreenState();
}

class _CouponsScreenState extends State<CouponsScreen> {
  List<dynamic> _coupons = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    final res = await ApiService.get('admin.php', params: {'action': 'coupons'});
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true) _coupons = res['coupons'] ?? [];
      });
    }
  }

  Future<void> _toggleStatus(int id, bool currentStatus) async {
    final res = await ApiService.post('admin.php?action=toggle_coupon', {
      'id': id,
      'is_active': currentStatus ? 0 : 1,
    });
    if (mounted) {
      if (res['success'] == true) {
        _loadData();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Update ব্যর্থ হয়েছে'), backgroundColor: AppConstants.dangerColor),
        );
      }
    }
  }

  void _openAddCoupon() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AddCouponSheet(onSuccess: _loadData),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('কুপন ম্যানেজমেন্ট'),
        backgroundColor: Colors.purple,
        foregroundColor: Colors.white,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openAddCoupon,
        backgroundColor: Colors.purple,
        icon: const Icon(Icons.add),
        label: const Text('নতুন কুপন'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.purple))
          : _coupons.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.local_offer_outlined, size: 80, color: Colors.grey.shade300),
                      const SizedBox(height: 16),
                      const Text('কোন কুপন নেই', style: TextStyle(fontSize: 18, color: Colors.grey)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadData,
                  color: Colors.purple,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: _coupons.length,
                    itemBuilder: (ctx, i) {
                      final c = _coupons[i];
                      final isActive = c['is_active'].toString() == '1';
                      final discountType = c['discount_type']?.toString() ?? '';
                      final discountValue = double.tryParse(c['discount_value']?.toString() ?? '0') ?? 0;
                      final discountText = discountType == 'percent' ? '${discountValue.toInt()}% ছাড়' : '৳${discountValue.toInt()} ছাড়';

                      return Card(
                        elevation: 0,
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                          side: BorderSide(color: Colors.grey.shade200),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            children: [
                              Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(10),
                                    decoration: BoxDecoration(
                                      color: isActive ? Colors.purple.withOpacity(0.1) : Colors.grey.withOpacity(0.1),
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: Icon(Icons.local_offer_rounded, color: isActive ? Colors.purple : Colors.grey, size: 22),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          c['code'].toString().toUpperCase(),
                                          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, letterSpacing: 1.5),
                                        ),
                                        Text(discountText, style: const TextStyle(color: Colors.purple, fontWeight: FontWeight.w600, fontSize: 13)),
                                      ],
                                    ),
                                  ),
                                  Switch(
                                    value: isActive,
                                    activeColor: Colors.purple,
                                    onChanged: (_) => _toggleStatus(int.parse(c['id'].toString()), isActive),
                                  ),
                                ],
                              ),
                              const Divider(height: 16),
                              Row(
                                children: [
                                  _InfoChip(label: 'Min: ৳${double.tryParse(c['min_order_amount']?.toString() ?? '0')?.toInt() ?? 0}'),
                                  const SizedBox(width: 8),
                                  _InfoChip(label: 'Max: ৳${double.tryParse(c['max_discount']?.toString() ?? '0')?.toInt() ?? 0}'),
                                  const Spacer(),
                                  Text(
                                    c['valid_until'] != null ? 'Expires: ${c['valid_until']?.toString().substring(0, 10)}' : 'Lifetime',
                                    style: const TextStyle(fontSize: 11, color: AppConstants.textLightColor),
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

class _InfoChip extends StatelessWidget {
  final String label;
  const _InfoChip({required this.label});
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: Colors.grey.shade100, borderRadius: BorderRadius.circular(8)),
      child: Text(label, style: const TextStyle(fontSize: 11, color: AppConstants.textLightColor)),
    );
  }
}

// ============= ADD COUPON SHEET =============
class _AddCouponSheet extends StatefulWidget {
  final VoidCallback onSuccess;
  const _AddCouponSheet({required this.onSuccess});
  @override
  State<_AddCouponSheet> createState() => _AddCouponSheetState();
}

class _AddCouponSheetState extends State<_AddCouponSheet> {
  final _codeCtrl = TextEditingController();
  final _valueCtrl = TextEditingController();
  final _minOrderCtrl = TextEditingController(text: '0');
  final _maxDiscountCtrl = TextEditingController(text: '0');
  String _type = 'percent';
  bool _isSaving = false;
  DateTime? _validUntil;

  Future<void> _submit() async {
    final code = _codeCtrl.text.trim().toUpperCase();
    final value = double.tryParse(_valueCtrl.text) ?? 0;
    if (code.isEmpty || value <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Code এবং Discount value দিন'), backgroundColor: AppConstants.dangerColor),
      );
      return;
    }

    setState(() => _isSaving = true);
    final res = await ApiService.post('admin.php?action=add_coupon', {
      'code': code,
      'discount_type': _type,
      'discount_value': value,
      'min_order_amount': double.tryParse(_minOrderCtrl.text) ?? 0,
      'max_discount': double.tryParse(_maxDiscountCtrl.text) ?? 0,
      'valid_until': _validUntil != null ? '${_validUntil!.year}-${_validUntil!.month.toString().padLeft(2,'0')}-${_validUntil!.day.toString().padLeft(2,'0')}' : null,
      'is_active': 1,
    });
    setState(() => _isSaving = false);

    if (!mounted) return;
    if (res['success'] == true) {
      Navigator.pop(context);
      widget.onSuccess();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('✅ কুপন যোগ হয়েছে'), backgroundColor: AppConstants.successColor),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'Error'), backgroundColor: AppConstants.dangerColor),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              decoration: const BoxDecoration(
                color: Colors.purple,
                borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              ),
              child: Row(
                children: [
                  const Expanded(child: Text('নতুন কুপন তৈরি করুন', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700))),
                  IconButton(icon: const Icon(Icons.close, color: Colors.white), onPressed: () => Navigator.pop(context)),
                ],
              ),
            ),
            SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextField(
                    controller: _codeCtrl,
                    textCapitalization: TextCapitalization.characters,
                    decoration: InputDecoration(labelText: 'Coupon Code (যেমন: EID20)', border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)), prefixIcon: const Icon(Icons.local_offer)),
                  ),
                  const SizedBox(height: 14),
                  const Text('Discount Type', style: TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(child: RadioListTile<String>(
                        title: const Text('Percent (%)'),
                        value: 'percent',
                        groupValue: _type,
                        onChanged: (v) => setState(() => _type = v!),
                        activeColor: Colors.purple,
                        dense: true,
                      )),
                      Expanded(child: RadioListTile<String>(
                        title: const Text('Fixed (৳)'),
                        value: 'fixed',
                        groupValue: _type,
                        onChanged: (v) => setState(() => _type = v!),
                        activeColor: Colors.purple,
                        dense: true,
                      )),
                    ],
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _valueCtrl,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: _type == 'percent' ? 'Discount (%)' : 'Discount Amount (৳)',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(child: TextField(
                        controller: _minOrderCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(labelText: 'Min Order ৳', border: OutlineInputBorder(borderRadius: BorderRadius.circular(10))),
                      )),
                      const SizedBox(width: 10),
                      Expanded(child: TextField(
                        controller: _maxDiscountCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(labelText: 'Max Discount ৳', border: OutlineInputBorder(borderRadius: BorderRadius.circular(10))),
                      )),
                    ],
                  ),
                  const SizedBox(height: 14),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.calendar_today, color: Colors.purple),
                    title: Text(
                      _validUntil == null ? 'Expiry Date (Optional)' : 'Expires: ${_validUntil!.day}/${_validUntil!.month}/${_validUntil!.year}',
                      style: TextStyle(color: _validUntil == null ? Colors.grey : Colors.black87),
                    ),
                    onTap: () async {
                      final d = await showDatePicker(
                        context: context,
                        initialDate: DateTime.now().add(const Duration(days: 30)),
                        firstDate: DateTime.now(),
                        lastDate: DateTime.now().add(const Duration(days: 365 * 3)),
                      );
                      if (d != null) setState(() => _validUntil = d);
                    },
                    trailing: _validUntil != null
                        ? IconButton(icon: const Icon(Icons.close), onPressed: () => setState(() => _validUntil = null))
                        : null,
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton(
                      onPressed: _isSaving ? null : _submit,
                      style: ElevatedButton.styleFrom(backgroundColor: Colors.purple, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                      child: _isSaving
                          ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text('কুপন তৈরি করুন', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
