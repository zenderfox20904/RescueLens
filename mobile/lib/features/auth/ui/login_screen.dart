import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';

InputDecoration _f(String label, IconData icon, {String? hint}) => InputDecoration(labelText: label, hintText: hint, prefixIcon: Icon(icon), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)));
String? _pw(String? v) => (v ?? '').length < 8 || !RegExp(r'[A-Za-z]').hasMatch(v!) || !RegExp(r'[0-9]').hasMatch(v) ? tr('v_password') : null;
String? _email(String? v) => RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch((v ?? '').trim()) ? null : tr('v_email');

/// Sign in, create account (with account types) and password reset.
class LoginScreen extends StatefulWidget {
  final VoidCallback onDone;
  const LoginScreen({super.key, required this.onDone});
  @override
  State<LoginScreen> createState() => _LoginState();
}

class _LoginState extends State<LoginScreen> {
  final form = GlobalKey<FormState>();
  final email = TextEditingController(), pass = TextEditingController();
  String? err; bool busy = false, show = false; Map social = {};
  @override
  void initState() { super.initState(); Api.get('/health').then((r) { if (mounted) setState(() => social = r['social'] ?? {}); }).catchError((_) {}); }
  Future<void> submit() async {
    if (!form.currentState!.validate()) return;
    setState(() { busy = true; err = null; });
    try { final r = await Api.post('/auth/login', {'email': email.text.trim(), 'password': pass.text, 'device_name': 'flutter'}); await Api.setToken(r['token']); widget.onDone(); }
    catch (_) { setState(() => err = tr('login_failed')); } finally { if (mounted) setState(() => busy = false); }
  }
  Future<void> socialLogin(String p) async {
    await launchUrl(Uri.parse('${Api.base}/auth/$p/redirect'), mode: LaunchMode.externalApplication);
  }
  @override
  Widget build(BuildContext context) => Form(key: form, autovalidateMode: AutovalidateMode.onUserInteraction, child: ListView(padding: const EdgeInsets.fromLTRB(24, 20, 24, 120), children: [
    const Entrance(child: Center(child: LogoMark(size: 64))), const SizedBox(height: 10),
    const Center(child: Text('RescueLens', style: TextStyle(color: RL.primary, fontSize: 30, fontWeight: FontWeight.w800))),
    Center(child: Text(tr('sign_in_prompt'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2))),
    const SizedBox(height: 20),
    TextFormField(controller: email, keyboardType: TextInputType.emailAddress, autofillHints: const [AutofillHints.email], decoration: _f(tr('email'), LucideIcons.atSign), validator: _email),
    const SizedBox(height: 12),
    TextFormField(controller: pass, obscureText: !show, autofillHints: const [AutofillHints.password], validator: (v) => (v ?? '').isEmpty ? tr('v_required') : null,
      decoration: _f(tr('password'), LucideIcons.lock).copyWith(suffixIcon: IconButton(icon: Icon(show ? LucideIcons.eyeOff : LucideIcons.eye), onPressed: () => setState(() => show = !show)))),
    Align(alignment: AlignmentDirectional.centerEnd, child: TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ForgotPasswordScreen(email: email.text.trim()))), child: Text(tr('forgot_password')))),
    if (err != null) Padding(padding: const EdgeInsets.only(bottom: 8), child: Text(err!, style: const TextStyle(color: Color(0xFFE41E3F)))),
    FilledButton(onPressed: busy ? null : submit, child: Padding(padding: const EdgeInsets.all(12), child: busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : Text(tr('login'), style: const TextStyle(fontSize: 17)))),
    if (social['google'] == true || social['facebook'] == true) ...[
      Padding(padding: const EdgeInsets.symmetric(vertical: 14), child: Row(children: [const Expanded(child: Divider()), Padding(padding: const EdgeInsets.symmetric(horizontal: 10), child: Text(tr('or'), style: const TextStyle(color: RL.text2))), const Expanded(child: Divider())])),
      if (social['google'] == true) OutlinedButton.icon(onPressed: () => socialLogin('google'), icon: const Icon(LucideIcons.globe, size: 28), label: Text(tr('continue_google'))),
      if (social['facebook'] == true) Padding(padding: const EdgeInsets.only(top: 8), child: OutlinedButton.icon(onPressed: () => socialLogin('facebook'), icon: const Icon(LucideIcons.userRound, color: Color(0xFF1877F2)), label: Text(tr('continue_facebook')))),
    ],
    const SizedBox(height: 16), const Divider(),
    const SizedBox(height: 8),
    OutlinedButton(style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF31A24C), side: const BorderSide(color: Color(0xFF31A24C))),
      onPressed: () async { final ok = await Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen())); if (ok == true) widget.onDone(); },
      child: Padding(padding: const EdgeInsets.all(12), child: Text(tr('create_account'), style: const TextStyle(fontWeight: FontWeight.w700)))),
  ]));
}

