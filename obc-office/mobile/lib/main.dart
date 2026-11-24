import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import 'screens/home.dart';
import 'screens/login.dart';
import 'session.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(ChangeNotifierProvider(create: (_) => Session()..restore(), child: const OfficeApp()));
}

class OfficeApp extends StatelessWidget {
  const OfficeApp({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final theme = ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: session.themeColor, dynamicSchemeVariant: DynamicSchemeVariant.fidelity),
      cardTheme: const CardThemeData(elevation: 0.5),
      appBarTheme: const AppBarTheme(centerTitle: false),
    );
    return MaterialApp(
      title: session.signedIn ? session.orgName : 'Office',
      debugShowCheckedModeBanner: false,
      theme: theme,
      home: !session.ready
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : session.signedIn
              ? const HomeShell()
              : const LoginScreen(),
    );
  }
}
