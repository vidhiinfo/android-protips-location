import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:material_ui/material_ui.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api.dart';

String fmtDate(dynamic iso, [String pattern = 'd MMM y']) {
  if (iso == null || iso.toString().isEmpty) return '';
  final d = DateTime.tryParse(iso.toString());
  return d == null ? iso.toString() : DateFormat(pattern).format(d.toLocal());
}

String initials(String? name) {
  final parts = (name ?? '').trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
  if (parts.isEmpty) return '?';
  return (parts.first[0] + (parts.length > 1 ? parts.last[0] : '')).toUpperCase();
}

/// Phone numbers: open dialer / WhatsApp (Indian numbers assumed when no country code).
Future<void> callNumber(String? mobile) async {
  if (mobile == null || mobile.isEmpty) return;
  await launchUrl(Uri(scheme: 'tel', path: mobile));
}

Future<void> openWhatsApp(String? mobileOrUrl, [String text = '']) async {
  if (mobileOrUrl == null || mobileOrUrl.isEmpty) return;
  final uri = mobileOrUrl.startsWith('http')
      ? Uri.parse(mobileOrUrl)
      : () {
          var d = mobileOrUrl.replaceAll(RegExp(r'\D'), '');
          if (d.length == 10) d = '91$d';
          return Uri.parse('https://wa.me/$d${text.isEmpty ? '' : '?text=${Uri.encodeComponent(text)}'}');
        }();
  await launchUrl(uri, mode: LaunchMode.externalApplication);
}

/// Opens a protected PDF / Word / image file: downloads it with the token, then hands it to the phone's viewer.
Future<void> openDocument(BuildContext context, String url, String fallbackName) async {
  if (kIsWeb) {
    toast(context, 'Open this file from the web portal.');
    return;
  }
  toast(context, 'Opening…');
  try {
    final (bytes, serverName) = await Api.instance.downloadBytes(url);
    final dir = await getTemporaryDirectory();
    final name = (serverName ?? fallbackName).replaceAll(RegExp(r'[^\w.\- ]'), '_');
    final file = File('${dir.path}/$name');
    await file.writeAsBytes(bytes, flush: true);
    final res = await OpenFilex.open(file.path);
    if (res.type != ResultType.done && context.mounted) toast(context, 'No app found to open this file.', error: true);
  } on ApiException catch (e) {
    if (context.mounted) toast(context, e.message, error: true);
  }
}

void toast(BuildContext context, String msg, {bool error = false}) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
    content: Text(msg),
    backgroundColor: error ? Colors.red.shade700 : null,
    behavior: SnackBarBehavior.floating,
  ));
}

/// Photo that needs the bearer token (member photos are private on the server).
class AuthAvatar extends StatelessWidget {
  const AuthAvatar({super.key, required this.url, required this.name, this.radius = 22});

  final String? url;
  final String? name;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final fallback = Text(initials(name), style: TextStyle(color: scheme.primary, fontWeight: FontWeight.w600, fontSize: radius * 0.7));
    return CircleAvatar(
      radius: radius,
      backgroundColor: scheme.primaryContainer.withValues(alpha: 0.5),
      child: url == null
          ? fallback
          : ClipOval(
              child: Image.network(url!, headers: Api.instance.imageHeaders, width: radius * 2, height: radius * 2, fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => fallback),
            ),
    );
  }
}

class AuthImage extends StatelessWidget {
  const AuthImage({super.key, required this.url, this.fit = BoxFit.cover, this.height});

  final String url;
  final BoxFit fit;
  final double? height;

  @override
  Widget build(BuildContext context) => Image.network(url, headers: Api.instance.imageHeaders, fit: fit, height: height,
      errorBuilder: (_, _, _) => const Center(child: Icon(Icons.broken_image_outlined)));
}

class StatusChip extends StatelessWidget {
  const StatusChip(this.label, {super.key, this.color});

  final String label;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    if (label.isEmpty) return const SizedBox.shrink();
    final c = color ?? Theme.of(context).colorScheme.primary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: c.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20), border: Border.all(color: c.withValues(alpha: 0.3))),
      child: Text(label, style: TextStyle(fontSize: 11, color: c, fontWeight: FontWeight.w600)),
    );
  }
}

