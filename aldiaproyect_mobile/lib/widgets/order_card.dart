import 'package:flutter/material.dart';
import 'package:aldiaproyect_mobile/models/order_model.dart';
import 'package:aldiaproyect_mobile/services/realtime_service.dart';
import 'package:aldiaproyect_mobile/services/api_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Tarjeta de pedido que reacciona a eventos en tiempo real de WebSocket.
/// Cuando se recibe un evento de asignación directa (`delivery.entrega.asignada`)
/// o de actualización de estado del pool, la tarjeta se actualiza inmediatamente.
class OrderCard extends StatefulWidget {
  final Order order;
  final VoidCallback? onAccepted;
  final VoidCallback? onRejected;
  final VoidCallback? onOrderUpdated;

  const OrderCard({
    super.key,
    required this.order,
    this.onAccepted,
    this.onRejected,
    this.onOrderUpdated,
  });

  @override
  State<OrderCard> createState() => _OrderCardState();
}

class _OrderCardState extends State<OrderCard> {
  late Order _order;
  bool _isAccepting = false;
  bool _isRejecting = false;
  String? _authToken;
  final TextEditingController _motivoController = TextEditingController();

  final RealtimeService _realtime = RealtimeService();
  final ApiService _api = ApiService();

  @override
  void initState() {
    super.initState();
    _order = widget.order;
    _init();
  }

  Future<void> _init() async {
    final prefs = await SharedPreferences.getInstance();
    _authToken = prefs.getString('auth_token');

    // Suscribirse a eventos de tiempo real.
    _realtime.eventStream.listen((event) {
      if (!mounted) return;

      // Procesar eventos de asignación directa a este repartidor.
      if (event.event == 'delivery.entrega.asignada' ||
          event.event == 'delivery.pool.available') {
        final data = event.data;
        final entregaId = data['entrega_id']?.toString();

        if (entregaId != null && entregaId == widget.order.id.toString()) {
          _handleOrderUpdate(data);
        }
      }

      // También escuchar eventos de actualización de estado del repartidor.
      if (event.event == 'repartidor.estado.actualizado') {
        final data = event.data;
        final userId = data['user_id']?.toString();
        if (userId != null && userId == widget.order.repartidorId) {
          _handleOrderUpdate(data);
        }
      }
    });

    setState(() {});
  }

  void _handleOrderUpdate(Map<String, dynamic> data) {
    final nuevoEstado = data['estado'] as String?;
    final nuevoRepartidor = data['repartidor_id']?.toString();

    setState(() {
      _order = _order.copyWith(
        estado: nuevoEstado ?? _order.estado,
        repartidorId: nuevoRepartidor ?? _order.repartidorId,
      );
    });

    widget.onOrderUpdated?.call();

    if (nuevoEstado == 'enviado') {
      _showToast('Pedido asignado y en ruta.');
    } else if (nuevoEstado == 'entregado') {
      _showToast('Pedido entregado.');
    }
  }

  void _showToast(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        duration: const Duration(seconds: 3),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _acceptOrder() async {
    if (_isAccepting || _authToken == null) return;

    setState(() => _isAccepting = true);

    try {
      await _api.acceptOrder(
        orderId: widget.order.id,
        token: _authToken!,
      );

      _showToast('Pedido aceptado.');
      widget.onAccepted?.call();

      // Refrescar desde la API para obtener estado actualizado.
      final orders = await _api.fetchOrders(token: _authToken!);
      final updated = orders.firstWhere(
        (o) => o.id == widget.order.id,
        orElse: () => _order,
      );
      setState(() => _order = updated);
    } catch (e) {
      _showToast(e.toString());
    } finally {
      if (mounted) setState(() => _isAccepting = false);
    }
  }

  Future<void> _rejectOrder() async {
    if (_isRejecting || _authToken == null) return;

    final motivo = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Rechazar pedido'),
        content: TextField(
          controller: _motivoController,
          decoration: const InputDecoration(
            hintText: 'Motivo (opcional)',
            border: OutlineInputBorder(),
          ),
          maxLines: 2,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            onPressed: () {
              Navigator.pop(context, _motivoController.text.trim());
            },
            child: const Text('Rechazar'),
          ),
        ],
      ),
    );

    _motivoController.clear();

    if (motivo == null) return;

    setState(() => _isRejecting = true);

    try {
      await _api.rejectOrder(
        orderId: widget.order.id,
        token: _authToken!,
        motivo: motivo,
      );

      _showToast('Pedido devuelto al pool.');
      widget.onRejected?.call();
    } catch (e) {
      _showToast(e.toString());
    } finally {
      if (mounted) setState(() => _isRejecting = false);
    }
  }

  @override
  void dispose() {
    _motivoController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _order.estado.statusColor;

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header con número de pedido y estado.
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Nuevo Pedido',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '#${widget.order.numeroPedido}',
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.circle,
                        size: 8,
                        color: statusColor,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        _order.estado.statusLabel,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: statusColor,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),

            // Cliente y dirección.
            Row(
              children: [
                const Icon(Icons.person, size: 16, color: Colors.grey),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '${_order.clienteNombre} - ${_order.clienteTelefono}',
                    style: const TextStyle(fontSize: 14),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 8),

            Row(
              children: [
                const Icon(Icons.location_on, size: 16, color: Colors.grey),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _order.direccionCliente,
                    style: const TextStyle(fontSize: 14),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),

            if (_order.distanciaKm != null) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.straighten, size: 16, color: Colors.grey),
                  const SizedBox(width: 8),
                  Text(
                    '${_order.distanciaKm!.toStringAsFixed(1)} km',
                    style: const TextStyle(fontSize: 14),
                  ),
                ],
              ),
            ],

            const SizedBox(height: 12),

            // Precios y ganancia.
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _buildPriceItem('Envío', '\$${_order.costoEnvio.toStringAsFixed(0)}'),
                _buildPriceItem('Total', '\$${_order.total.toStringAsFixed(0)}', isBold: true),
                if (_order.gananciaEstimada != null)
                  _buildPriceItem(
                    'Ganancia',
                    '\$${_order.gananciaEstimada!.toStringAsFixed(0)}',
                    color: Colors.green,
                  ),
              ],
            ),

            const SizedBox(height: 16),

            // Botones de acción (solo si está en estado disponible/preparando).
            if (_order.estado.toOrderStatus() == OrderStatus.preparando ||
                _order.estado.toOrderStatus() == OrderStatus.pendiente)
              Row(
                children: [
                  Expanded(
                    flex: 1,
                    child: OutlinedButton.icon(
                      onPressed: _isRejecting ? null : _rejectOrder,
                      icon: _isRejecting
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                              ),
                            )
                          : const Icon(Icons.close, size: 16),
                      label: Text(_isRejecting ? 'Rechazando...' : 'Rechazar'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.red,
                        side: const BorderSide(color: Colors.red),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 1,
                    child: ElevatedButton.icon(
                      onPressed: _isAccepting ? null : _acceptOrder,
                      icon: _isAccepting
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                              ),
                            )
                          : const Icon(Icons.check, size: 16),
                      label: Text(_isAccepting ? 'Aceptando...' : 'Aceptar'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildPriceItem(String label, String value,
      {bool isBold = false, Color? color}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 10,
            color: color ?? Colors.grey[600],
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: TextStyle(
            fontSize: 14,
            fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
            color: isBold ? Colors.black : Colors.grey[800],
          ),
        ),
      ],
    );
  }
}
