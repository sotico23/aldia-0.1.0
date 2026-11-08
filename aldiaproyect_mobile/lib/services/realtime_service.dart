import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:web_socket_channel/io.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'package:aldiaproyect_mobile/config/env_config.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Evento recibido por WebSocket de Reverb.
class RealtimeEvent {
  final String channel;
  final String event;
  final Map<String, dynamic> data;

  RealtimeEvent({
    required this.channel,
    required this.event,
    required this.data,
  });

  factory RealtimeEvent.fromJson(Map<String, dynamic> json) {
    return RealtimeEvent(
      channel: json['channel'] as String? ?? '',
      event: json['event'] as String? ?? '',
      data: (json['data'] as Map<String, dynamic>?) ?? {},
    );
  }
}

/// Estado de la conexión WebSocket.
enum RealtimeConnectionStatus {
  disconnected,
  connecting,
  connected,
  authenticated,
  error,
}

/// Servicio de tiempo real usando Laravel Reverb (Pusher-compatible).
/// Gestiona la conexión WebSocket segura (WSS), autenticación de canales
/// privados con Sanctum, suscripción a canales de pool y repartidor, y
/// reenvío de eventos entrantes mediante Stream.
class RealtimeService {
  static final RealtimeService _instance = RealtimeService._internal();
  factory RealtimeService() => _instance;

  RealtimeService._internal();

  final EnvConfig _env = EnvConfig();

  WebSocketChannel? _channel;
  Timer? _reconnectTimer;
  Timer? _pingTimer;

  final _statusController = StreamController<RealtimeConnectionStatus>.broadcast();
  final _eventController = StreamController<RealtimeEvent>.broadcast();

  Stream<RealtimeConnectionStatus> get statusStream => _statusController.stream;
  Stream<RealtimeEvent> get eventStream => _eventController.stream;

  RealtimeConnectionStatus _status = RealtimeConnectionStatus.disconnected;
  RealtimeConnectionStatus get status => _status;

  String? _socketId;
  String? _authToken;
  int? _ownerId;
  int? _userId;

  bool _isDisposing = false;
  int _reconnectAttempts = 0;
  static const int _maxReconnectAttempts = 10;

  /// Inicializa el servicio: carga config, obtiene token y usuario.
  Future<void> init() async {
    await _env.load();
    await _loadAuthData();
    _status = RealtimeConnectionStatus.disconnected;
    _statusController.add(_status);
  }

  Future<void> _loadAuthData() async {
    final prefs = await SharedPreferences.getInstance();
    _authToken = prefs.getString('auth_token');
    _userId = prefs.getInt('user_id');
    _ownerId = prefs.getInt('owner_id');
  }

  /// Guarda datos de autenticación (llamar después del login).
  Future<void> setAuthData({
    required String token,
    required int userId,
    required int ownerId,
  }) async {
    _authToken = token;
    _userId = userId;
    _ownerId = ownerId;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
    await prefs.setInt('user_id', userId);
    await prefs.setInt('owner_id', ownerId);

    // Si ya está conectado, reconectar para aplicar nuevo token.
    if (_status == RealtimeConnectionStatus.connected ||
        _status == RealtimeConnectionStatus.authenticated) {
      await disconnect();
      await connect();
    }
  }

  /// Conecta al WebSocket de Reverb (WSS seguro).
  Future<void> connect() async {
    if (_status == RealtimeConnectionStatus.connecting ||
        _status == RealtimeConnectionStatus.connected) {
      return;
    }

    _status = RealtimeConnectionStatus.connecting;
    _statusController.add(_status);

    try {
      final wsUrl = _env.reverbWsUrl;
      _channel = IOWebSocketChannel.connect(Uri.parse(wsUrl));

      _channel!.stream.listen(
        _onMessage,
        onError: _onError,
        onDone: _onDone,
        cancelOnError: false,
      );

      // Esperar un breve momento para recibir el evento de bienvenida con socket_id.
      await Future.delayed(const Duration(seconds: 2));

      if (_status == RealtimeConnectionStatus.connecting) {
        // Si no recibimos welcome, asumimos conectado (algunos servidores no envían welcome).
        _status = RealtimeConnectionStatus.connected;
        _statusController.add(_status);
        _startPing();
      }
    } catch (e) {
      _status = RealtimeConnectionStatus.error;
      _statusController.add(_status);
      _scheduleReconnect();
    }
  }