/// Multi step registration: account type, details, organization info.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterState();
}

class _RegisterState extends State<RegisterScreen> {
  int step = 0; String type = 'citizen'; String otherType = 'hospital';
  final k1 = GlobalKey<FormState>(), k2 = GlobalKey<FormState>();
  final name = TextEditingController(), email = TextEditingController(), pass = TextEditingController();
  final orgName = TextEditingController(), orgPhone = TextEditingController(), orgAddr = TextEditingController(), hours = TextEditingController();
  double coverage = 10; double? lat, lng; bool busy = false, locating = false; String? err;
  static const types = [('citizen', LucideIcons.user), ('help_center', LucideIcons.headset), ('ngo', LucideIcons.handHeart), ('other', LucideIcons.building2)];
  static const others = ['hospital', 'ambulance', 'fire_service', 'police', 'volunteer'];
  bool get isOrg => type != 'citizen';
  String get apiType => type == 'other' ? otherType : type;

  Future<void> locate() async {
    setState(() => locating = true);
    try {
      var p = await Geolocator.checkPermission();
      if (p == LocationPermission.denied) p = await Geolocator.requestPermission();
      final pos = await Geolocator.getCurrentPosition();
      setState(() { lat = pos.latitude; lng = pos.longitude; });
    } catch (_) { if (mounted) setState(() => err = tr('location_failed')); }
    if (mounted) setState(() => locating = false);
  }

  Future<void> submit() async {
    if (isOrg && !(k2.currentState?.validate() ?? false)) return;
    if (isOrg && lat == null) { setState(() => err = tr('org_location_needed')); return; }
    setState(() { busy = true; err = null; });
    try {
      final r = await Api.post('/auth/register', {'name': name.text.trim(), 'email': email.text.trim(), 'password': pass.text, 'locale': L10n.i.lang, 'device_name': 'flutter', 'account_type': apiType,
        if (isOrg) 'organization': {'name': orgName.text.trim(), 'phone': orgPhone.text.trim(), 'address': orgAddr.text.trim(), 'lat': lat, 'lng': lng, 'coverage_km': coverage.round(), if (hours.text.trim().isNotEmpty) 'service_hours': hours.text.trim()}});
      await Api.setToken(r['token']);
      if (mounted) Navigator.pop(context, true);
    } catch (e) { if (mounted) setState(() { err = '$e'; busy = false; }); }
  }

  void next() {
    if (step == 1 && !k1.currentState!.validate()) return;
    if (step == 1 && !isOrg) { submit(); return; }
    if (step == 2) { submit(); return; }
    setState(() => step++);
  }

