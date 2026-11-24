import 'package:material_ui/material_ui.dart';

import '../api.dart';
import '../widgets/common.dart';

class CircularsScreen extends StatefulWidget {
  const CircularsScreen({super.key});

  @override
  State<CircularsScreen> createState() => _CircularsScreenState();
}

class _CircularsScreenState extends State<CircularsScreen> {
  final _list = GlobalKey<PagedListState>();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Circulars')),
        body: PagedList(
          key: _list,
          fetch: (p) async => ApiPage.fromJson(await Api.instance.get('/circulars', query: {'page': p})),
          empty: 'No circulars.',
          itemBuilder: (ctx, c) => ListTile(
            leading: Icon(c['read'] == true ? Icons.drafts_outlined : Icons.mark_email_unread, color: c['read'] == true ? Colors.grey : Theme.of(ctx).colorScheme.primary),
            title: Text(c['title'], style: TextStyle(fontWeight: c['read'] == true ? FontWeight.normal : FontWeight.w700)),
            subtitle: Text(fmtDate(c['created_at'])),
            trailing: c['has_attachment'] == true ? const Icon(Icons.attach_file, size: 18) : null,
            onTap: () async {
              await Navigator.push(ctx, MaterialPageRoute(builder: (_) => _CircularDetail(c)));
              _list.currentState?.reload();
            },
          ),
        ),
      );
}

class _CircularDetail extends StatefulWidget {
  const _CircularDetail(this.c);

  final Map<String, dynamic> c;

  @override
  State<_CircularDetail> createState() => _CircularDetailState();
}

class _CircularDetailState extends State<_CircularDetail> {
  @override
  void initState() {
    super.initState();
    Api.instance.post('/circulars/${widget.c['id']}/read').catchError((_) => null);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Circular')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          Text(widget.c['title'], style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(fmtDate(widget.c['created_at'], 'd MMM y, h:mm a'), style: TextStyle(color: Colors.grey.shade600)),
          const SizedBox(height: 16),
          SelectableText(widget.c['body'] ?? '', style: const TextStyle(height: 1.5)),
          if (widget.c['has_attachment'] == true) ...[
            const SizedBox(height: 20),
            OutlinedButton.icon(
              onPressed: () => openDocument(context, Api.instance.apiUrl('/circulars/${widget.c['id']}/attachment'), 'circular-${widget.c['id']}'),
              icon: const Icon(Icons.attach_file),
              label: const Text('Open attachment'),
            ),
          ],
        ]),
      );
}
