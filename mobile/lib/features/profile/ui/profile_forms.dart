import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/auth/ui/login_screen.dart';
import 'package:rescuelens/features/feed/ui/home_header.dart';

void _toast(BuildContext c, String m) => ScaffoldMessenger.of(c)..hideCurrentSnackBar()..showSnackBar(SnackBar(content: Text(m), behavior: SnackBarBehavior.floating));

InputDecoration field(String label, {String? hint, IconData? icon}) => InputDecoration(labelText: label, hintText: hint, prefixIcon: icon == null ? null : Icon(icon), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)));

/// Sign in page reachable from Profile.
class AuthPage extends StatelessWidget {
  const AuthPage({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(tr('login'))), body: LoginScreen(onDone: () => Navigator.pop(context, true)));
}

/// Edit profile with live validation and optimistic save.
class EditProfileScreen extends StatefulWidget {
  final Map me;
  const EditProfileScreen({super.key, required this.me});
  @override
  State<EditProfileScreen> createState() => _EditProfileState();
}

class _EditProfileState extends State<EditProfileScreen> {
  final form = GlobalKey<FormState>();
  late final name = TextEditingController(text: '${widget.me['name'] ?? ''}');
  late final phone = TextEditingController(text: '${widget.me['phone'] ?? ''}');
  late final bio = TextEditingController(text: '${widget.me['bio'] ?? ''}');
  late final country = TextEditingController(text: '${widget.me['country_code'] ?? 'BD'}');
  late String unit = '${widget.me['distance_unit'] ?? 'km'}';
  bool busy = false;
  Future<void> save() async {
    if (!form.currentState!.validate()) return;
    setState(() => busy = true);
    try {
      final r = await Api.patch('/auth/me', {'name': name.text.trim(), 'phone': phone.text.trim().isEmpty ? null : phone.text.trim(), 'bio': bio.text.trim().isEmpty ? null : bio.text.trim(), 'country_code': country.text.trim().toUpperCase(), 'distance_unit': unit});
      Me.user = r['user'];
      if (mounted) { _toast(context, tr('saved')); Navigator.pop(context, true); }
    } catch (e) { if (mounted) { setState(() => busy = false); _toast(context, '$e'); } }
  }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(tr('edit_profile'))), body: Form(key: form, autovalidateMode: AutovalidateMode.onUserInteraction, child: ListView(padding: const EdgeInsets.all(16), children: [
    TextFormField(controller: name, decoration: field(tr('name'), icon: Icons.person_outline_rounded), validator: (v) => (v ?? '').trim().length < 2 ? tr('v_name') : null),
    const SizedBox(height: 12),
    TextFormField(controller: phone, keyboardType: TextInputType.phone, decoration: field(tr('phone'), hint: '+880 1711 000000', icon: Icons.phone_outlined),
      validator: (v) => (v ?? '').trim().isEmpty || RegExp(r'^\+?[0-9 ()]{6,20}$').hasMatch(v!.trim()) ? null : tr('v_phone')),
    const SizedBox(height: 12),
    TextFormField(controller: bio, maxLength: 200, maxLines: 3, decoration: field(tr('bio'), icon: Icons.notes_rounded)),
    const SizedBox(height: 4),
    TextFormField(controller: country, maxLength: 2, textCapitalization: TextCapitalization.characters, decoration: field(tr('country'), hint: 'BD', icon: Icons.flag_outlined),
      validator: (v) => RegExp(r'^[A-Za-z]{2}$').hasMatch((v ?? '').trim()) ? null : tr('v_country')),
    const SizedBox(height: 4),
    Text(tr('units'), style: const TextStyle(fontWeight: FontWeight.w700)), const SizedBox(height: 6),
    SegmentedButton<String>(segments: const [ButtonSegment(value: 'km', label: Text('km')), ButtonSegment(value: 'mi', label: Text('mi'))], selected: {unit}, onSelectionChanged: (v) => setState(() => unit = v.first)),
    const SizedBox(height: 20),
    FilledButton(onPressed: busy ? null : save, child: Padding(padding: const EdgeInsets.all(12), child: busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : Text(tr('save')))),
  ])));
}

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordState();
}