  @override
  Widget build(BuildContext context) {
    final total = isOrg ? 3 : 2;
    return Scaffold(appBar: AppBar(title: Text(tr('create_account'))), body: ListView(padding: const EdgeInsets.fromLTRB(20, 16, 20, 40), children: [
      Row(children: [for (var i = 0; i < total; i++) Expanded(child: AnimatedContainer(duration: const Duration(milliseconds: 250), height: 4, margin: const EdgeInsets.symmetric(horizontal: 3),
        decoration: BoxDecoration(color: i <= step ? RL.primary : RL.text2.withValues(alpha: .25), borderRadius: BorderRadius.circular(4))))]),
      const SizedBox(height: 6),
      Text('${tr('step')} ${step + 1} / $total', style: const TextStyle(color: RL.text2, fontSize: 12)),
      const SizedBox(height: 12),
      AnimatedSwitcher(duration: const Duration(milliseconds: 250), child: KeyedSubtree(key: ValueKey(step), child: switch (step) {
        0 => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(tr('acct_choose'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)), const SizedBox(height: 10),
          for (final (t, ic) in types) Card(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: BorderSide(color: type == t ? RL.primary : Colors.transparent, width: 2)),
            child: ListTile(leading: CircleAvatar(backgroundColor: RL.primary.withValues(alpha: .12), child: Icon(ic, color: RL.primary)), title: Text(tr('acct_$t'), style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text(tr('acct_${t}_sub')), trailing: Icon(type == t ? LucideIcons.circleDot : LucideIcons.circle, color: type == t ? RL.primary : RL.text2), onTap: () => setState(() => type = t))),
          if (type == 'other') Padding(padding: const EdgeInsets.only(top: 8), child: DropdownButtonFormField<String>(initialValue: otherType, decoration: _f(tr('org_type'), LucideIcons.layoutGrid),
            items: [for (final o in others) DropdownMenuItem(value: o, child: Text(tr('acct_$o')))], onChanged: (v) => setState(() => otherType = v!))),
        ]),
        1 => Form(key: k1, autovalidateMode: AutovalidateMode.onUserInteraction, child: Column(children: [
          TextFormField(controller: name, decoration: _f(tr(isOrg ? 'contact_name' : 'name'), LucideIcons.user), validator: (v) => (v ?? '').trim().length < 2 ? tr('v_name') : null),
          const SizedBox(height: 12),
          TextFormField(controller: email, keyboardType: TextInputType.emailAddress, decoration: _f(tr('email'), LucideIcons.atSign), validator: _email),
          const SizedBox(height: 12),
          TextFormField(controller: pass, obscureText: true, decoration: _f(tr('password'), LucideIcons.lock, hint: tr('v_password')), validator: _pw),
        ])),
        _ => Form(key: k2, autovalidateMode: AutovalidateMode.onUserInteraction, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          TextFormField(controller: orgName, decoration: _f(tr('org_name'), LucideIcons.building2), validator: (v) => (v ?? '').trim().length < 2 ? tr('v_required') : null),
          const SizedBox(height: 12),
          TextFormField(controller: orgPhone, keyboardType: TextInputType.phone, decoration: _f(tr('phone'), LucideIcons.phone), validator: (v) => RegExp(r'^[0-9+ ()]{3,30}$').hasMatch((v ?? '').trim()) ? null : tr('v_phone')),
          const SizedBox(height: 12),
          TextFormField(controller: orgAddr, decoration: _f(tr('address'), LucideIcons.mapPin)),
          const SizedBox(height: 12),
          TextFormField(controller: hours, decoration: _f(tr('service_hours'), LucideIcons.clock, hint: '24/7')),
          const SizedBox(height: 12),
          OutlinedButton.icon(onPressed: locating ? null : locate, icon: Icon(lat == null ? LucideIcons.locateFixed : LucideIcons.circleCheck, color: lat == null ? null : const Color(0xFF31A24C)),
            label: Text(lat == null ? tr('org_use_location') : tr('org_location_set'))),
          const SizedBox(height: 12),
          Text('${tr('coverage')}: ${coverage.round()} km', style: const TextStyle(fontWeight: FontWeight.w700)),
          Slider(value: coverage, min: 1, max: 100, divisions: 99, onChanged: (v) => setState(() => coverage = v)),
        ])),
      })),
      if (err != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(err!, style: const TextStyle(color: Color(0xFFE41E3F)))),
      const SizedBox(height: 16),
      Row(children: [
        if (step > 0) Expanded(child: OutlinedButton(onPressed: busy ? null : () => setState(() => step--), child: Padding(padding: const EdgeInsets.all(12), child: Text(tr('back'))))),
        if (step > 0) const SizedBox(width: 10),
        Expanded(child: FilledButton(onPressed: busy ? null : next, child: Padding(padding: const EdgeInsets.all(12), child: busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
          : Text(step == total - 1 ? tr('create_account') : tr('next'))))),
      ]),
    ]));
  }
}

