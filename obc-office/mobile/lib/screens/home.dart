import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../session.dart';
import 'biodata.dart';
import 'circulars.dart';
import 'dashboard.dart';
import 'greetings.dart';
import 'members.dart';
import 'petitions.dart';
import 'programs.dart';
import 'schedule.dart';
import 'tasks.dart';

class AppModule {
  const AppModule(this.key, this.label, this.icon, this.builder);

  final String key;
  final String label;
  final IconData icon;
  final Widget Function() builder;
}

/// Bottom navigation built from the user's permissions (Settings → Roles & permissions on the server).
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  static final _primary = [
    AppModule('dashboard', 'Home', Icons.dashboard_outlined, () => const DashboardScreen()),
    AppModule('members', 'Members', Icons.groups_outlined, () => const MembersScreen()),
    AppModule('biodata', 'Biodata', Icons.description_outlined, () => const BiodataListScreen()),
  ];

  static final secondary = [
    AppModule('schedule', 'Schedule & Tours', Icons.event_outlined, () => const ScheduleScreen()),
    AppModule('greetings', 'Greetings', Icons.cake_outlined, () => const GreetingsScreen()),
    AppModule('circulars', 'Circulars', Icons.campaign_outlined, () => const CircularsScreen()),
    AppModule('tasks', 'Tasks', Icons.task_alt, () => const TasksScreen()),
    AppModule('petitions', 'Petitions', Icons.mark_email_unread_outlined, () => const PetitionsScreen()),
    AppModule('programs', 'Programmes & Reports', Icons.flag_outlined, () => const ProgramsScreen()),
  ];

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    final tabs = _primary.where((m) => s.canView(m.key)).toList();
    final more = secondary.where((m) => s.canView(m.key)).toList();
    final pages = [...tabs.map((m) => m.builder()), MoreScreen(modules: more)];
    final dests = [
      ...tabs.map((m) => NavigationDestination(icon: Icon(m.icon), label: m.label)),
      const NavigationDestination(icon: Icon(Icons.menu), label: 'More'),
    ];
    if (_index >= pages.length) _index = 0;

    return Scaffold(
      body: IndexedStack(index: _index, children: pages),
      bottomNavigationBar: NavigationBar(selectedIndex: _index, onDestinationSelected: (i) => setState(() => _index = i), destinations: dests),
    );
  }
}

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key, required this.modules});

  final List<AppModule> modules;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    final u = s.user!;
    return Scaffold(
      appBar: AppBar(title: Text(s.orgName)),
      body: ListView(children: [
        ListTile(
          leading: CircleAvatar(child: Text((u['name'] as String).substring(0, 1))),
          title: Text(u['name'] as String, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Text('${u['role_label']} · ${u['jurisdiction']}'),
        ),
        const Divider(),
        ...modules.map((m) => ListTile(
              leading: Icon(m.icon),
              title: Text(m.label),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => m.builder())),
            )),
        const Divider(),
        ListTile(
          leading: const Icon(Icons.sync),
          title: const Text('Refresh settings & lists'),
          subtitle: const Text('After the office changes dropdowns or permissions'),
          onTap: () async {
            await s.refreshMeta();
            if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Updated')));
          },
        ),
        ListTile(
          leading: const Icon(Icons.logout, color: Colors.red),
          title: const Text('Sign out', style: TextStyle(color: Colors.red)),
          onTap: () => s.logout(),
        ),
      ]),
    );
  }
}
