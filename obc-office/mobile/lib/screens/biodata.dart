import 'package:material_ui/material_ui.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../session.dart';
import '../widgets/common.dart';
import '../widgets/form_kit.dart';
import 'members.dart' show PreviewImage;

class BiodataListScreen extends StatefulWidget {
  const BiodataListScreen({super.key});

  @override
  State<BiodataListScreen> createState() => _BiodataListScreenState();
}

class _BiodataListScreenState extends State<BiodataListScreen> {
  final _list = GlobalKey<PagedListState>();
  String _q = '';
  String? _category;

  Future<ApiPage> _fetch(int page) async => ApiPage.fromJson(await Api.instance.get('/biodata', query: {'page': page, 'q': _q, 'category': _category}));

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    final cats = s.lookups('biodata_category');
    return Scaffold(
      appBar: AppBar(title: const Text('Biodata Register')),
      floatingActionButton: s.canManage('biodata')
          ? FloatingActionButton.extended(
              icon: const Icon(Icons.document_scanner_outlined),
              label: const Text('Record'),
              onPressed: () async {
                final saved = await Navigator.push(context, MaterialPageRoute(builder: (_) => const BiodataFormScreen()));
                if (saved == true) _list.currentState?.reload();
              },
            )
          : null,
      body: Column(children: [
        SearchField(hint: 'Name, mobile, ref. no., received from…', onChanged: (v) {
          _q = v;
          _list.currentState?.reload();
        }),
        SizedBox(
          height: 44,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 12), children: [
            for (final e in {null: 'All', ...cats}.entries)
              Padding(
                padding: const EdgeInsets.only(right: 6),
                child: ChoiceChip(
                  label: Text(e.value),
                  selected: _category == e.key,
                  onSelected: (_) {
                    setState(() => _category = e.key);
                    _list.currentState?.reload();
                  },
                ),
              ),
          ]),
        ),
        Expanded(
          child: PagedList(
            key: _list,
            fetch: _fetch,
            empty: 'No biodata found.',
            itemBuilder: (ctx, b) => ListTile(
              leading: AuthAvatar(url: b['photo_url'], name: b['name']),
              title: Text(b['name'], style: const TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Text(
                [b['reference_no'], fmtDate(b['received_on']), b['received_from']].where((e) => e != null && '$e'.isNotEmpty).join(' · '),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                StatusChip(b['status_label'] ?? ''),
                if ((b['documents_count'] ?? 0) > 0) Text('📎 ${b['documents_count']}', style: const TextStyle(fontSize: 12)),
              ]),
              onTap: () async {
                await Navigator.push(ctx, MaterialPageRoute(builder: (_) => BiodataDetailScreen(id: b['id'])));
                _list.currentState?.reload();
              },
            ),
          ),
        ),
      ]),
    );
  }
}

class BiodataDetailScreen extends StatefulWidget {
  const BiodataDetailScreen({super.key, required this.id});

  final int id;

  @override
  State<BiodataDetailScreen> createState() => _BiodataDetailScreenState();
}

class _BiodataDetailScreenState extends State<BiodataDetailScreen> {
  late Future<Map<String, dynamic>> _future = _load();
  bool _uploading = false;

  Future<Map<String, dynamic>> _load() async => (await Api.instance.get('/biodata/${widget.id}') as Map).cast<String, dynamic>();

