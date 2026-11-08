import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:aldiaproyect_mobile/models/order_model.dart';
import 'package:aldiaproyect_mobile/config/env_config.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;

  ApiService._internal();

  final String baseUrl = EnvConfig.instance.apiBaseUrl;

  /// Obtiene la lista de órdenes disponibles en el pool para el repartidor autenticado.
  Future<List<Order>> fetchOrders({String? token}) async {
    final headers = <String, String>{
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };

    final response = await http.get(
      Uri.parse('$baseUrl/v1/delivery/orders'),
      headers: headers,
    ).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final pool = data['pool'] as List<dynamic>? ?? [];
      return pool.map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
    } else if (response.statusCode == 401) {
      throw Exception('No autorizado. Sesión expirada.');
    } else {
      throw Exception('Error al cargar órdenes: ${response.statusCode}');
    }
  }

  /// Acepta un pedido del pool.
  Future<void> acceptOrder({
    required int orderId,
    required String token,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/v1/delivery/orders/$orderId/aceptar'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    ).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      return;
    } else if (response.statusCode == 401) {
      throw Exception('No autorizado.');
    } else {
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      throw Exception(data['message'] ?? 'Error al aceptar pedido.');
    }
  }

  /// Rechaza un pedido y lo devuelve al pool.
  Future<void> rejectOrder({
    required int orderId,
    required String token,
    String? motivo,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/v1/delivery/orders/$orderId/rechazar'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'motivo': motivo ?? ''}),
    ).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      return;
    } else if (response.statusCode == 401) {
      throw Exception('No autorizado.');
    } else {
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      throw Exception(data['message'] ?? 'Error al rechazar pedido.');
    }
  }
}