class ErrorView extends StatelessWidget {
  const ErrorView(this.message, {super.key, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_outlined, size: 48, color: Colors.grey),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            if (onRetry != null) ...[const SizedBox(height: 12), OutlinedButton(onPressed: onRetry, child: const Text('Try again'))],
          ]),
        ),
      );
}

class InfoRow extends StatelessWidget {
  const InfoRow(this.label, this.value, {super.key});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    if (value == null || value!.trim().isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: Theme.of(context).textTheme.labelSmall?.copyWith(color: Colors.grey.shade600)),
        const SizedBox(height: 2),
        Text(value!, style: Theme.of(context).textTheme.bodyMedium),
      ]),
    );
  }
}

class SectionCard extends StatelessWidget {
  const SectionCard({super.key, this.title, required this.children, this.trailing});

  final String? title;
  final List<Widget> children;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Card(
        margin: const EdgeInsets.fromLTRB(12, 6, 12, 6),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (title != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(children: [
                  Expanded(child: Text(title!, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700))),
                  ?trailing,
                ]),
              ),
            ...children,
          ]),
        ),
      );
}

/// Infinite-scrolling, pull-to-refresh list backed by a paginated API endpoint.
class PagedList extends StatefulWidget {
  const PagedList({super.key, required this.fetch, required this.itemBuilder, this.empty = 'Nothing here yet.', this.header});

  final Future<ApiPage> Function(int page) fetch;
  final Widget Function(BuildContext, Map<String, dynamic>) itemBuilder;
  final String empty;
  final Widget? header;

  @override
  State<PagedList> createState() => PagedListState();
}

class PagedListState extends State<PagedList> {
  final _items = <Map<String, dynamic>>[];
  int _page = 0;
  int _last = 1;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    reload();
  }

  Future<void> reload() async {
    setState(() {
      _items.clear();
      _page = 0;
      _last = 1;
      _error = null;
    });
    await _more();
  }

  Future<void> _more() async {
    if (_loading || _page >= _last) return;
    setState(() => _loading = true);
    try {
      final p = await widget.fetch(_page + 1);
      if (!mounted) return;
      setState(() {
        _items.addAll(p.items);
        _page++;
        _last = p.lastPage;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null && _items.isEmpty) return ErrorView(_error!, onRetry: reload);
    return RefreshIndicator(
      onRefresh: reload,
      child: NotificationListener<ScrollNotification>(
        onNotification: (n) {
          if (n.metrics.pixels > n.metrics.maxScrollExtent - 300) _more();
          return false;
        },
        child: ListView.builder(
          physics: const AlwaysScrollableScrollPhysics(),
          itemCount: _items.length + 2,
          itemBuilder: (ctx, i) {
            if (i == 0) return widget.header ?? const SizedBox.shrink();
            if (i == _items.length + 1) {
              if (_loading) return const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator()));
              if (_items.isEmpty) return Padding(padding: const EdgeInsets.all(48), child: Center(child: Text(widget.empty, style: const TextStyle(color: Colors.grey))));
              return const SizedBox(height: 80);
            }
            return widget.itemBuilder(ctx, _items[i - 1]);
          },
        ),
      ),
    );
  }
}

/// Search box that debounces input before calling [onChanged].
class SearchField extends StatefulWidget {
  const SearchField({super.key, required this.onChanged, this.hint = 'Search…'});

  final ValueChanged<String> onChanged;
  final String hint;

  @override
  State<SearchField> createState() => _SearchFieldState();
}

class _SearchFieldState extends State<SearchField> {
  final _c = TextEditingController();
  DateTime _last = DateTime.now();

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
        child: TextField(
          controller: _c,
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(
            hintText: widget.hint,
            prefixIcon: const Icon(Icons.search),
            isDense: true,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            suffixIcon: _c.text.isEmpty ? null : IconButton(icon: const Icon(Icons.clear), onPressed: () {
              _c.clear();
              widget.onChanged('');
              setState(() {});
            }),
          ),
          onSubmitted: widget.onChanged,
          onChanged: (v) {
            setState(() {});
            final stamp = _last = DateTime.now();
            Future.delayed(const Duration(milliseconds: 500), () {
              if (stamp == _last && mounted) widget.onChanged(v);
            });
          },
        ),
      );
}
