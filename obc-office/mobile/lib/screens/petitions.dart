import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../session.dart';
import '../widgets/common.dart';

class PetitionsScreen extends StatefulWidget {
  const PetitionsScreen({super.key});

  @override
  State<PetitionsScreen> createState() => _PetitionsScreenState();
}

class _PetitionsScreenState extends State<PetitionsScreen> {
  final _list = GlobalKey<PagedListState>();
  String _q = '';
  String? _status;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    final statuses = s.lookups('petition_status');
    return Scaffold(
      appBar: AppBar(title: const Text('Petitions')),
      body: Column(children: [
        SearchField(hint: 'Tracking no. or name', onChanged: (v) {
          _q = v;
          _list.currentState?.reload();
        }),
        SizedBox(
          height: 44,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 12), children: [
            for (final e in {null: 'All', ...statuses}.entries)
              Padding(
                padding: const EdgeInsets.only(right: 6),
                child: ChoiceChip(label: Text(e.value), selected: _status == e.key, onSelected: (_) {
                  setState(() => _status = e.key);
                  _list.currentState?.reload();
                }),
              ),
          ]),
        ),
        Expanded(
          child: PagedList(
            key: _list,
            fetch: (p) async => ApiPage.fromJson(await Api.instance.get('/petitions', query: {'page': p, 'q': _q, 'status': _status})),
            empty: 'No petitions.',
            itemBuilder: (ctx, p) => ListTile(
              title: Text(p['subject'], maxLines: 1, overflow: TextOverflow.ellipsis),
              subtitle: Text('${p['tracking_no']} · ${p['petitioner_name']} · ${fmtDate(p['received_on'])}'),
              trailing: StatusChip(s.label('petition_status', p['status'])),
              onTap: () async {
                await Navigator.push(ctx, MaterialPageRoute(builder: (_) => PetitionDetailScreen(id: p['id'])));
                _list.currentState?.reload();
              },
            ),
          ),
        ),
      ]),
    );
  }
}

class PetitionDetailScreen extends StatefulWidget {
  const PetitionDetailScreen({super.key, required this.id});

  final int id;

  @override
  State<PetitionDetailScreen> createState() => _PetitionDetailScreenState();
}

class _PetitionDetailScreenState extends State<PetitionDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async => (await Api.instance.get('/petitions/${widget.id}') as Map).cast<String, dynamic>();

  Future<void> _addUpdate(Session s, Map<String, dynamic> p) async {
    String status = p['status'];
    final note = TextEditingController();
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(ctx).viewInsets.bottom + 16),
        child: StatefulBuilder(
          builder: (ctx, setSt) => Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('Add follow-up', style: Theme.of(ctx).textTheme.titleMedium),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: status,
              decoration: const InputDecoration(labelText: 'Status', border: OutlineInputBorder()),
              items: s.lookups('petition_status').entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
              onChanged: (v) => setSt(() => status = v ?? status),
            ),
            const SizedBox(height: 12),
            TextField(controller: note, maxLines: 3, decoration: const InputDecoration(labelText: 'Note', border: OutlineInputBorder())),
            const SizedBox(height: 12),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
          ]),
        ),
      ),
    );
    if (ok != true) return;
    try {
      await Api.instance.post('/petitions/${widget.id}/updates', data: {'status': status, 'note': note.text});
      setState(() => _future = _load());
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    return Scaffold(
      appBar: AppBar(title: const Text('Petition')),
      body: FutureBuilder(
        future: _future,
        builder: (ctx, snap) {
          if (snap.hasError) return ErrorView(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
          final p = snap.data!;
          final updates = ((p['updates'] as List?) ?? []).cast<Map<String, dynamic>>();
          return ListView(children: [
            SectionCard(title: p['tracking_no'], trailing: StatusChip(s.label('petition_status', p['status'])), children: [
              Text(p['subject'], style: Theme.of(context).textTheme.titleMedium),
              InfoRow('Petitioner', p['petitioner_name']),
              InfoRow('Mobile', p['mobile']),
              InfoRow('Area', [p['district']?['name'], p['state']?['name']].where((e) => e != null).join(', ')),
              InfoRow('Category', s.label('petition_category', p['category'])),
              InfoRow('Received', fmtDate(p['received_on'])),
              InfoRow('Follow-up due', fmtDate(p['due_date'])),
              InfoRow('Forwarded to', p['forwarded_to']),
              InfoRow('Details', p['details']),
              if (p['mobile'] != null)
                Row(children: [
                  OutlinedButton.icon(onPressed: () => callNumber(p['mobile']), icon: const Icon(Icons.call, size: 18), label: const Text('Call')),
                  const SizedBox(width: 8),
                  OutlinedButton.icon(
                    onPressed: () => openWhatsApp(p['mobile'], 'Your petition ${p['tracking_no']} status: ${s.label('petition_status', p['status'])}.'),
                    icon: const Icon(Icons.chat, size: 18),
                    label: const Text('Send status'),
                  ),
                ]),
            ]),
            SectionCard(title: 'Timeline', children: [
              for (final u in updates)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.circle, size: 10),
                  minLeadingWidth: 12,
                  title: Text(u['note']?.toString().isNotEmpty == true ? u['note'] : s.label('petition_status', u['status'])),
                  subtitle: Text('${fmtDate(u['created_at'], 'd MMM y, h:mm a')} · ${u['user']?['name'] ?? ''}'),
                ),
            ]),
            const SizedBox(height: 80),
          ]);
        },
      ),
      floatingActionButton: s.canManage('petitions')
          ? FloatingActionButton.extended(
              icon: const Icon(Icons.add_comment_outlined),
              label: const Text('Follow-up'),
              onPressed: () async => _addUpdate(s, await _future),
            )
          : null,
    );
  }
}
