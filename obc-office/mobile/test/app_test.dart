import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:obc_office/api.dart';
import 'package:obc_office/screens/login.dart';
import 'package:obc_office/session.dart';
import 'package:obc_office/version.dart';
import 'package:obc_office/widgets/form_kit.dart';
import 'package:provider/provider.dart';

void main() {
  group('ApiPage', () {
    test('reads Laravel resource pagination (meta.last_page)', () {
      final p = ApiPage.fromJson({
        'data': [
          {'id': 1},
        ],
        'meta': {'last_page': 4},
      });
      expect(p.items.single['id'], 1);
      expect(p.lastPage, 4);
    });

    test('reads plain paginator (last_page)', () {
      expect(ApiPage.fromJson({'data': [], 'last_page': 2}).lastPage, 2);
    });
  });

  group('FormKit', () {
    test('nests custom fields under extra and keeps others flat', () {
      final kit = FormKit({'name': 'Ram', 'extra.voter_id': 'ABC123', 'empty': null});
      kit.set('extra.blood_group', 'O+');
      final p = kit.payload();
      expect(p['name'], 'Ram');
      expect(p['extra'], {'voter_id': 'ABC123', 'blood_group': 'O+'});
      expect(p.containsKey('empty'), isFalse);
    });

    test('server validation errors clear when the field changes', () {
      final kit = FormKit({});
      kit.applyErrors(ApiException('x', status: 422, fieldErrors: {'mobile': 'Invalid'}));
      expect(kit.errors['mobile'], 'Invalid');
      kit.set('mobile', '9876543210');
      expect(kit.errors.containsKey('mobile'), isFalse);
    });
  });

  group('Session', () {
    Session signedIn(Map<String, String> perms) => Session()
      ..user = {
        'name': 'Test',
        'role': 'state_admin',
        'state_id': 26,
        'permissions': perms,
        'branding': {'org_name': 'Long Org Name', 'org_short_name': 'Org', 'theme_color': '#112233'},
      }
      ..meta = {
        'lookups': {
          'level': [
            {'value': 'state', 'label': 'State'},
            {'value': 'district', 'label': 'District'},
          ],
        },
        'districts': [
          {'id': 1, 'state_id': 26, 'name': 'Lucknow'},
          {'id': 2, 'state_id': 21, 'name': 'Jaipur'},
        ],
        'member_required_fields': ['name', 'mobile'],
      };

    test('permissions, branding and lookups come from the server', () {
      final s = signedIn({'members': 'manage', 'petitions': 'none', 'circulars': 'view'});
      expect(s.canManage('members'), isTrue);
      expect(s.canView('circulars'), isTrue);
      expect(s.canManage('circulars'), isFalse);
      expect(s.canView('petitions'), isFalse);
      expect(s.canView('unknown_module'), isFalse);
      expect(s.isNational, isFalse);
      expect(s.orgName, 'Org');
      expect(s.themeColor, const Color(0xff112233));
      expect(s.label('level', 'district'), 'District');
      expect(s.label('level', 'booth'), 'booth');
      expect(s.districtsOf(26).map((d) => d['name']), ['Lucknow']);
      expect(s.requiredFields('member'), ['name', 'mobile']);
    });
  });

  testWidgets('login screen shows fields and server settings', (tester) async {
    await tester.pumpWidget(ChangeNotifierProvider(
      create: (_) => Session(),
      child: const MaterialApp(home: LoginScreen()),
    ));
    expect(find.text('Username or email'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
    expect(find.text('Server settings'), findsOneWidget);
  });

  group('App updates & leader profile', () {
    test('version comparison', () {
      expect(isNewerVersion('1.2.0', '1.1.0'), isTrue);
      expect(isNewerVersion('1.1.0', '1.1.0'), isFalse);
      expect(isNewerVersion('1.0.9', '1.1.0'), isFalse);
      expect(isNewerVersion('2.0', '1.9.9'), isTrue);
      expect(isNewerVersion(null), isFalse);
      expect(isNewerVersion(''), isFalse);
    });

    test('leader profile comes from public branding before sign-in', () {
      final s = Session()
        ..publicBranding = {
          'leader_on_login': true,
          'leader_name': 'श्री अमरपाल मौर्य',
          'leader_designations': ['सांसद (राज्यसभा)', 'राष्ट्रीय अध्यक्ष'],
        };
      expect(s.signedIn, isFalse);
      expect(s.showLeader, isTrue);
      expect(s.leaderDesignations.length, 2);
      s.publicBranding['leader_on_login'] = false;
      expect(s.showLeader, isFalse);
    });
  });

  testWidgets('login screen shows the leader name and designations', (tester) async {
    final s = Session()
      ..publicBranding = {
        'leader_on_login': true,
        'leader_name': 'श्री अमरपाल मौर्य',
        'leader_designations': ['सांसद (राज्यसभा)'],
        'org_name': 'BJP OBC Morcha',
      };
    await tester.pumpWidget(ChangeNotifierProvider.value(value: s, child: const MaterialApp(home: LoginScreen())));
    expect(find.text('श्री अमरपाल मौर्य'), findsOneWidget);
    expect(find.text('सांसद (राज्यसभा)'), findsOneWidget);
    expect(find.text('BJP OBC Morcha'), findsOneWidget);
  });
}

