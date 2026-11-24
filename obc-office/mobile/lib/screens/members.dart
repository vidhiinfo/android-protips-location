import 'package:material_ui/material_ui.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../session.dart';
import '../widgets/common.dart';
import '../widgets/form_kit.dart';

class MembersScreen extends StatefulWidget {
  const MembersScreen({super.key});

  @override
  State<MembersScreen> createState() => _MembersScreenState();
}

class _MembersScreenState extends State<MembersScreen> {
  final _list = GlobalKey<PagedListState>();
  final Map<String, String?> _filters = {};
  String _q = '';

  Future<ApiPage> _fetch(int page) async =>
      ApiPage.fromJson(await Api.instance.get('/members', query: {'page': page, 'q': _q, ..._filters}));

  Future<void> _openFilters(Session s) async {
    final kit = FormKit(Map.of(_filters));
    final applied = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(ctx).viewInsets.bottom + 16),
        child: ListenableBuilder(
          listenable: kit,
          builder: (ctx, _) => Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('Filters', style: Theme.of(ctx).textTheme.titleMedium),
            const SizedBox(height: 12),
            if (s.isNational)
              kit.dropdown('state_id', 'State', {for (final st in s.states) '${st['id']}': st['name']}, onChanged: (_) => kit.set('district_id', null)),
            if (s.role != 'district_admin')
              kit.dropdown('district_id', 'District', {for (final d in s.districtsOf(int.tryParse(kit.str('state_id') ?? '') ?? s.stateId)) '${d['id']}': d['name']}),
            kit.dropdown('level', 'Level', s.lookups('level')),
            kit.dropdown('type', 'Type', s.lookups('member_type')),
            kit.dropdown('missing', 'Data quality', const {'photo': 'Missing photo', 'mobile': 'Missing mobile', 'dob': 'Missing DOB'}),
            Row(children: [
              Expanded(child: OutlinedButton(onPressed: () { kit.values.clear(); Navigator.pop(ctx, true); }, child: const Text('Clear'))),
              const SizedBox(width: 12),
              Expanded(child: FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Apply'))),
            ]),
          ]),
        ),
      ),
    );
    if (applied == true) {
      setState(() {
        _filters
          ..clear()
          ..addAll(kit.values.map((k, v) => MapEntry(k, v?.toString())));
      });
      _list.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    final active = _filters.values.where((v) => v != null && v.isNotEmpty).length;
    return Scaffold(
      appBar: AppBar(title: const Text('Bearers & Workers'), actions: [
        IconButton(icon: Badge(isLabelVisible: active > 0, label: Text('$active'), child: const Icon(Icons.filter_list)), onPressed: () => _openFilters(s)),
      ]),
      floatingActionButton: s.canManage('members')
          ? FloatingActionButton.extended(
              icon: const Icon(Icons.person_add_alt),
              label: const Text('Add'),
              onPressed: () async {
                final saved = await Navigator.push(context, MaterialPageRoute(builder: (_) => const MemberFormScreen()));
                if (saved == true) _list.currentState?.reload();
              },
            )
          : null,
      body: Column(children: [
        SearchField(hint: 'Name, mobile, mandal…', onChanged: (v) {
          _q = v;
          _list.currentState?.reload();
        }),
        Expanded(
          child: PagedList(
            key: _list,
            fetch: _fetch,
            empty: 'No members found.',
            itemBuilder: (ctx, m) => ListTile(
              leading: AuthAvatar(url: m['photo_url'], name: m['name']),
              title: Text(m['name'], style: const TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Text([m['designation']?['title'], m['area']].where((e) => e != null && '$e'.isNotEmpty).join(' · '), maxLines: 1, overflow: TextOverflow.ellipsis),
              trailing: m['status'] == 'proposed' ? StatusChip(s.label('member_status', 'proposed'), color: Colors.orange.shade800) : null,
              onTap: () async {
                final changed = await Navigator.push(ctx, MaterialPageRoute(builder: (_) => MemberDetailScreen(id: m['id'])));
                if (changed == true) _list.currentState?.reload();
              },
            ),
          ),
        ),
      ]),
    );
  }
}

class MemberDetailScreen extends StatefulWidget {
  const MemberDetailScreen({super.key, required this.id});

  final int id;

  @override
  State<MemberDetailScreen> createState() => _MemberDetailScreenState();
}

