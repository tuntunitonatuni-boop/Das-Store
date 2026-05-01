class OrderModel {
  final int id;
  final String invoiceNo;
  final double total;
  final double amountPaid;
  final String paymentMethod;
  final String status;
  final String? shippingAddress;
  final String? createdAt;

  OrderModel({
    required this.id,
    required this.invoiceNo,
    required this.total,
    required this.amountPaid,
    required this.paymentMethod,
    required this.status,
    this.shippingAddress,
    this.createdAt,
  });

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    return OrderModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      invoiceNo: json['invoice_no'] ?? '',
      total: double.tryParse(json['total'].toString()) ?? 0,
      amountPaid: double.tryParse(json['amount_paid'].toString()) ?? 0,
      paymentMethod: json['payment_method'] ?? 'cod',
      status: json['status'] ?? 'pending',
      shippingAddress: json['shipping_address'],
      createdAt: json['created_at'],
    );
  }

  String get statusLabel {
    switch (status) {
      case 'pending': return 'Pending';
      case 'confirmed': return 'Confirmed';
      case 'packaging': return 'Packaging';
      case 'delivered': return 'Delivered';
      case 'cancelled': return 'Cancelled';
      default: return status;
    }
  }
}
