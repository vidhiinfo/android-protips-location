import 'package:material_ui/material_ui.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../session.dart';
import '../widgets/common.dart';
import '../widgets/form_kit.dart';
import 'members.dart' show PreviewImage;

class ProgramsScreen extends StatelessWidget {
  const ProgramsScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Programmes & Reports')),
        body: PagedList(
          fetch: (p) async => ApiPage.fromJson(await Api.instance.get('/programs', query: {'page': p})),
          empty: 'No programmes.',
          itemBuilder: (ctx, p) => Card(
            margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
            child: ListTile(
              title: Text(p['name'], style: const TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Text([fmtDate(p['start_date']), if (p['end_date'] != null) '– ${fmtDate(p['end_date'])}', if ((p['description'] ?? '').toString().isNotEmpty) '\n${p['description']}'].join(' '),
                  maxLines: 3, overflow: TextOverflow.ellipsis),
              trailing: const Icon(Icons.add_a_photo_outlined),
              onTap: () => Navigator.push(ctx, MaterialPageRoute(builder: (_) => ReportFormScreen(program: p))),
            ),
          ),
        ),
      );
}

/// Field report for a programme: date, venue, attendance, summary and up to 10 photos.
class ReportFormScreen extends StatefulWidget {
  const ReportFormScreen({super.key, required this.program});

  final Map<String, dynamic> program;

  @override
  State<ReportFormScreen> createState() => _ReportFormScreenState();
}

class _ReportFormScreenState extends State<ReportFormScreen> {
  late final FormKit kit;
  final List<XFile> _photos = [];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final s = context.read<Session>();
    kit = FormKit({'event_date': DateFormat('yyyy-MM-dd').format(DateTime.now()), 'state_id': s.stateId, 'district_id': s.districtId},
        required: const ['state_id', 'event_date']);
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      await Api.instance.post('/programs/${widget.program['id']}/reports', data: await Api.form({...kit.payload(), 'photos': _photos}));
      if (!mounted) return;
      toast(context, 'Report submitted. Thank you!');
      Navigator.pop(context);
    } on ApiException catch (e) {
      kit.applyErrors(e);
      if (mounted) toast(context, e.fieldErrors.isEmpty ? e.message : 'Please check the form', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<Session>();
    return Scaffold(
      appBar: AppBar(title: Text(widget.program['name'])),
      body: ListenableBuilder(
        listenable: kit,
        builder: (ctx, _) => ListView(padding: const EdgeInsets.all(16), children: [
          kit.dropdown('state_id', 'State', {for (final st in s.states) '${st['id']}': st['name']}, enabled: s.isNational, onChanged: (_) => kit.set('district_id', null)),
          kit.dropdown('district_id', 'District (blank for state-level event)', {for (final d in s.districtsOf(int.tryParse(kit.str('state_id') ?? ''))) '${d['id']}': d['name']},
              enabled: s.role != 'district_admin'),
          kit.date(context, 'event_date', 'Event date', last: DateTime.now()),
          kit.text('venue', 'Venue'),
          kit.text('attendance', 'Attendance', keyboard: TextInputType.number),
          kit.text('summary', 'Summary', maxLines: 4),
          Row(children: [
            Text('Photos (${_photos.length}/10)', style: const TextStyle(fontWeight: FontWeight.w600)),
            const Spacer(),
            TextButton.icon(
              onPressed: _photos.length >= 10
                  ? null
                  : () async {
                      final p = await pickPhoto(context);
                      if (p != null) setState(() => _photos.add(p));
                    },
              icon: const Icon(Icons.add_a_photo_outlined),
              label: const Text('Add photo'),
            ),
          ]),
          if (_photos.isNotEmpty)
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (var i = 0; i < _photos.length; i++)
                GestureDetector(onLongPress: () => setState(() => _photos.removeAt(i)), child: ClipRRect(borderRadius: BorderRadius.circular(6), child: PreviewImage(_photos[i], size: 76))),
            ]),
          if (_photos.isNotEmpty) const Text('Long-press a photo to remove it.', style: TextStyle(fontSize: 12, color: Colors.grey)),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: _busy ? null : _save,
            style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
            child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Submit report'),
          ),
        ]),
      ),
    );
  }
}
