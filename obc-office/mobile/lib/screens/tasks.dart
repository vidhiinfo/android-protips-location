import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../session.dart';
import '../widgets/common.dart';

class TasksScreen extends StatefulWidget {
  const TasksScreen({super.key});

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen> {
  final _list = GlobalKey<PagedListState>();
  bool _mine = true;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    final statuses = s.lookups('task_status');
    return Scaffold(
      appBar: AppBar(title: const Text('Tasks'), actions: [
        if (s.canManage('tasks'))
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: SegmentedButton<bool>(
              segments: const [ButtonSegment(value: true, label: Text('Mine')), ButtonSegment(value: false, label: Text('All'))],
              selected: {_mine},
              onSelectionChanged: (v) {
                setState(() => _mine = v.first);
                _list.currentState?.reload();
              },
            ),
          ),
      ]),
      body: PagedList(
        key: _list,
        fetch: (p) async => ApiPage.fromJson(await Api.instance.get('/tasks', query: {'page': p, 'mine': _mine ? 1 : null})),
        empty: 'No tasks.',
        itemBuilder: (ctx, t) {
          final overdue = t['due_date'] != null && t['status'] != 'done' && DateTime.parse(t['due_date']).isBefore(DateTime.now().subtract(const Duration(days: 1)));
          return ListTile(
            title: Text(t['title'], style: TextStyle(decoration: t['status'] == 'done' ? TextDecoration.lineThrough : null)),
            subtitle: Text([
              if (t['due_date'] != null) 'Due ${fmtDate(t['due_date'])}',
              if (t['assignee'] != null) t['assignee']['name'],
              s.label('priority', t['priority']),
            ].join(' · '), style: TextStyle(color: overdue ? Colors.red : null)),
            trailing: DropdownButton<String>(
              value: statuses.containsKey(t['status']) ? t['status'] : null,
              underline: const SizedBox.shrink(),
              items: statuses.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value, style: const TextStyle(fontSize: 13)))).toList(),
              onChanged: (v) async {
                try {
                  await Api.instance.patch('/tasks/${t['id']}', data: {'status': v});
                  setState(() => t['status'] = v);
                } on ApiException catch (e) {
                  if (ctx.mounted) toast(ctx, e.message, error: true);
                }
              },
            ),
            onTap: (t['details'] ?? '').toString().isEmpty ? null : () => showModalBottomSheet(context: ctx, builder: (_) => Padding(padding: const EdgeInsets.all(20), child: Text(t['details']))),
          );
        },
      ),
    );
  }
}
