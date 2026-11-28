import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api.dart';
import '../session.dart';
import '../version.dart';
import '../widgets/common.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _user = TextEditingController();
  final _pass = TextEditingController();
  bool _busy = false;
  bool _hide = true;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Session>().login(_user.text, _pass.text);
    } on ApiException catch (e) {
      setState(() => _error = e.fieldErrors['username'] ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _serverDialog() async {
    final c = TextEditingController(text: Api.instance.baseUrl);
    final url = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Server address'),
        content: TextField(controller: c, keyboardType: TextInputType.url, decoration: const InputDecoration(hintText: 'https://office.example.org')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, c.text), child: const Text('Save')),
        ],
      ),
    );
    if (url != null && url.trim().isNotEmpty) {
      await Api.instance.setBaseUrl(url);
      if (!mounted) return;
      toast(context, 'Server set to ${Api.instance.baseUrl}');
      context.read<Session>().loadPublicBranding();
    }
  }

  Widget _leaderHeader(Session s, ColorScheme scheme) {
    final photo = s.branding['leader_photo_url'] as String?;
    final name = (s.branding['leader_name'] ?? '').toString();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [scheme.primary, Color.lerp(scheme.primary, Colors.black, 0.25)!],
        ),
      ),
      child: Column(children: [
        Container(
          width: 132,
          height: 132,
          decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 4), color: Colors.white24),
          clipBehavior: Clip.antiAlias,
          child: photo == null
              ? Center(child: Text(initials(name.replaceAll('श्री', '').replaceAll('Shri', '')), style: const TextStyle(fontSize: 44, color: Colors.white, fontWeight: FontWeight.bold)))
              : Image.network(photo, fit: BoxFit.cover, alignment: Alignment.topCenter,
                  errorBuilder: (_, _, _) => const Icon(Icons.person, size: 64, color: Colors.white)),
        ),
        const SizedBox(height: 14),
        Text(name, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
        for (final line in s.leaderDesignations)
          Padding(
            padding: const EdgeInsets.only(top: 3),
            child: Text(line, textAlign: TextAlign.center, style: TextStyle(color: Colors.white.withValues(alpha: 0.92), fontSize: 14, fontWeight: FontWeight.w500)),
          ),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final s = context.watch<Session>();
    final latest = s.branding['app_version'] as String?;
    final downloadUrl = (s.branding['app_page_url'] ?? s.branding['app_download_url']) as String?;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                if (s.showLeader) ...[
                  _leaderHeader(s, scheme),
                  const SizedBox(height: 20),
                ] else ...[
                  CircleAvatar(radius: 36, backgroundColor: scheme.primary, child: const Icon(Icons.account_balance, color: Colors.white, size: 34)),
                  const SizedBox(height: 16),
                ],
                Text((s.branding['org_name'] ?? 'Office Portal').toString(), textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                Text('Sign in with your office account', textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade600)),
                if (isNewerVersion(latest) && downloadUrl != null)
                  Card(
                    margin: const EdgeInsets.only(top: 16),
                    color: scheme.primaryContainer,
                    child: ListTile(
                      leading: const Icon(Icons.system_update),
                      title: Text('New version $latest available'),
                      subtitle: const Text('Tap to download and install'),
                      onTap: () => launchUrl(Uri.parse(downloadUrl), mode: LaunchMode.externalApplication),
                    ),
                  ),
                const SizedBox(height: 24),
                TextField(
                  controller: _user,
                  autofillHints: const [AutofillHints.username],
                  decoration: const InputDecoration(labelText: 'Username or email', border: OutlineInputBorder(), prefixIcon: Icon(Icons.person_outline)),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _pass,
                  obscureText: _hide,
                  autofillHints: const [AutofillHints.password],
                  onSubmitted: (_) => _submit(),
                  decoration: InputDecoration(
                    labelText: 'Password',
                    border: const OutlineInputBorder(),
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(icon: Icon(_hide ? Icons.visibility : Icons.visibility_off), onPressed: () => setState(() => _hide = !_hide)),
                  ),
                ),
                if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: const TextStyle(color: Colors.red))),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
                  child: _busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Sign in'),
                ),
                const SizedBox(height: 12),
                TextButton.icon(onPressed: _serverDialog, icon: const Icon(Icons.dns_outlined, size: 18), label: const Text('Server settings')),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