  Future<void> _addPages() async {
    final pages = await capturePages(context);
    if (pages.isEmpty) return;
    setState(() => _uploading = true);
    try {
      await Api.instance.post('/biodata/${widget.id}/documents', data: await Api.form({'documents': pages}));
      if (mounted) toast(context, '${pages.length} file(s) attached');
      setState(() => _future = _load());
    } on ApiException catch (e) {
      if (mounted) toast(context, e.fieldErrors.values.firstOrNull ?? e.message, error: true);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  void _openDocument(Map<String, dynamic> d) {
    final isImage = (d['mime'] ?? '').toString().startsWith('image/');
    if (isImage) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => Scaffold(
            appBar: AppBar(title: Text(d['name'])),
            backgroundColor: Colors.black,
            body: InteractiveViewer(maxScale: 5, child: Center(child: AuthImage(url: d['url'], fit: BoxFit.contain))),
          )));
    } else {
      openDocument(context, d['url'], d['name']);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    return Scaffold(
      appBar: AppBar(title: const Text('Biodata')),
      body: FutureBuilder(
        future: _future,
        builder: (ctx, snap) {
          if (snap.hasError) return ErrorView(snap.error.toString(), onRetry: () => setState(() => _future = _load()));
          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
          final b = snap.data!;
          final docs = ((b['documents'] as List?) ?? []).cast<Map<String, dynamic>>();
          final extra = (b['extra'] is Map) ? (b['extra'] as Map) : {};
          final area = [b['mandal'], b['district']?['name'], b['state']?['name']].where((e) => e != null).join(', ');
          return ListView(children: [
            const SizedBox(height: 16),
            Center(child: AuthAvatar(url: b['photo_url'], name: b['name'], radius: 52)),
            const SizedBox(height: 8),
            Text(b['name'], textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            Text(area, textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade600)),
            const SizedBox(height: 8),
            Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              if (b['mobile'] != null) FilledButton.tonalIcon(onPressed: () => callNumber(b['mobile']), icon: const Icon(Icons.call), label: const Text('Call')),
              const SizedBox(width: 12),
              if (b['mobile'] != null) FilledButton.tonalIcon(onPressed: () => openWhatsApp(b['whatsapp'] ?? b['mobile']), icon: const Icon(Icons.chat), label: const Text('WhatsApp')),
            ]),
            SectionCard(title: 'Receipt', trailing: StatusChip(b['status_label'] ?? ''), children: [
              InfoRow('Reference no.', b['reference_no']),
              InfoRow('Received on', fmtDate(b['received_on'])),
              InfoRow('Received from', b['received_from']),
              InfoRow('Received via', s.label('biodata_source', b['received_via'])),
              InfoRow('Category', s.label('biodata_category', b['category'])),
            ]),
            SectionCard(title: 'Details', children: [
              InfoRow("Father's / Husband's name", b['father_name']),
              InfoRow('Gender', s.label('gender', b['gender'])),
              InfoRow('Date of birth', fmtDate(b['dob'])),
              InfoRow('Mobile', b['mobile']),
              InfoRow('Email', b['email']),
              InfoRow('Education', s.label('education', b['education'])),
              InfoRow('Profession', s.label('profession', b['profession'])),
              InfoRow('Address', b['address']),
              ...s.customFields('biodata').map((f) => InfoRow(f['label'], extra[f['key']]?.toString())),
              InfoRow('Political / social background', b['background']),
              InfoRow('Office remarks', b['remarks']),
            ]),
            SectionCard(
              title: 'Files (${docs.length})',
              trailing: s.canManage('biodata')
                  ? (_uploading
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : TextButton.icon(onPressed: _addPages, icon: const Icon(Icons.add_a_photo_outlined, size: 18), label: const Text('Add')))
                  : null,
              children: [
                if (docs.isEmpty) const Text('No files attached.', style: TextStyle(color: Colors.grey)),
                ...docs.map((d) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: (d['mime'] ?? '').toString().startsWith('image/')
                          ? ClipRRect(borderRadius: BorderRadius.circular(6), child: SizedBox(width: 44, height: 52, child: AuthImage(url: d['url'])))
                          : const Icon(Icons.picture_as_pdf_outlined, size: 36),
                      title: Text(d['name'], maxLines: 1, overflow: TextOverflow.ellipsis),
                      subtitle: Text('${((d['size'] ?? 0) / 1024).round()} KB'),
                      onTap: () => _openDocument(d),
                    )),
              ],
            ),
            const SizedBox(height: 24),
          ]);
        },
      ),
    );
  }
}

/// Photograph biodata pages one after another (or pick from gallery / files).
Future<List<XFile>> capturePages(BuildContext context) async {
  final picker = ImagePicker();
  final choice = await showModalBottomSheet<String>(
    context: context,
    builder: (ctx) => SafeArea(
      child: Wrap(children: [
        ListTile(leading: const Icon(Icons.document_scanner_outlined), title: const Text('Photograph pages'), subtitle: const Text('Take one photo per page'), onTap: () => Navigator.pop(ctx, 'camera')),
        ListTile(leading: const Icon(Icons.photo_library_outlined), title: const Text('Choose photos from gallery'), onTap: () => Navigator.pop(ctx, 'gallery')),
      ]),
    ),
  );
  if (choice == 'gallery') return picker.pickMultiImage(maxWidth: 2000, imageQuality: 85);
  if (choice != 'camera') return [];

  final pages = <XFile>[];
  while (true) {
    final p = await picker.pickImage(source: ImageSource.camera, maxWidth: 2000, imageQuality: 85);
    if (p == null) break;
    pages.add(p);
    if (!context.mounted) break;
    final more = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Page ${pages.length} captured'),
        content: const Text('Photograph another page?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Done')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Next page')),
        ],
      ),
    );
    if (more != true) break;
  }
  return pages;
}

class BiodataFormScreen extends StatefulWidget {
  const BiodataFormScreen({super.key});

  @override
  State<BiodataFormScreen> createState() => _BiodataFormScreenState();
}

