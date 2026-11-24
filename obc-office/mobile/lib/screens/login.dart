import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../session.dart';
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
      if (mounted) toast(context, 'Server set to ${Api.instance.baseUrl}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                CircleAvatar(radius: 36, backgroundColor: scheme.primary, child: const Icon(Icons.account_balance, color: Colors.white, size: 34)),
                const SizedBox(height: 16),
                Text('Office Portal', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                Text('Sign in with your office account', textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade600)),
                const SizedBox(height: 28),
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
