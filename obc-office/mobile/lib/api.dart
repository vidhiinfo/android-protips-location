import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:image_picker/image_picker.dart';

/// Thrown for any failed API call; [fieldErrors] holds Laravel validation errors (HTTP 422).
class ApiException implements Exception {
  ApiException(this.message, {this.status, this.fieldErrors = const {}});

  final String message;
  final int? status;
  final Map<String, String> fieldErrors;

  @override
  String toString() => message;
}

/// Thin wrapper around the Laravel REST API (/api/v1) with Sanctum bearer tokens.
class Api {
  Api._();

  static final Api instance = Api._();

  /// Set at build time: flutter build apk --dart-define=API_BASE_URL=https://office.example.org
  static const defaultBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'https://electionsoft.in/obc');

  final _storage = const FlutterSecureStorage();
  late Dio _dio;
  String baseUrl = defaultBaseUrl;
  String? token;

  /// Called when the server rejects the token (expired, password changed, account disabled).
  VoidCallback? onUnauthorized;

  Future<void> init() async {
    baseUrl = await _storage.read(key: 'base_url') ?? defaultBaseUrl;
    token = await _storage.read(key: 'token');
    _build();
  }

  void _build() {
    _dio = Dio(BaseOptions(
      baseUrl: '$baseUrl/api/v1',
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 60),
      headers: {'Accept': 'application/json'},
    ));
    _dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
      if (token != null) o.headers['Authorization'] = 'Bearer $token';
      h.next(o);
    }));
  }

  String apiUrl(String path) => '$baseUrl/api/v1$path';

  /// Downloads a protected file with the bearer token; returns bytes and the server's filename.
  Future<(List<int>, String?)> downloadBytes(String url) async {
    try {
      final r = await _dio.get<List<int>>(url, options: Options(responseType: ResponseType.bytes));
      final cd = r.headers.value('content-disposition') ?? '';
      final name = RegExp(r'filename="?([^";]+)"?').firstMatch(cd)?.group(1);
      return (r.data ?? <int>[], name);
    } on DioException catch (e) {
      throw ApiException(e.response?.statusCode == 404 ? 'File not found.' : 'Could not download the file.');
    }
  }

  Map<String, String> get imageHeaders => token == null ? {} : {'Authorization': 'Bearer $token'};

  Future<void> setBaseUrl(String url) async {
    baseUrl = url.trim().replaceAll(RegExp(r'/+$'), '');
    await _storage.write(key: 'base_url', value: baseUrl);
    _build();
  }

  Future<void> saveToken(String? value) async {
    token = value;
    if (value == null) {
      await _storage.delete(key: 'token');
    } else {
      await _storage.write(key: 'token', value: value);
    }
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _wrap(() => _dio.get(path, queryParameters: _clean(query)));

  Future<dynamic> post(String path, {Object? data}) => _wrap(() => _dio.post(path, data: data));

  Future<dynamic> patch(String path, {Object? data}) => _wrap(() => _dio.patch(path, data: data));

  /// Builds a multipart body. Values: String/num/bool, Map (sent as `key[sub]`), XFile (file), `List<XFile>` (`key[]`).
  static Future<FormData> form(Map<String, dynamic> values) async {
    final fd = FormData();
    Future<void> add(String key, dynamic v) async {
      if (v == null) return;
      if (v is XFile) {
        fd.files.add(MapEntry(key, MultipartFile.fromBytes(await v.readAsBytes(), filename: v.name)));
      } else if (v is List<XFile>) {
        for (final f in v) {
          await add('$key[]', f);
        }
      } else if (v is Map) {
        for (final e in v.entries) {
          await add('$key[${e.key}]', e.value);
        }
      } else if (v is bool) {
        fd.fields.add(MapEntry(key, v ? '1' : '0'));
      } else {
        final s = v.toString();
        if (s.isNotEmpty) fd.fields.add(MapEntry(key, s));
      }
    }

    for (final e in values.entries) {
      await add(e.key, e.value);
    }
    return fd;
  }

  Map<String, dynamic>? _clean(Map<String, dynamic>? q) =>
      q == null ? null : (Map.of(q)..removeWhere((k, v) => v == null || v.toString().isEmpty));

  Future<dynamic> _wrap(Future<Response> Function() call) async {
    try {
      return (await call()).data;
    } on DioException catch (e) {
      final res = e.response;
      if (res == null) {
        throw ApiException('Cannot reach the server. Please check your internet connection.');
      }
      final data = res.data;
      final msg = data is Map && data['message'] is String ? data['message'] as String : 'Request failed (${res.statusCode}).';
      if (res.statusCode == 401) {
        onUnauthorized?.call();
        throw ApiException('Your session has expired. Please sign in again.', status: 401);
      }
      final fieldErrors = <String, String>{};
      if (res.statusCode == 422 && data is Map && data['errors'] is Map) {
        (data['errors'] as Map).forEach((k, v) => fieldErrors[k.toString()] = (v is List && v.isNotEmpty) ? v.first.toString() : v.toString());
      }
      throw ApiException(
        res.statusCode == 403 ? 'You do not have access to this.' : msg,
        status: res.statusCode,
        fieldErrors: fieldErrors,
      );
    }
  }
}

/// Reads Laravel paginator JSON (either {data, meta:{last_page}} or {data, last_page}).
class ApiPage {
  ApiPage(this.items, this.lastPage);

  factory ApiPage.fromJson(dynamic json) {
    final map = json as Map<String, dynamic>;
    final items = (map['data'] as List).cast<Map<String, dynamic>>();
    final last = (map['meta']?['last_page'] ?? map['last_page'] ?? 1) as int;
    return ApiPage(items, last);
  }

  final List<Map<String, dynamic>> items;
  final int lastPage;
}
