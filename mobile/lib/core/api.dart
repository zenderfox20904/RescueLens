import 'dart:convert';
import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Thin client for the RescueLens REST API. Base URL comes from --dart-define=API_URL.
class Api {
  static const _override = String.fromEnvironment('API_BASE_URL', defaultValue: String.fromEnvironment('API_URL'));
  /// Override with --dart-define=API_BASE_URL. Android emulators reach the host at 10.0.2.2; iOS simulator, macOS and web use 127.0.0.1.
  static String get base {
    if (_override.isNotEmpty) return _override;
    final host = (!kIsWeb && Platform.isAndroid) ? '10.0.2.2' : '127.0.0.1';
    return 'http://$host:8000/api/v1';
  }
  static String? token;

  static Future<void> load() async => token = (await SharedPreferences.getInstance()).getString('rl_token');
  static Future<void> setToken(String? t) async {
    token = t;
    final p = await SharedPreferences.getInstance();
    t == null ? await p.remove('rl_token') : await p.setString('rl_token', t);
  }

  static Map<String, String> get _h => {'Accept': 'application/json', 'Content-Type': 'application/json', if (token != null) 'Authorization': 'Bearer $token'};

  static dynamic _decode(http.Response r) {
    final body = r.body.isEmpty ? null : jsonDecode(r.body);
    if (r.statusCode >= 400) throw ApiException(body is Map && body['message'] is String ? body['message'] as String : 'HTTP ${r.statusCode}');
    return body;
  }

  static Future<dynamic> get(String path) async => _decode(await http.get(Uri.parse('$base$path'), headers: _h).timeout(const Duration(seconds: 15)));
  static Future<dynamic> post(String path, Map<String, dynamic> body, {Map<String, String>? headers}) async =>
      _decode(await http.post(Uri.parse('$base$path'), headers: {..._h, ...?headers}, body: jsonEncode(body)));

  static Future<dynamic> upload(String path, String filePath) async {
    final req = http.MultipartRequest('POST', Uri.parse('$base$path'))
      ..headers.addAll({'Accept': 'application/json', if (token != null) 'Authorization': 'Bearer $token'})
      ..files.add(await http.MultipartFile.fromPath('image', filePath));
    return _decode(await http.Response.fromStream(await req.send()));
  }
}

class ApiException implements Exception {
  final String message;
  ApiException(this.message);
  @override
  String toString() => message;
}
