class ProductModel {
  final int id;
  final String name;
  final String? description;
  final double salePrice;
  final double mrp;
  final String unit;
  final String? image;
  final String? imageUrl;
  final String? categoryName;
  final String? companyName;
  final int stock;
  final int categoryId;
  final String? barcode;
  // Loose product fields
  final int isLoose;
  final double? looseMinQty;
  final String? looseUnit;

  ProductModel({
    required this.id,
    required this.name,
    this.description,
    required this.salePrice,
    required this.mrp,
    required this.unit,
    this.image,
    this.imageUrl,
    this.categoryName,
    this.companyName,
    required this.stock,
    required this.categoryId,
    this.barcode,
    this.isLoose = 0,
    this.looseMinQty,
    this.looseUnit,
  });

  bool get inStock => stock > 0;
  bool get hasDiscount => mrp > salePrice && mrp > 0;
  int get discountPercent => mrp > 0 ? (((mrp - salePrice) / mrp) * 100).round() : 0;

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      name: json['name'] ?? '',
      description: json['description'],
      salePrice: double.tryParse(json['sale_price'].toString()) ?? 0,
      mrp: double.tryParse(json['mrp'].toString()) ?? 0,
      unit: json['unit'] ?? 'pcs',
      image: json['image'],
      imageUrl: json['image_url'],
      categoryName: json['category_name'],
      companyName: json['company_name'],
      stock: (double.tryParse(json['stock'].toString()) ?? 0.0).toInt(),
      categoryId: int.tryParse(json['category_id'].toString()) ?? 0,
      barcode: json['barcode'],
      isLoose: int.tryParse(json['is_loose'].toString()) ?? 0,
      looseMinQty: json['loose_min_qty'] != null ? double.tryParse(json['loose_min_qty'].toString()) : null,
      looseUnit: json['loose_unit'],
    );
  }
}