  void _onMessage(dynamic message) {
    try {
      final json = jsonDecode(message.toString()) as Map<String, dynamic>;
      final event = json['event'] as String?;

      // Evento de bienvenida de Pusher/Reverb: contiene socket_id.
      if (event == 'pusher:connection_established') {
        final data = json['data'] as Map<String, dynamic>?;
        _socketId = data?['socket_id'] as String?;
        _status = RealtimeConnectionStatus.connected;
        _statusController.add(_status);
        _startPing();
        _subscribeToChannels();
        return;
      }

      // Error de suscripción (canal privado requiere autenticación).
      if (event == 'pusher:error') {
        final data = json['data'] as Map<String, dynamic>?;
        final code = data?['code'] as int?;

        if (code == 403) {
          // Canal privado: necesitaría autenticación.
          final channel = data?['channel'] as String?;
          _authenticateChannel(channel);
        }
        return;
      }

      // Evento de suscripción exitosa.
      if (event == 'pusher:subscription_succeeded') {
        _status = RealtimeConnectionStatus.authenticated;
        _statusController.add(_status);
        return;
      }

      // Cualquier otro evento de aplicación lo reenviamos.
      _eventController.add(RealtimeEvent.fromJson(json));
    } catch (e) {
      debugPrint('RealtimeService: Error parseando mensaje: $e');
    }
  }

  /// Autentica un canal privado llamando al endpoint /broadcasting/auth de Laravel.
  Future<void> _authenticateChannel(String? channel) async {
    if (channel == null || _socketId == null || _authToken == null) return;

    try {
      final response = await http.post(
        Uri.parse(_env.reverbAuthUrl),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $_authToken',
        },
        body: jsonEncode({
          'socket_id': _socketId,
          'channel_name': channel,
        }),
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final authData = jsonDecode(response.body) as Map<String, dynamic>;
        final authSignature = authData['auth'] as String?;

        if (authSignature != null && _channel != null) {
          _channel!.sink.add(jsonEncode({
            'event': 'pusher:subscribe',
            'data': {
              'channel': channel,
              'auth': authSignature,
            },
          }));
        }
      }
    } catch (e) {
      debugPrint('RealtimeService: Error autenticando canal: $e');
    }
  }

  /// Suscribe a los canales necesarios:
  /// - delivery.pool.{ownerId}: bolsa general de ofertas.
  /// - delivery.driver.{userId}: asignación directa al repartidor.
  Future<void> _subscribeToChannels() async {
    if (_channel == null) return;

    if (_ownerId != null) {
      _channel!.sink.add(jsonEncode({
        'event': 'pusher:subscribe',
        'data': {
          'channel': 'delivery.pool.$_ownerId',
        },
      }));
    }

    if (_userId != null) {
      _channel!.sink.add(jsonEncode({
        'event': 'pusher:subscribe',
        'data': {
          'channel': 'delivery.driver.$_userId',
        },
      }));
    }
  }

  void _onError(dynamic error) {
    debugPrint('RealtimeService: Error WebSocket: $error');
    _status = RealtimeConnectionStatus.error;
    _statusController.add(_status);
    _scheduleReconnect();
  }

  void _onDone() {
    debugPrint('RealtimeService: WebSocket cerrado.');
    _status = RealtimeConnectionStatus.disconnected;
    _statusController.add(_status);
    _stopPing();
    _scheduleReconnect();
  }

  void _startPing() {
    _stopPing();
    _pingTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (_channel != null && _status == RealtimeConnectionStatus.connected) {
        _channel!.sink.add(jsonEncode({
          'event': 'pusher:ping',
          'data': {},
        }));
      }
    });
  }

  void _stopPing() {
    _pingTimer?.cancel();
    _pingTimer = null;
  }

  void _scheduleReconnect() {
    if (_isDisposing) return;
    if (_reconnectAttempts >= _maxReconnectAttempts) {
      debugPrint('RealtimeService: Máximo de intentos de reconexión alcanzado.');
      return;
    }

    _reconnectAttempts++;
    final delay = Duration(seconds: 5 * _reconnectAttempts);
    debugPrint('RealtimeService: Reconectando en ${delay.inSeconds}s...');

    _reconnectTimer = Timer(delay, () async {
      await connect();
    });
  }

  /// Desconecta y limpia recursos.
  Future<void> disconnect() async {
    _isDisposing = true;
    _stopPing();
    _reconnectTimer?.cancel();
    _reconnectTimer = null;

    await _channel?.sink.close();
    _channel = null;

    _status = RealtimeConnectionStatus.disconnected;
    _statusController.add(_status);
    _socketId = null;
  }

  /// Limpia todos los controladores (llamar al.dispose del widget principal).
  void dispose() {
    _isDisposing = true;
    _stopPing();
    _reconnectTimer?.cancel();
    _channel?.sink.close();
    _statusController.close();
    _eventController.close();
  }

  /// Suscribe a un canal adicional (útil para canales dinámicos).
  Future<void> subscribe(String channel) async {
    if (_channel == null) return;
    _channel!.sink.add(jsonEncode({
      'event': 'pusher:subscribe',
      'data': {'channel': channel},
    }));
  }

  /// Envía un evento personalizado al canal (si es necesario).
  Future<void> sendEvent(String channel, String eventName, Map<String, dynamic> data) async {
    if (_channel == null) return;
    _channel!.sink.add(jsonEncode({
      'event': eventName,
      'data': {...data, 'channel': channel},
    }));
  }
}
