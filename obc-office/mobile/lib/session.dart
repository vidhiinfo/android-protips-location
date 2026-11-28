import 'dart:convert';

import 'package:material_ui/material_ui.dart';

import 'api.dart';

/// Signed-in user, effective permissions, branding and master data (/meta).
class Session extends ChangeNotifier {
  Map<String, dynamic>? user;
  Map<String, dynamic> meta = {};
  bool ready = false;

  bool get signedIn => user != null;

  /// Public branding (works before sign-in): organisation, theme, leader profile, latest app version.
  Map<String, dynamic> publicBranding = {};

  Map<String, dynamic> get branding =>
      {...publicBranding, ...((user?['branding'] as Map?)?.cast<String, dynamic>() ?? {})};

  bool get showLeader => publicBranding['leader_on_login'] != false && (branding['leader_name'] ?? '').toString().isNotEmpty;

  List<String> get leaderDesignations =>
      ((branding['leader_designations'] as List?) ?? const []).map((e) => e.toString()).toList();

  /// Loads the cached copy first (so it shows offline), then refreshes from the server.
  Future<void> loadPublicBranding() async {
    try {
      final cached = await Api.instance.readCache('branding');
      if (cached != null) {
        publicBranding = (jsonDecode(cached) as Map).cast<String, dynamic>();
        notifyListeners();
      }
    } catch (_) {}
    try {
      final fresh = (await Api.instance.get('/branding') as Map).cast<String, dynamic>();
      publicBranding = fresh;
      await Api.instance.writeCache('branding', jsonEncode(fresh));
      notifyListeners();
    } catch (_) {
      // Offline: keep the cached copy.
    }
  }

  String get orgName => (branding['org_short_name'] as String?)?.isNotEmpty == true
      ? branding['org_short_name'] as String
      : (branding['org_name'] as String?) ?? 'Office';

  Color get themeColor {
    final hex = (branding['theme_color'] as String?) ?? '#ea580c';
    return Color(int.parse('ff${hex.replaceFirst('#', '')}', radix: 16));
  }

  String get role => user?['role'] as String? ?? '';

  bool get isNational => role == 'super_admin' || role == 'national_staff';

  int? get stateId => user?['state_id'] as int?;

  int? get districtId => user?['district_id'] as int?;

  String access(String module) => (user?['permissions'] as Map?)?[module] as String? ?? 'none';

  bool canView(String module) => access(module) != 'none';

  bool canManage(String module) => access(module) == 'manage';

  Future<void> restore() async {
    await Api.instance.init();
    Api.instance.onUnauthorized = () => _clear();
    loadPublicBranding(); // not awaited: the login screen updates when it arrives
    if (Api.instance.token != null) {
      try {
        await _load();
      } catch (_) {
        // Offline or token expired: show login; the token stays if it was just a network error.
      }
    }
    ready = true;
    notifyListeners();
  }

  Future<void> login(String username, String password) async {
    final res = await Api.instance.post('/login', data: {
      'username': username.trim(),
      'password': password,
      'device_name': 'mobile-app',
    });
    await Api.instance.saveToken(res['token'] as String);
    await _load();
  }

  Future<void> _load() async {
    user = (await Api.instance.get('/me') as Map).cast<String, dynamic>();
    meta = (await Api.instance.get('/meta') as Map).cast<String, dynamic>();
    notifyListeners();
  }

  Future<void> refreshMeta() async {
    await _load();
    await loadPublicBranding();
  }

  Future<void> logout() async {
    try {
      await Api.instance.post('/logout');
    } catch (_) {}
    await _clear();
  }

  Future<void> _clear() async {
    await Api.instance.saveToken(null);
    user = null;
    notifyListeners();
  }

  // ---- master data helpers -------------------------------------------------

  /// value → label for an admin-configured dropdown list.
  Map<String, String> lookups(String group) {
    final list = (meta['lookups']?[group] as List?) ?? const [];
    return {for (final o in list) o['value'].toString(): o['label'].toString()};
  }

  String label(String group, dynamic value) => value == null ? '' : (lookups(group)[value.toString()] ?? value.toString());

  List<Map<String, dynamic>> get states => ((meta['states'] as List?) ?? const []).cast<Map<String, dynamic>>();

  List<Map<String, dynamic>> districtsOf(int? stateId) =>
      ((meta['districts'] as List?) ?? const []).cast<Map<String, dynamic>>().where((d) => d['state_id'] == stateId).toList();

  List<Map<String, dynamic>> designationsFor(String? level) => ((meta['designations'] as List?) ?? const [])
      .cast<Map<String, dynamic>>()
      .where((d) => level == null || d['level'] == level)
      .toList();

  List<Map<String, dynamic>> customFields(String entity) =>
      ((meta['${entity}_custom_fields'] as List?) ?? const []).cast<Map<String, dynamic>>();

  List<String> requiredFields(String entity) =>
      ((meta['${entity}_required_fields'] as List?) ?? const []).map((e) => e.toString()).toList();
}
