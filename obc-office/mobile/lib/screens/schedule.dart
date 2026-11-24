import 'package:material_ui/material_ui.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../session.dart';
import '../widgets/common.dart';

class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  DateTime _from = DateTime.now();
  late Future<List> _future = _load();

  Future<List> _load() async {
    final f = DateFormat('yyyy-MM-dd');
    return await Api.instance.get('/schedule', query: {'from': f.format(_from), 'to': f.format(_from.add(const Duration(days: 14)))}) as List;
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    return Scaffold(
      appBar: AppBar(title: const Text('Schedule & Tours'), actions: [
        IconButton(
          icon: const Icon(Icons.calendar_month),
          onPressed: () async {
            final d = await showDatePicker(context: context, initialDate: _from, firstDate: DateTime(2020), lastDate: DateTime(2035));
            if (d != null) setState(() { _from = d; _future = _load(); });
          },
        ),
      ]),
      body: FutureBuilder(
        future: _future,
        builder: (ctx, snap) {
          if (snap.hasError) return ErrorView(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
          final items = snap.data!.cast<Map<String, dynamic>>();
          if (items.isEmpty) return const Center(child: Text('Nothing scheduled in the next 14 days.', style: TextStyle(color: Colors.grey)));
          String? lastDay;
          return RefreshIndicator(
            onRefresh: () async => setState(() => _future = _load()),
            child: ListView(children: [
              for (final e in items) ...[
                if (lastDay != (lastDay = fmtDate(e['start_at'], 'EEEE, d MMM')))
                  Padding(padding: const EdgeInsets.fromLTRB(16, 16, 16, 4), child: Text(lastDay, style: TextStyle(fontWeight: FontWeight.w700, color: Theme.of(context).colorScheme.primary))),
                Card(
                  margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  child: ListTile(
                    leading: Text(fmtDate(e['start_at'], 'h:mm\na'), textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w600)),
                    title: Text(e['title'] ?? ''),
                    subtitle: Text([s.label('schedule_kind', e['kind']), e['location'], e['contact_person']].where((x) => x != null && '$x'.isNotEmpty).join(' · ')),
                    trailing: StatusChip(s.label('schedule_status', e['status'])),
                    onTap: (e['notes'] ?? '').toString().isEmpty
                        ? null
                        : () => showModalBottomSheet(context: context, builder: (_) => Padding(padding: const EdgeInsets.all(20), child: Text(e['notes']))),
                  ),
                ),
              ],
            ]),
          );
        },
      ),
    );
  }
}
