import 'dart:async';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/feed/ui/feed_screen.dart';
import 'package:rescuelens/features/incident/ui/incident_widgets.dart';

/// Report flow: pick photo, upload, AI suggestion (labeled), edit, explicit confirmation, post.
class ReportScreen extends StatefulWidget {
  const ReportScreen({super.key});
  @override
  State<ReportScreen> createState() => _ReportState();
}

class _ReportState extends State<ReportScreen> {
  XFile? file; Map? analysis; String? mediaId; String? err; bool busy = false, confirm = false;
  final title = TextEditingController(), desc = TextEditingController();
  String category = 'road_accident', severity = 'moderate';

  Future<void> pick(ImageSource src) async {
    final f = await ImagePicker().pickImage(source: src, maxWidth: 2048, imageQuality: 85);
    if (f == null) return;
    setState(() { file = f; busy = true; err = null; });
    try {
      mediaId = (await Api.upload('/media', f.path))['id'];
      final a = (await Api.post('/analyses', {'media_id': mediaId, 'ai_consent': true, 'language': curLang}))['analysis'];
      setState(() {
        analysis = a;
        final r = a['result'];
        if (r != null) { title.text = r['suggested_title']; desc.text = r['summary']; }
        category = a['primary_hazard'] ?? category; severity = a['severity'] ?? severity;
      });
    } catch (_) { setState(() => err = tr('action_failed')); } finally { setState(() => busy = false); }
  }

  Future<void> post() async {
    setState(() => busy = true);
    try {
      final r = await Api.post('/incidents', {'title': title.text, 'description': desc.text, 'category': category, 'severity': severity, 'lat': 23.8103, 'lng': 90.4125,
        'consent_public': true, 'media_ids': [?mediaId], if (analysis != null) 'analysis_id': analysis!['id']}, headers: {'Idempotency-Key': 'rl_m_${DateTime.now().microsecondsSinceEpoch}'});
      if (mounted) openDetail(context, r['data']['id']);
    } catch (_) { setState(() => err = tr('action_failed')); } finally { if (mounted) setState(() => busy = false); }
  }

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 120), children: [
    const SosBanner(),
    Row(children: [
      Expanded(child: OutlinedButton.icon(onPressed: busy ? null : () => pick(ImageSource.camera), icon: const Icon(Icons.photo_camera), label: Text(tr('camera')))),
      const SizedBox(width: 8),
      Expanded(child: OutlinedButton.icon(onPressed: busy ? null : () => pick(ImageSource.gallery), icon: const Icon(Icons.photo), label: Text(tr('gallery')))),
    ]),
    if (busy) const Padding(padding: EdgeInsets.all(8), child: Shim(child: Column(children: [Sk(h: 14), SizedBox(height: 8), Sk(w: 200, h: 14)]))),
    if (analysis != null) Card(color: RL.primary.withValues(alpha: .08), child: Padding(padding: const EdgeInsets.all(12), child: Text('✨ ${tr('ai_suggestion')}${analysis!['mode'] == 'demo' ? ' (DEMO)' : ''}'))),
    if (file != null) ...[
      TextField(controller: title, maxLength: 160, decoration: InputDecoration(labelText: tr('title'))),
      TextField(controller: desc, maxLines: 3, decoration: InputDecoration(labelText: tr('description'))),
      Wrap(spacing: 6, children: [for (final c in RL.catIcon.keys) ChoiceChip(label: Text(RL.catIcon[c]!), selected: category == c, onSelected: (_) => setState(() => category = c))]),
      Wrap(spacing: 6, children: [for (final s in RL.sev.keys) ChoiceChip(label: Text(tr(s)), selected: severity == s, onSelected: (_) => setState(() => severity = s))]),
      CheckboxListTile(value: confirm, onChanged: (v) => setState(() => confirm = v ?? false), title: Text(tr('confirm_publish'))),
      FilledButton(onPressed: confirm && !busy && title.text.isNotEmpty ? post : null, child: Text(tr('post'))),
    ],
    if (err != null) Text(err!, style: const TextStyle(color: Color(0xFFE41E3F))),
  ]);
}