/// Request a 6 digit code by email, then set a new password.
class ForgotPasswordScreen extends StatefulWidget {
  final String email;
  const ForgotPasswordScreen({super.key, this.email = ''});
  @override
  State<ForgotPasswordScreen> createState() => _ForgotState();
}

class _ForgotState extends State<ForgotPasswordScreen> {
  final form = GlobalKey<FormState>();
  late final email = TextEditingController(text: widget.email);
  final code = TextEditingController(), pw = TextEditingController(), pw2 = TextEditingController();
  bool sent = false, busy = false; String? msg;
  Future<void> go() async {
    if (!form.currentState!.validate()) return;
    setState(() { busy = true; msg = null; });
    try {
      if (!sent) { final r = await Api.post('/auth/forgot', {'email': email.text.trim()}); setState(() { sent = true; msg = r['message']; }); }
      else {
        await Api.post('/auth/reset', {'email': email.text.trim(), 'code': code.text.trim(), 'password': pw.text, 'password_confirmation': pw2.text});
        if (mounted) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('password_changed')), behavior: SnackBarBehavior.floating)); Navigator.pop(context); }
      }
    } catch (e) { setState(() => msg = '$e'); }
    if (mounted) setState(() => busy = false);
  }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(tr('forgot_password'))), body: Form(key: form, autovalidateMode: AutovalidateMode.onUserInteraction, child: ListView(padding: const EdgeInsets.all(20), children: [
    const Icon(LucideIcons.keyRound, size: 56, color: RL.primary), const SizedBox(height: 10),
    Text(tr(sent ? 'reset_enter_code' : 'reset_intro'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2)),
    const SizedBox(height: 16),
    TextFormField(controller: email, enabled: !sent, keyboardType: TextInputType.emailAddress, decoration: _f(tr('email'), LucideIcons.atSign), validator: _email),
    if (sent) ...[
      const SizedBox(height: 12),
      TextFormField(controller: code, keyboardType: TextInputType.number, maxLength: 6, decoration: _f(tr('reset_code'), LucideIcons.keySquare), validator: (v) => RegExp(r'^\d{6}$').hasMatch(v ?? '') ? null : tr('v_code')),
      TextFormField(controller: pw, obscureText: true, decoration: _f(tr('new_password'), LucideIcons.lock), validator: _pw),
      const SizedBox(height: 12),
      TextFormField(controller: pw2, obscureText: true, decoration: _f(tr('confirm_password'), LucideIcons.lock), validator: (v) => v != pw.text ? tr('v_match') : null),
    ],
    if (msg != null) Padding(padding: const EdgeInsets.only(top: 10), child: Text(msg!, textAlign: TextAlign.center)),
    const SizedBox(height: 16),
    FilledButton(onPressed: busy ? null : go, child: Padding(padding: const EdgeInsets.all(12), child: Text(tr(sent ? 'reset_set' : 'reset_send')))),
    if (sent) TextButton(onPressed: busy ? null : () => setState(() { sent = false; }), child: Text(tr('reset_resend'))),
  ])));
}