class _ChangePasswordState extends State<ChangePasswordScreen> {
  final form = GlobalKey<FormState>();
  final cur = TextEditingController(), pw = TextEditingController(), pw2 = TextEditingController();
  bool busy = false, show = false;
  Future<void> save() async {
    if (!form.currentState!.validate()) return;
    setState(() => busy = true);
    try {
      await Api.post('/auth/password', {'current_password': cur.text, 'password': pw.text, 'password_confirmation': pw2.text});
      if (mounted) { _toast(context, tr('password_changed')); Navigator.pop(context); }
    } catch (e) { if (mounted) { setState(() => busy = false); _toast(context, '$e'); } }
  }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(tr('change_password'))), body: Form(key: form, autovalidateMode: AutovalidateMode.onUserInteraction, child: ListView(padding: const EdgeInsets.all(16), children: [
    TextFormField(controller: cur, obscureText: !show, decoration: field(tr('current_password'), icon: Icons.lock_outline_rounded), validator: (v) => (v ?? '').isEmpty ? tr('v_required') : null),
    const SizedBox(height: 12),
    TextFormField(controller: pw, obscureText: !show, decoration: field(tr('new_password'), icon: Icons.lock_reset_rounded), validator: passwordRule),
    const SizedBox(height: 12),
    TextFormField(controller: pw2, obscureText: !show, decoration: field(tr('confirm_password'), icon: Icons.lock_reset_rounded), validator: (v) => v != pw.text ? tr('v_match') : null),
    CheckboxListTile(contentPadding: EdgeInsets.zero, value: show, onChanged: (v) => setState(() => show = v!), title: Text(tr('show_password'))),
    FilledButton(onPressed: busy ? null : save, child: Padding(padding: const EdgeInsets.all(12), child: Text(tr('save')))),
  ])));
}

String? passwordRule(String? v) => (v ?? '').length < 8 || !RegExp(r'[A-Za-z]').hasMatch(v!) || !RegExp(r'[0-9]').hasMatch(v) ? tr('v_password') : null;

Future<void> pickLanguage(BuildContext context) => showModalBottomSheet(context: context, isScrollControlled: true, showDragHandle: true, builder: (c) {
  var q = '';
  return StatefulBuilder(builder: (c, set) => SafeArea(child: Padding(padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(c).bottom), child: Column(mainAxisSize: MainAxisSize.min, children: [
    Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 8), child: TextField(autofocus: false, onChanged: (v) => set(() => q = v.toLowerCase()), decoration: field(tr('search_language'), icon: Icons.search_rounded))),
    for (final e in L10n.langs.entries.where((e) => q.isEmpty || e.value.toLowerCase().contains(q) || e.key.contains(q)))
      ListTile(title: Text(e.value), subtitle: Text(e.key.toUpperCase()), trailing: L10n.i.lang == e.key ? const Icon(Icons.check_circle_rounded, color: RL.primary) : null,
        onTap: () async { await L10n.i.set(e.key); if (Api.token != null) { Api.patch('/auth/me', {'locale': e.key}).ignore(); } if (c.mounted) Navigator.pop(c); }),
    const SizedBox(height: 12),
  ]))));
});

Future<void> pickTheme(BuildContext context) => showModalBottomSheet(context: context, showDragHandle: true, builder: (c) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
  for (final (k, i) in [('light', Icons.light_mode_rounded), ('dark', Icons.dark_mode_rounded), ('system', Icons.brightness_auto_rounded)])
    ListTile(leading: Icon(i), title: Text(tr('theme_$k')), trailing: L10n.i.theme == k ? const Icon(Icons.check_circle_rounded, color: RL.primary) : null,
      onTap: () async { await L10n.i.setTheme(k); if (Api.token != null) { Api.patch('/auth/me', {'theme': k}).ignore(); } if (c.mounted) Navigator.pop(c); }),
  const SizedBox(height: 12),
])));

