class AdminUser {
  final int id;
  final String name;
  final String username;
  final String role;

  AdminUser({required this.id, required this.name, required this.username, required this.role});

  factory AdminUser.fromJson(Map<String, dynamic> json) => AdminUser(
    id: int.tryParse(json['id'].toString()) ?? 0,
    name: json['name'] ?? '',
    username: json['username'] ?? '',
    role: json['role'] ?? '',
  );

  Map<String, dynamic> toJson() => {'id': id, 'name': name, 'username': username, 'role': role};

  bool get isOwner => role == 'owner';
}
