import 'package:material_ui/material_ui.dart';

import '../api.dart';
import '../widgets/common.dart';

class GreetingsScreen extends StatefulWidget {
  const GreetingsScreen({super.key});

  @override
  State<GreetingsScreen> createState() => _GreetingsScreenState();
}

class _GreetingsScreenState extends State<GreetingsScreen> {
  int _days = 7;
  late Future<List> _future = _load();

  Future<List> _load() async => await Api.instance.get('/greetings', query: {'days': _days}) as List;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Greetings'), actions: [
          PopupMenuButton<int>(
            initialValue: _days,
            icon: const Icon(Icons.date_range),
            onSelected: (d) => setState(() { _days = d; _future = _load(); }),
            itemBuilder: (_) => const [
              PopupMenuItem(value: 0, child: Text('Today')),
              PopupMenuItem(value: 7, child: Text('Next 7 days')),
              PopupMenuItem(value: 30, child: Text('Next 30 days')),
            ],
          ),
        ]),
        body: FutureBuilder(
          future: _future,
          builder: (ctx, snap) {
            if (snap.hasError) return ErrorView(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
            if (!snap.hasData) return const Center(child: CircularProgressIndicator());
            final items = snap.data!.cast<Map<String, dynamic>>();
            if (items.isEmpty) return const Center(child: Text('No birthdays or anniversaries.', style: TextStyle(color: Colors.grey)));
            return ListView.builder(
              itemCount: items.length,
              itemBuilder: (ctx, i) {
                final g = items[i];
                final when = g['in_days'] == 0 ? 'Today' : g['in_days'] == 1 ? 'Tomorrow' : g['date'];
                return Card(
                  margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        AuthAvatar(url: g['photo_url'], name: g['name']),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(g['name'], style: const TextStyle(fontWeight: FontWeight.w600)),
                            Text([g['designation'], g['area']].where((x) => x != null && '$x'.isNotEmpty).join(' · '), style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                          ]),
                        ),
                        StatusChip('${g['kind'] == 'birthday' ? '🎂' : '💐'} $when', color: g['in_days'] == 0 ? Colors.green.shade700 : null),
                      ]),
                      const SizedBox(height: 8),
                      Text(g['message'] ?? '', style: TextStyle(fontSize: 13, color: Colors.grey.shade800)),
                      const SizedBox(height: 8),
                      Row(children: [
                        if (g['whatsapp_url'] != null)
                          FilledButton.icon(onPressed: () => openWhatsApp(g['whatsapp_url']), icon: const Icon(Icons.chat, size: 18), label: const Text('WhatsApp')),
                        const SizedBox(width: 8),
                        if (g['mobile'] != null) OutlinedButton.icon(onPressed: () => callNumber(g['mobile']), icon: const Icon(Icons.call, size: 18), label: const Text('Call')),
                      ]),
                    ]),
                  ),
                );
              },
            );
          },
        ),
      );
}