Future<void> editRadius(BuildContext context, Map me) async {
  var v = ((me['alert_radius_km'] ?? 5) as num).toDouble();
  final ok = await showModalBottomSheet<bool>(context: context, showDragHandle: true, builder: (c) => StatefulBuilder(builder: (c, set) => SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(20, 0, 20, 20), child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
    Text(tr('alert_radius'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
    Text('${v.round()} km', style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: RL.primary)),
    Slider(value: v, min: 1, max: 50, divisions: 49, label: '${v.round()} km', onChanged: (x) => set(() => v = x)),
    SizedBox(width: double.infinity, child: FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(tr('save')))),
  ])))));
  if (ok != true) return;
  try { final r = await Api.patch('/auth/me', {'alert_radius_km': v.round()}); Me.user = r['user']; if (context.mounted) _toast(context, tr('saved')); } catch (e) { if (context.mounted) _toast(context, '$e'); }
}

Future<void> showLinked(BuildContext context) async {
  Map social = {};
  try { social = (await Api.get('/health'))['social'] ?? {}; } catch (_) {}
  if (!context.mounted) return;
  showModalBottomSheet(context: context, showDragHandle: true, builder: (c) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
    for (final p in ['google', 'facebook']) ListTile(leading: Icon(p == 'google' ? Icons.g_mobiledata_rounded : Icons.facebook_rounded, size: 32),
      title: Text(p == 'google' ? 'Google' : 'Facebook'), subtitle: Text(social[p] == true ? tr('linked_available') : tr('linked_disabled'))),
    const SizedBox(height: 12),
  ])));
}

Future<void> deleteAccount(BuildContext context, VoidCallback onChanged) async {
  if (!await confirmAction(context, title: tr('delete_account_q'), body: tr('delete_account_body'), confirm: tr('delete_account'), icon: Icons.delete_forever_rounded)) return;
  try { await Api.delete('/auth/me'); await Api.setToken(null); Me.user = null; onChanged(); } catch (e) { if (context.mounted) _toast(context, '$e'); }
}

/// Soft deleted posts that can be restored.
class TrashScreen extends StatefulWidget {
  const TrashScreen({super.key});
  @override
  State<TrashScreen> createState() => _TrashState();
}

class _TrashState extends State<TrashScreen> {
  List? items; bool error = false;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async {
    try { final r = await Api.get('/me/trash'); if (mounted) setState(() { items = r['data']; error = false; }); } catch (_) { if (mounted) setState(() { items = []; error = true; }); }
  }
  Future<void> restore(Map i) async {
    try { await Api.post('/incidents/${i['id']}/restore', {}); setState(() => items!.remove(i)); if (mounted) _toast(context, tr('restored')); } catch (e) { if (mounted) _toast(context, '$e'); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(tr('my_trash'))), body: RefreshIndicator(onRefresh: load, child: ListView(padding: const EdgeInsets.all(12), children: [
    Padding(padding: const EdgeInsets.all(4), child: Text(tr('trash_sub'), style: const TextStyle(color: RL.text2))),
    if (items == null) const PostSkeleton(),
    if (items != null && items!.isEmpty) Padding(padding: const EdgeInsets.all(40), child: Column(children: [
      Icon(Icons.delete_outline_rounded, size: 48, color: RL.text2.withValues(alpha: .6)), const SizedBox(height: 12),
      Text(tr('trash_empty'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2))])),
    for (final i in items ?? []) Card(child: ListTile(title: Text('${i['title']}'), subtitle: Text('${tr('deleted')} ${i['deleted_at'] ?? ''}'),
      trailing: TextButton.icon(onPressed: () => restore(i), icon: const Icon(Icons.restore_rounded), label: Text(tr('restore'))))),
  ])));
}
