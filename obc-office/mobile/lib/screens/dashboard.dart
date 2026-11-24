import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../session.dart';
import '../widgets/common.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async => (await Api.instance.get('/dashboard') as Map).cast<String, dynamic>();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    return Scaffold(
      appBar: AppBar(title: Text(s.orgName), actions: [IconButton(icon: const Icon(Icons.refresh), onPressed: () => setState(() => _future = _load()))]),
      body: FutureBuilder(
        future: _future,
        builder: (ctx, snap) {
          if (snap.hasError) return ErrorView(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
          final d = snap.data!;
          final t = (d['totals'] as Map).cast<String, dynamic>();
          final office = d['office'] is Map ? (d['office'] as Map).cast<String, dynamic>() : <String, dynamic>{};
          final rows = ((d['breakdown']?['rows'] as List?) ?? []).cast<Map<String, dynamic>>()..sort((a, b) => (b['bearers'] as int).compareTo(a['bearers'] as int));
          final tasks = ((d['my_tasks'] as List?) ?? []).cast<Map<String, dynamic>>();

          return RefreshIndicator(
            onRefresh: () async => setState(() => _future = _load()),
            child: ListView(padding: const EdgeInsets.only(bottom: 24), children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                child: Text('Namaskar, ${s.user!['name']}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
              ),
              Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: Text(s.user!['jurisdiction'] as String, style: TextStyle(color: Colors.grey.shade600))),
              GridView.count(
                crossAxisCount: 3,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                padding: const EdgeInsets.all(12),
                mainAxisSpacing: 8,
                crossAxisSpacing: 8,
                childAspectRatio: 1.25,
                children: [
                  _Tile('Bearers', t['bearers']),
                  _Tile('Workers', t['workers']),
                  _Tile('Pending', t['proposed']),
                  _Tile('No photo', t['missing_photo']),
                  _Tile('No mobile', t['missing_mobile']),
                  _Tile('This month', t['added_this_month']),
                ],
              ),
              if (office['schedule'] is List)
                SectionCard(title: "Today's schedule", children: [
                  if ((office['schedule'] as List).isEmpty) const Text('No engagements today.', style: TextStyle(color: Colors.grey)),
                  ...(office['schedule'] as List).map((e) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        dense: true,
                        leading: Text(fmtDate(e['start_at'], 'h:mm a'), style: TextStyle(color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w700)),
                        title: Text(e['title'] ?? ''),
                        subtitle: Text(e['location'] ?? ''),
                      )),
                  Wrap(spacing: 16, children: [
                    if (office['visitors_today'] != null) Text('Visitors today: ${office['visitors_today']}'),
                    if (office['petitions_open'] != null) Text('Open petitions: ${office['petitions_open']}'),
                    if (office['petitions_overdue'] != null) Text('Overdue: ${office['petitions_overdue']}', style: TextStyle(color: (office['petitions_overdue'] as int) > 0 ? Colors.red : null)),
                    if (office['biodata_month'] != null) Text('Biodata this month: ${office['biodata_month']}'),
                  ]),
                ]),
              if (tasks.isNotEmpty)
                SectionCard(title: 'My tasks', children: tasks.map((t) => ListTile(contentPadding: EdgeInsets.zero, dense: true, title: Text(t['title']), trailing: Text(fmtDate(t['due_date'])))).toList()),
              SectionCard(
                title: '${d['breakdown']?['by'] ?? 'State'}-wise',
                children: rows.take(15).map((r) {
                  final max = rows.isEmpty ? 1 : (rows.first['bearers'] as int).clamp(1, 1 << 30);
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 5),
                    child: Row(children: [
                      Expanded(flex: 4, child: Text(r['name'], overflow: TextOverflow.ellipsis)),
                      Expanded(flex: 3, child: LinearProgressIndicator(value: (r['bearers'] as int) / max, minHeight: 6, borderRadius: BorderRadius.circular(4))),
                      SizedBox(width: 40, child: Text('${r['bearers']}', textAlign: TextAlign.right)),
                      SizedBox(width: 58, child: Text('${r['posts_filled']}/${r['posts_sanctioned']}', textAlign: TextAlign.right, style: TextStyle(fontSize: 12, color: (r['vacancies'] as int) > 0 ? Colors.orange.shade800 : Colors.green))),
                    ]),
                  );
                }).toList(),
              ),
            ]),
          );
        },
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile(this.label, this.value);

  final String label;
  final dynamic value;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            const SizedBox(height: 4),
            Text('${value ?? 0}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
          ]),
        ),
      );
}
