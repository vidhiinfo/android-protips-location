import 'package:material_ui/material_ui.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../api.dart';

/// Holds form values + server validation errors, and builds consistent inputs.
/// Values are kept as strings / XFiles keyed by the API field name.
class FormKit extends ChangeNotifier {
  FormKit(Map<String, dynamic> initial, {this.required = const []}) {
    initial.forEach((k, v) {
      if (v != null) values[k] = v is bool ? v : v.toString();
    });
  }

  final Map<String, dynamic> values = {};
  Map<String, String> errors = {};
  final List<String> required;
  final _controllers = <String, TextEditingController>{};

  String? str(String key) => values[key]?.toString();

  void set(String key, dynamic value) {
    values[key] = value;
    errors.remove(key);
    notifyListeners();
  }

  void applyErrors(ApiException e) {
    errors = e.fieldErrors;
    notifyListeners();
  }

  String _label(String key, String label) => required.contains(key) ? '$label *' : label;

  TextEditingController _ctl(String key) =>
      _controllers.putIfAbsent(key, () => TextEditingController(text: str(key) ?? ''));

  Widget text(String key, String label,
      {TextInputType? keyboard, int maxLines = 1, bool mustFill = false, String? hint}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: _ctl(key),
        keyboardType: keyboard ?? (maxLines > 1 ? TextInputType.multiline : TextInputType.text),
        maxLines: maxLines,
        textCapitalization: keyboard == null ? TextCapitalization.words : TextCapitalization.none,
        decoration: InputDecoration(
          labelText: mustFill ? '$label *' : _label(key, label),
          hintText: hint,
          errorText: errors[key],
          errorMaxLines: 3,
          border: const OutlineInputBorder(),
          isDense: true,
        ),
        onChanged: (v) {
          values[key] = v;
          if (errors.remove(key) != null) notifyListeners();
        },
      ),
    );
  }

  Widget dropdown(String key, String label, Map<String, String> options,
      {bool mustFill = false, ValueChanged<String?>? onChanged, bool enabled = true}) {
    final current = options.containsKey(str(key)) ? str(key) : null;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<String>(
        key: ValueKey('$key-$current-${options.length}'),
        initialValue: current,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: mustFill ? '$label *' : _label(key, label),
          errorText: errors[key],
          border: const OutlineInputBorder(),
          isDense: true,
        ),
        items: [
          const DropdownMenuItem<String>(value: null, child: Text('—')),
          ...options.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value, overflow: TextOverflow.ellipsis))),
        ],
        onChanged: enabled
            ? (v) {
                set(key, v);
                onChanged?.call(v);
              }
            : null,
      ),
    );
  }

  Widget date(BuildContext context, String key, String label, {bool mustFill = false, DateTime? first, DateTime? last}) {
    final v = str(key);
    final shown = v == null || v.isEmpty ? '' : DateFormat('d MMM y').format(DateTime.parse(v));
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () async {
          final picked = await showDatePicker(
            context: context,
            initialDate: v != null && v.isNotEmpty ? DateTime.parse(v) : (last ?? DateTime.now()),
            firstDate: first ?? DateTime(1930),
            lastDate: last ?? DateTime(DateTime.now().year + 5),
          );
          if (picked != null) set(key, DateFormat('yyyy-MM-dd').format(picked));
        },
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: mustFill ? '$label *' : _label(key, label),
            errorText: errors[key],
            border: const OutlineInputBorder(),
            isDense: true,
            suffixIcon: const Icon(Icons.calendar_today, size: 18),
          ),
          child: Text(shown.isEmpty ? ' ' : shown),
        ),
      ),
    );
  }

  Widget checkbox(String key, String label, {bool mustFill = false}) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            value: values[key] == true || values[key] == '1' || values[key] == 'true',
            onChanged: (v) => set(key, v ?? false),
            title: Text(mustFill ? '$label *' : label, style: const TextStyle(fontSize: 14)),
          ),
          if (errors[key] != null) Text(errors[key]!, style: const TextStyle(color: Colors.red, fontSize: 12)),
        ]),
      );

  /// Admin-defined custom fields (Settings → Custom fields), stored under extra[key].
  List<Widget> customFields(BuildContext context, List<Map<String, dynamic>> defs) {
    return defs.map((f) {
      final key = 'extra.${f['key']}';
      final label = f['label'].toString();
      final req = f['required'] == true;
      return switch (f['type']) {
        'select' => dropdown(key, label, {for (final o in (f['options'] as List)) o.toString(): o.toString()}, mustFill: req),
        'date' => date(context, key, label, mustFill: req),
        'checkbox' => checkbox(key, label, mustFill: req),
        'number' => text(key, label, keyboard: TextInputType.number, mustFill: req),
        'textarea' => text(key, label, maxLines: 3, mustFill: req),
        _ => text(key, label, mustFill: req),
      };
    }).toList();
  }

  /// Converts flat "extra.key" values into the nested map the API expects.
  Map<String, dynamic> payload() {
    final out = <String, dynamic>{};
    final extra = <String, dynamic>{};
    values.forEach((k, v) {
      if (k.startsWith('extra.')) {
        extra[k.substring(6)] = v;
      } else {
        out[k] = v;
      }
    });
    if (extra.isNotEmpty) out['extra'] = extra;
    return out;
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }
}

/// Lets the user take a photo with the camera or pick one from the gallery.
Future<XFile?> pickPhoto(BuildContext context, {int maxWidth = 1600}) async {
  final source = await showModalBottomSheet<ImageSource>(
    context: context,
    builder: (ctx) => SafeArea(
      child: Wrap(children: [
        ListTile(leading: const Icon(Icons.photo_camera), title: const Text('Take photo'), onTap: () => Navigator.pop(ctx, ImageSource.camera)),
        ListTile(leading: const Icon(Icons.photo_library), title: const Text('Choose from gallery'), onTap: () => Navigator.pop(ctx, ImageSource.gallery)),
      ]),
    ),
  );
  if (source == null) return null;
  return ImagePicker().pickImage(source: source, maxWidth: maxWidth.toDouble(), imageQuality: 85);
}
