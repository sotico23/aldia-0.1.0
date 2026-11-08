import 'package:equatable/equatable.dart';
import 'package:flutter/material.dart';

class Order extends Equatable {
  final int id;
  final String numeroPedido;
  final String estado;
  final String dispatchMode;
  final String clienteNombre;
  final String clienteTelefono;
  final String direccionCliente;
  final double? destinoLat;
  final double? destinoLng;
  final double? distanciaKm;
  final double costoEnvio;
  final double? gananciaEstimada;
  final String metodoPago;
  final double subtotal;
  final double impuesto;
  final double total;
  final String? repartidorId;
  final String? repartidorNombre;
  final String createdAt;
  final List<Map<String, dynamic>> items;

  const Order({
    required this.id,
    required this.numeroPedido,
    required this.estado,
    required this.dispatchMode,
    required this.clienteNombre,
    required this.clienteTelefono,
    required this.direccionCliente,
    this.destinoLat,
    this.destinoLng,
    this.distanciaKm,
    required this.costoEnvio,
    this.gananciaEstimada,
    required this.metodoPago,
    required this.subtotal,
    required this.impuesto,
    required this.total,
    this.repartidorId,
    this.repartidorNombre,
    required this.createdAt,
    this.items = const [],
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'] as int,
      numeroPedido: json['numero_pedido'] ?? json['numero'] ?? '',
      estado: json['estado'] ?? '',
      dispatchMode: json['dispatch_mode'] ?? json['delivery_type'] ?? 'internal',
      clienteNombre: json['nombre_cliente'] ?? json['cliente'] ?? 'Cliente',
      clienteTelefono: json['telefono_cliente'] ?? json['telefono'] ?? '',
      direccionCliente: json['direccion_cliente'] ?? json['direccion'] ?? '',
      destinoLat: json['destino_lat']?.toDouble(),
      destinoLng: json['destino_lng']?.toDouble(),
      distanciaKm: json['distancia_km']?.toDouble(),
      costoEnvio: (json['costo_envio'] ?? 0).toDouble(),
      gananciaEstimada: json['ganancia_estimada']?.toDouble(),
      metodoPago: json['metodo_pago'] ?? '',
      subtotal: (json['subtotal'] ?? 0).toDouble(),
      impuesto: (json['impuesto'] ?? 0).toDouble(),
      total: (json['total'] ?? 0).toDouble(),
      repartidorId: json['repartidor_id']?.toString(),
      repartidorNombre: json['repartidor_nombre'],
      createdAt: json['created_at'] ?? '',
      items: (json['items'] as List<dynamic>?)
          ?.map((item) => item as Map<String, dynamic>)
          .toList() ?? [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'numero_pedido': numeroPedido,
      'estado': estado,
      'dispatch_mode': dispatchMode,
      'nombre_cliente': clienteNombre,
      'telefono_cliente': clienteTelefono,
      'direccion_cliente': direccionCliente,
      'destino_lat': destinoLat,
      'destino_lng': destinoLng,
      'distancia_km': distanciaKm,
      'costo_envio': costoEnvio,
      'ganancia_estimada': gananciaEstimada,
      'metodo_pago': metodoPago,
      'subtotal': subtotal,
      'impuesto': impuesto,
      'total': total,
      'repartidor_id': repartidorId,
      'repartidor_nombre': repartidorNombre,
      'created_at': createdAt,
      'items': items,
    };
  }

  Order copyWith({
    int? id,
    String? numeroPedido,
    String? estado,
    String? dispatchMode,
    String? clienteNombre,
    String? clienteTelefono,
    String? direccionCliente,
    double? destinoLat,
    double? destinoLng,
    double? distanciaKm,
    double? costoEnvio,
    double? gananciaEstimada,
    String? metodoPago,
    double? subtotal,
    double? impuesto,
    double? total,
    String? repartidorId,
    String? repartidorNombre,
    String? createdAt,
    List<Map<String, dynamic>>? items,
  }) {
    return Order(
      id: id ?? this.id,
      numeroPedido: numeroPedido ?? this.numeroPedido,
      estado: estado ?? this.estado,
      dispatchMode: dispatchMode ?? this.dispatchMode,
      clienteNombre: clienteNombre ?? this.clienteNombre,
      clienteTelefono: clienteTelefono ?? this.clienteTelefono,
      direccionCliente: direccionCliente ?? this.direccionCliente,
      destinoLat: destinoLat ?? this.destinoLat,
      destinoLng: destinoLng ?? this.destinoLng,
      distanciaKm: distanciaKm ?? this.distanciaKm,
      costoEnvio: costoEnvio ?? this.costoEnvio,
      gananciaEstimada: gananciaEstimada ?? this.gananciaEstimada,
      metodoPago: metodoPago ?? this.metodoPago,
      subtotal: subtotal ?? this.subtotal,
      impuesto: impuesto ?? this.impuesto,
      total: total ?? this.total,
      repartidorId: repartidorId ?? this.repartidorId,
      repartidorNombre: repartidorNombre ?? this.repartidorNombre,
      createdAt: createdAt ?? this.createdAt,
      items: items ?? this.items,
    );
  }

  @override
  List<Object?> get props => [
        id,
        numeroPedido,
        estado,
        dispatchMode,
        clienteNombre,
        clienteTelefono,
        direccionCliente,
        destinoLat,
        destinoLng,
        distanciaKm,
        costoEnvio,
        gananciaEstimada,
        metodoPago,
        subtotal,
        impuesto,
        total,
        repartidorId,
        repartidorNombre,
        createdAt,
        items,
      ];
}

enum OrderStatus {
  preparando,
  enviado,
  entregado,
  cancelado,
  pendiente,
}

extension OrderStatusExtension on String {
  OrderStatus toOrderStatus() {
    switch (toLowerCase()) {
      case 'preparando':
        return OrderStatus.preparando;
      case 'enviado':
        return OrderStatus.enviado;
      case 'entregado':
        return OrderStatus.entregado;
      case 'cancelado':
        return OrderStatus.cancelado;
      default:
        return OrderStatus.pendiente;
    }
  }

  Color get statusColor {
    switch (toOrderStatus()) {
      case OrderStatus.preparando:
        return const Color(0xFFF59E0B); // amber
      case OrderStatus.enviado:
        return const Color(0xFF3B82F6); // blue
      case OrderStatus.entregado:
        return const Color(0xFF10B981); // emerald
      case OrderStatus.cancelado:
        return const Color(0xFFEF4444); // red
      default:
        return const Color(0xFF6B7280); // gray
    }
  }

  String get statusLabel {
    switch (toOrderStatus()) {
      case OrderStatus.preparando:
        return 'Preparando';
      case OrderStatus.enviado:
        return 'En Ruta';
      case OrderStatus.entregado:
        return 'Entregado';
      case OrderStatus.cancelado:
        return 'Cancelado';
      default:
        return 'Pendiente';
    }
  }
}