class _MemberDetailScreenState extends State<MemberDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();
  bool _changed = false;

  Future<Map<String, dynamic>> _load() async => ((await Api.instance.get('/members/${widget.id}'))['data'] as Map).cast<String, dynamic>();

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.pop(context, _changed);
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: FutureBuilder(
          future: _future,
          builder: (ctx, snap) {
            if (snap.hasError) return ErrorView(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
            if (!snap.hasData) return const Center(child: CircularProgressIndicator());
            final m = snap.data!;
            final extra = (m['extra'] is Map) ? (m['extra'] as Map) : {};
            return ListView(children: [
              const SizedBox(height: 16),
              Center(child: AuthAvatar(url: m['photo_url'], name: m['name'], radius: 56)),
              const SizedBox(height: 10),
              Text(m['name'], textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
              Text(m['designation']?['title'] ?? '', textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w600)),
              Text('${m['level_label']} · ${m['area']}', textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade600)),
              const SizedBox(height: 12),
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                if (m['mobile'] != null) FilledButton.tonalIcon(onPressed: () => callNumber(m['mobile']), icon: const Icon(Icons.call), label: const Text('Call')),
                const SizedBox(width: 12),
                if ((m['whatsapp'] ?? m['mobile']) != null)
                  FilledButton.tonalIcon(onPressed: () => openWhatsApp(m['whatsapp'] ?? m['mobile']), icon: const Icon(Icons.chat), label: const Text('WhatsApp')),
              ]),
              SectionCard(title: 'Details', trailing: StatusChip(s.label('member_status', m['status'])), children: [
                InfoRow('Type', s.label('member_type', m['type'])),
                InfoRow("Father's / Husband's name", m['father_name']),
                InfoRow('Gender', s.label('gender', m['gender'])),
                InfoRow('Date of birth', fmtDate(m['dob'])),
                InfoRow('Mobile', m['mobile']),
                InfoRow('Email', m['email']),
                InfoRow('Profession', s.label('profession', m['profession'])),
                InfoRow('Education', s.label('education', m['education'])),
                InfoRow('Mandal / Block', m['mandal']),
                InfoRow('Appointed on', fmtDate(m['appointed_on'])),
                InfoRow('Address', m['address']),
                ...s.customFields('member').map((f) => InfoRow(f['label'], extra[f['key']]?.toString())),
                InfoRow('Profile completeness', '${m['completeness']}%'),
              ]),
              const SizedBox(height: 24),
            ]);
          },
        ),
        floatingActionButton: s.canManage('members')
            ? FloatingActionButton.extended(
                icon: const Icon(Icons.edit),
                label: const Text('Edit'),
                onPressed: () async {
                  final m = await _future;
                  if (!context.mounted) return;
                  final saved = await Navigator.push(context, MaterialPageRoute(builder: (_) => MemberFormScreen(member: m)));
                  if (saved == true) {
                    setState(() {
                      _changed = true;
                      _future = _load();
                    });
                  }
                },
              )
            : null,
      ),
    );
  }
}

class MemberFormScreen extends StatefulWidget {
  const MemberFormScreen({super.key, this.member});

  final Map<String, dynamic>? member;

  @override
  State<MemberFormScreen> createState() => _MemberFormScreenState();
}

class _MemberFormScreenState extends State<MemberFormScreen> {
  late final FormKit kit;
  XFile? _photo;
  bool _busy = false;
  bool _allowDuplicate = false;

