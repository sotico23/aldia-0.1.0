import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:aldiaproyect_mobile/models/order_model.dart';
import 'package:aldiaproyect_mobile/services/realtime_service.dart';
import 'package:aldiaproyect_mobile/widgets/order_card.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  runApp(const RedClienteApp());
}

class RedClienteApp extends StatelessWidget {
  const RedClienteApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'RedCliente - Repartidor',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF059669)),
        useMaterial3: true,
      ),
      home: const RepartidorHomePage(),
    );
  }
}

class RepartidorHomePage extends StatefulWidget {
  const RepartidorHomePage({super.key});

  @override
  State<RepartidorHomePage> createState() => _RepartidorHomePageState();
}

class _RepartidorHomePageState extends State<RepartidorHomePage> {
  final RealtimeService _realtime = RealtimeService();
  final List<Order> _orders = [];
  bool _isLoading = true;
  bool _isConnected = false;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    await _realtime.init();
    await _realtime.connect();

    _realtime.statusStream.listen((status) {
      if (!mounted) return;
      setState(() {
        _isConnected = status == RealtimeConnectionStatus.connected ||
            status == RealtimeConnectionStatus.authenticated;
      });
    });

    _realtime.eventStream.listen((event) {
      if (!mounted) return;
      debugPrint('Evento recibido: ${event.event} en ${event.channel}');
    });

    await _cargarOrdenes();

    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _cargarOrdenes() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('auth_token');

    if (token == null) {
      if (!mounted) return;
      setState(() {
        _orders.addAll([
          Order(
            id: 1,
            numeroPedido: 'PED-001',
            estado: 'preparando',
            dispatchMode: 'internal',
            clienteNombre: 'Juan Pérez',
            clienteTelefono: '+56 9 1234 5678',
            direccionCliente: 'Av. Libertador 1234, Santiago',
            costoEnvio: 2500,
            gananciaEstimada: 500,
            metodoPago: 'tarjeta',
            subtotal: 15000,
            impuesto: 2850,
            total: 17850,
            createdAt: DateTime.now().toIso8601String(),
          ),
          Order(
            id: 2,
            numeroPedido: 'PED-002',
            estado: 'pendiente',
            dispatchMode: 'marketplace',
            clienteNombre: 'María González',
            clienteTelefono: '+56 9 8765 4321',
            direccionCliente: 'Calle Mayor 567, Providencia',
            costoEnvio: 3000,
            gananciaEstimada: 600,
            metodoPago: 'efectivo',
            subtotal: 22000,
            impuesto: 4180,
            total: 26180,
            createdAt: DateTime.now().toIso8601String(),
          ),
        ]);
      });
      return;
    }

    try {
      final orders = await _apiFetchOrders(token);
      if (!mounted) return;
      setState(() {
        _orders.clear();
        _orders.addAll(orders);
      });
    } catch (e) {
      debugPrint('Error cargando órdenes: $e');
    }
  }

  Future<List<Order>> _apiFetchOrders(String token) async {
    final response = await http.get(
      Uri.parse('https://redcliente.cl/api/v1/delivery/orders'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    ).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final pool = data['pool'] as List<dynamic>? ?? [];
      return pool.map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
    }
    return [];
  }

  @override
  void dispose() {
    _realtime.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Repartidor - RedCliente'),
        actions: [
          IconButton(
            icon: Icon(_isConnected ? Icons.wifi : Icons.wifi_off),
            onPressed: _realtime.connect,
            tooltip: _isConnected ? 'Conectado' : 'Desconectado',
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _cargarOrdenes,
            tooltip: 'Refrescar',
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : (_orders.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.inbox, size: 64, color: Colors.grey),
                      SizedBox(height: 16),
                      Text(
                        'No hay pedidos disponibles',
                        style: TextStyle(fontSize: 18, color: Colors.grey),
                      ),
                      Text(
                        'Los pedidos aparecerán aquí en tiempo real.',
                        style: TextStyle(fontSize: 14, color: Colors.grey),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _cargarOrdenes,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(8),
                    itemCount: _orders.length,
                    itemBuilder: (context, index) {
                      return OrderCard(
                        order: _orders[index],
                        onAccepted: () {
                          setState(() {
                            _orders.removeAt(index);
                          });
                        },
                        onRejected: () {
                          setState(() {
                            _orders.removeAt(index);
                          });
                        },
                        onOrderUpdated: () {
                          setState(() {});
                        },
                      );
                    },
                  ),
                )),
      floatingActionButton: FloatingActionButton(
        onPressed: _cargarOrdenes,
        child: const Icon(Icons.refresh),
      ),
    );
  }
}
