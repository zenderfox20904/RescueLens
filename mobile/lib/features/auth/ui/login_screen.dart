import 'dart:async';
import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';

class LoginScreen extends StatefulWidget {
  final VoidCallback onDone;
  const LoginScreen({super.key, required this.onDone});
  @override
  State<LoginScreen> createState() => _LoginState();
}

class _LoginState extends State<LoginScreen> {
  final email = TextEditingController(), pass = TextEditingController();
  String? err; bool busy = false;
  Future<void> submit() async {
    setState(() { busy = true; err = null; });
    try { final r = await Api.post('/auth/login', {'email': email.text.trim(), 'password': pass.text, 'device_name': 'flutter'}); await Api.setToken(r['token']); widget.onDone(); }
    catch (_) { setState(() => err = tr('login_failed')); } finally { if (mounted) setState(() => busy = false); }
  }
  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.fromLTRB(24, 24, 24, 120), children: [
    const Center(child: LogoMark(size: 72)), const SizedBox(height: 12),
    const Center(child: Text('RescueLens', style: TextStyle(color: RL.primary, fontSize: 34, fontWeight: FontWeight.w800))),
    const SizedBox(height: 24),
    TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: InputDecoration(labelText: tr('email'), border: const OutlineInputBorder())),
    const SizedBox(height: 12),
    TextField(controller: pass, obscureText: true, decoration: InputDecoration(labelText: tr('password'), border: const OutlineInputBorder())),
    if (err != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(err!, style: const TextStyle(color: Color(0xFFE41E3F)))),
    const SizedBox(height: 12),
    FilledButton(onPressed: busy ? null : submit, child: Padding(padding: const EdgeInsets.all(12), child: Text(tr('login'), style: const TextStyle(fontSize: 17)))),
    const SizedBox(height: 8),
    Text(tr('sign_in_prompt'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2)),
  ]);
}