class _BiodataFormScreenState extends State<BiodataFormScreen> {
  late final FormKit kit;
  XFile? _photo;
  final List<XFile> _pages = [];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final s = context.read<Session>();
    kit = FormKit({
      'received_on': DateFormat('yyyy-MM-dd').format(DateTime.now()),
      'state_id': s.stateId,
      'district_id': s.districtId,
    }, required: [...s.requiredFields('biodata'), 'name', 'received_on']);
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final res = await Api.instance.post('/biodata', data: await Api.form({...kit.payload(), 'photo': _photo, 'documents': _pages}));
      if (!mounted) return;
      toast(context, 'Saved as ${res['reference_no']}');
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      kit.applyErrors(e);
      if (mounted) toast(context, e.fieldErrors.isEmpty ? e.message : 'Please check the highlighted fields', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    return Scaffold(
      appBar: AppBar(title: const Text('Record biodata')),
      body: ListenableBuilder(
        listenable: kit,
        builder: (ctx, _) {
          final stateId = int.tryParse(kit.str('state_id') ?? '');
          final docsError = kit.errors['documents'] ?? kit.errors.entries.where((e) => e.key.startsWith('documents.')).map((e) => e.value).firstOrNull;
          return ListView(padding: const EdgeInsets.all(16), children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Expanded(child: Text(kit.required.contains('documents') ? 'Biodata pages *' : 'Biodata pages', style: const TextStyle(fontWeight: FontWeight.w600))),
                    TextButton.icon(
                      onPressed: () async {
                        final p = await capturePages(context);
                        setState(() => _pages.addAll(p));
                      },
                      icon: const Icon(Icons.add_a_photo_outlined),
                      label: const Text('Add pages'),
                    ),
                  ]),
                  if (_pages.isEmpty) const Text('Photograph each page of the biodata received.', style: TextStyle(color: Colors.grey, fontSize: 13)),
                  if (_pages.isNotEmpty)
                    SizedBox(
                      height: 92,
                      child: ListView(scrollDirection: Axis.horizontal, children: [
                        for (var i = 0; i < _pages.length; i++)
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: Stack(children: [
                              ClipRRect(borderRadius: BorderRadius.circular(6), child: PreviewImage(_pages[i], size: 84)),
                              Positioned(
                                right: 0,
                                top: 0,
                                child: InkWell(onTap: () => setState(() => _pages.removeAt(i)), child: const CircleAvatar(radius: 11, backgroundColor: Colors.black54, child: Icon(Icons.close, size: 14, color: Colors.white))),
                              ),
                            ]),
                          ),
                      ]),
                    ),
                  if (docsError != null) Text(docsError, style: const TextStyle(color: Colors.red, fontSize: 12)),
                ]),
              ),
            ),
            const SizedBox(height: 12),
            kit.date(context, 'received_on', 'Received on', last: DateTime.now()),
            kit.text('received_from', 'Received from', hint: 'e.g. UP State President'),
            kit.dropdown('received_via', 'Received via', s.lookups('biodata_source')),
            kit.dropdown('category', 'Category', s.lookups('biodata_category')),
            const Divider(height: 24),
            Row(children: [
              GestureDetector(
                onTap: () async {
                  final p = await pickPhoto(context);
                  if (p != null) setState(() => _photo = p);
                },
                child: CircleAvatar(radius: 34, child: _photo == null ? const Icon(Icons.add_a_photo_outlined) : ClipOval(child: PreviewImage(_photo!, size: 68))),
              ),
              const SizedBox(width: 12),
              Expanded(child: Text(kit.required.contains('photo') ? "Person's photo *" : "Person's photo (optional)", style: TextStyle(color: kit.errors['photo'] != null ? Colors.red : null))),
            ]),
            const SizedBox(height: 12),
            kit.text('name', 'Full name'),
            kit.text('father_name', "Father's / Husband's name"),
            kit.dropdown('gender', 'Gender', s.lookups('gender')),
            kit.date(context, 'dob', 'Date of birth', last: DateTime.now()),
            kit.text('mobile', 'Mobile', keyboard: TextInputType.phone),
            kit.text('whatsapp', 'WhatsApp (if different)', keyboard: TextInputType.phone),
            kit.text('email', 'Email', keyboard: TextInputType.emailAddress),
            kit.dropdown('state_id', 'State', {for (final st in s.states) '${st['id']}': st['name']}, enabled: s.isNational, onChanged: (_) => kit.set('district_id', null)),
            kit.dropdown('district_id', 'District', {for (final d in s.districtsOf(stateId)) '${d['id']}': d['name']}, enabled: s.role != 'district_admin'),
            kit.text('mandal', 'Mandal / Block'),
            kit.dropdown('education', 'Education', s.lookups('education')),
            kit.dropdown('profession', 'Profession', s.lookups('profession')),
            kit.text('address', 'Address', maxLines: 2),
            ...kit.customFields(context, s.customFields('biodata')),
            kit.text('background', 'Political / social background', maxLines: 4),
            kit.text('remarks', 'Office remarks', maxLines: 3),
            FilledButton(
              onPressed: _busy ? null : _save,
              style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
              child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Save biodata'),
            ),
            const SizedBox(height: 24),
          ]);
        },
      ),
    );
  }
}
