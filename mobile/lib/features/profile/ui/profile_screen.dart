import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';

/// Profile: language choice (persisted) and sign out.
class ProfileScreen extends StatelessWidget {
  final VoidCallback onChanged;
  const ProfileScreen({super.key, required this.onChanged});
  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 120), children: [
    Entrance(child: Card(child: Column(children: [
      ListTile(leading: const Icon(Icons.translate_rounded), title: Text(tr('language'), style: const TextStyle(fontWeight: FontWeight.w700))),
      RadioGroup<String>(groupValue: L10n.i.lang, onChanged: (v) async { await L10n.i.set(v!); onChanged(); }, child: Column(children: [
        for (final e in L10n.langs.entries) RadioListTile<String>(value: e.key, title: Text(e.value)),
      ])),
    ]))),
    if (Api.token != null) Entrance(index: 1, child: Card(child: ListTile(leading: const Icon(Icons.logout_rounded), title: Text(tr('logout')), onTap: () async { await Api.setToken(null); onChanged(); }))),
    const Padding(padding: EdgeInsets.all(16), child: Text('© 2026 RescueLens', textAlign: TextAlign.center, style: TextStyle(color: RL.text2, fontSize: 12))),
  ]);
}