  @override
  void initState() {
    super.initState();
    final s = context.read<Session>();
    final m = widget.member;
    final extra = (m?['extra'] is Map) ? (m!['extra'] as Map) : {};
    kit = FormKit({
      'type': m?['type'] ?? 'bearer',
      'level': m?['level'] ?? (s.role == 'district_admin' ? 'district' : s.role == 'state_admin' ? 'state' : 'national'),
      'state_id': m?['state']?['id'] ?? s.stateId,
      'district_id': m?['district']?['id'] ?? s.districtId,
      'designation_id': m?['designation']?['id'],
      for (final k in ['name', 'father_name', 'gender', 'dob', 'mobile', 'whatsapp', 'email', 'address', 'profession', 'education', 'mandal', 'appointed_on'])
        k: m?[k],
      'consent': m?['consent'] ?? false,
      for (final e in extra.entries) 'extra.${e.key}': e.value,
    }, required: [...s.requiredFields('member'), 'name', 'type', 'level']);
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final body = await Api.form({...kit.payload(), 'photo': _photo, if (_allowDuplicate) 'allow_duplicate': '1'});
      final m = widget.member;
      await Api.instance.post(m == null ? '/members' : '/members/${m['id']}', data: body);
      if (!mounted) return;
      toast(context, m == null ? 'Member saved' : 'Member updated');
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      kit.applyErrors(e);
      if (mounted) {
        toast(context, e.fieldErrors.isEmpty ? e.message : 'Please check the highlighted fields', error: true);
        if ((e.fieldErrors['mobile'] ?? '').contains('allow duplicate')) setState(() {});
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    final levels = Map.of(s.lookups('level'));
    if (s.role == 'state_admin') levels.remove('national');
    if (s.role == 'district_admin') levels..remove('national')..remove('state');

    return Scaffold(
      appBar: AppBar(title: Text(widget.member == null ? 'Add member' : 'Edit member')),
      body: ListenableBuilder(
        listenable: kit,
        builder: (ctx, _) {
          final stateId = int.tryParse(kit.str('state_id') ?? '');
          final dupError = (kit.errors['mobile'] ?? '').contains('allow duplicate');
          return ListView(padding: const EdgeInsets.all(16), children: [
            Center(
              child: GestureDetector(
                onTap: () async {
                  final p = await pickPhoto(context);
                  if (p != null) setState(() => _photo = p);
                },
                child: Stack(children: [
                  _photo != null
                      ? CircleAvatar(radius: 52, child: ClipOval(child: PreviewImage(_photo!)))
                      : AuthAvatar(url: widget.member?['photo_url'], name: kit.str('name'), radius: 52),
                  const Positioned(right: 0, bottom: 0, child: CircleAvatar(radius: 16, child: Icon(Icons.photo_camera, size: 18))),
                ]),
              ),
            ),
            if (kit.errors['photo'] != null) Center(child: Text(kit.errors['photo']!, style: const TextStyle(color: Colors.red, fontSize: 12))),
            const SizedBox(height: 16),
            kit.dropdown('type', 'Type', s.lookups('member_type')),
            kit.dropdown('level', 'Level', levels, onChanged: (_) => kit.set('designation_id', null)),
            kit.dropdown('state_id', 'State', {for (final st in s.states) '${st['id']}': st['name']},
                enabled: s.isNational, onChanged: (_) => kit.set('district_id', null)),
            kit.dropdown('district_id', 'District', {for (final d in s.districtsOf(stateId)) '${d['id']}': d['name']}, enabled: s.role != 'district_admin'),
            kit.dropdown('designation_id', 'Designation', {for (final d in s.designationsFor(kit.str('level'))) '${d['id']}': d['title']}),
            kit.text('mandal', 'Mandal / Block'),
            kit.date(context, 'appointed_on', 'Appointed on'),
            const Divider(height: 24),
            kit.text('name', 'Full name'),
            kit.text('father_name', "Father's / Husband's name"),
            kit.dropdown('gender', 'Gender', s.lookups('gender')),
            kit.date(context, 'dob', 'Date of birth', last: DateTime.now()),
            kit.text('mobile', 'Mobile', keyboard: TextInputType.phone),
            if (dupError)
              CheckboxListTile(
                value: _allowDuplicate,
                onChanged: (v) => setState(() => _allowDuplicate = v ?? false),
                title: const Text('Allow duplicate mobile number'),
                contentPadding: EdgeInsets.zero,
              ),
            kit.text('whatsapp', 'WhatsApp (if different)', keyboard: TextInputType.phone),
            kit.text('email', 'Email', keyboard: TextInputType.emailAddress),
            kit.dropdown('profession', 'Profession', s.lookups('profession')),
            kit.dropdown('education', 'Education', s.lookups('education')),
            kit.text('address', 'Address', maxLines: 2),
            ...kit.customFields(context, s.customFields('member')),
            if (s.meta['require_consent'] == true)
              kit.checkbox('consent', 'The person consents to their data and photo being stored for party organisational use', mustFill: true),
            if (!s.isNational && widget.member == null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text('New records may need approval by the national office before they become active.', style: TextStyle(color: Colors.orange.shade800, fontSize: 12)),
              ),
            FilledButton(
              onPressed: _busy ? null : _save,
              style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
              child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Save'),
            ),
            const SizedBox(height: 24),
          ]);
        },
      ),
    );
  }
}

/// Shows a just-picked photo on every platform (file on mobile, bytes on web).
class PreviewImage extends StatelessWidget {
  const PreviewImage(this.file, {super.key, this.size = 104});

  final XFile file;
  final double size;

  @override
  Widget build(BuildContext context) => FutureBuilder(
        future: file.readAsBytes(),
        builder: (_, snap) => snap.hasData ? Image.memory(snap.data!, width: size, height: size, fit: BoxFit.cover) : SizedBox(width: size, height: size),
      );
}
