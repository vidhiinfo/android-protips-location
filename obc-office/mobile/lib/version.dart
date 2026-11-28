/// Keep in step with `version:` in pubspec.yaml. Used to tell users when the
/// office has uploaded a newer app (Administration → Mobile App on the portal).
const kAppVersion = '1.1.0';

/// True when [latest] (e.g. "1.2.0") is newer than [current].
bool isNewerVersion(String? latest, [String current = kAppVersion]) {
  if (latest == null || latest.trim().isEmpty) return false;
  List<int> parts(String v) => v.trim().split(RegExp(r'[.+\-]')).map((p) => int.tryParse(p) ?? 0).toList();
  final a = parts(latest), b = parts(current);
  for (var i = 0; i < 3; i++) {
    final x = i < a.length ? a[i] : 0, y = i < b.length ? b[i] : 0;
    if (x != y) return x > y;
  }
  return false;
}
