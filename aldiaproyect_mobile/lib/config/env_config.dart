import 'dart:convert';
import 'package:flutter/services.dart' show rootBundle;

class EnvConfig {
  static EnvConfig? _instance;
  factory EnvConfig() => _instance ??= EnvConfig._init();

  EnvConfig._init();

  late String apiBaseUrl;
  late String reverbHost;
  late int reverbPort;
  late bool reverbTls;
  late String reverbScheme;
  late String reverbAppKey;
  late String reverbCluster;
  late int pollingIntervalSeconds;

  Future<void> load() async {
    final jsonString = await rootBundle.loadString('config/env.json');
    final data = jsonDecode(jsonString) as Map<String, dynamic>;

    apiBaseUrl = data['API_BASE_URL'] as String? ?? 'https://redcliente.cl/api';
    reverbHost = data['REVERB_HOST'] as String? ?? 'redcliente.cl';
    reverbPort = data['REVERB_PORT'] as int? ?? 8080;
    reverbTls = data['REVERB_TLS'] as bool? ?? true;
    reverbScheme = data['REVERB_SCHEME'] as String? ?? 'wss';
    reverbAppKey = data['REVERB_APP_KEY'] as String? ?? '';
    reverbCluster = data['REVERB_CLUSTER'] as String? ?? 'mt1';
    pollingIntervalSeconds =
        data['POLLING_INTERVAL_SECONDS'] as int? ?? 20;
  }

  /// URL del WebSocket para Reverb (Pusher-compatible).
  String get reverbWsUrl {
    return '$reverbScheme://$reverbHost:$reverbPort/app/$reverbAppKey?protocol=7&client=flutter&version=1.0.0';
  }

  /// Endpoint de autenticación para canales privados.
  String get reverbAuthUrl {
    return '$apiBaseUrl/broadcasting/auth';
  }

  /// Instancia estática para acceso global sin necesidad de instanciar.
  static EnvConfig get instance => _instance!;
}
