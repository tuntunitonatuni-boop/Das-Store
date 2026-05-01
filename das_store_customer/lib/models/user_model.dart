class UserModel {
  final int id;
  final String name;
  final String username;
  final String phone;
  final String? address;

  UserModel({
    required this.id,
    required this.name,
    required this.username,
    required this.phone,
    this.address,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      name: json['name'] ?? '',
      username: json['username'] ?? '',
      phone: json['phone'] ?? '',
      address: json['address'],
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'username': username,
    'phone': phone,
    'address': address,
  };
}
