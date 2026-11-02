import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'dart:io';
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
  XFile? file; String? poster; bool isVideo = false; Map? analysis; String? mediaId; String? err; bool busy = false, confirm = false;
  final title = TextEditingController(), desc = TextEditingController();
  String category = 'road_accident', severity = 'moderate';

  Future<void> pick(ImageSource src, {bool video = false}) async {
    final XFile? f;
    try {
      f = video ? await ImagePicker().pickVideo(source: src, maxDuration: const Duration(seconds: 60)) : await ImagePicker().pickImage(source: src, maxWidth: 2048, imageQuality: 85);
    } catch (_) { setState(() => err = tr(video ? 'video_pick_failed' : 'camera_unavailable')); return; }
    if (f == null) return;
    setState(() { file = f; isVideo = video; poster = null; busy = true; err = null; });
    try {
      final m = await Api.upload('/media', f.path, field: video ? 'video' : 'image');
      mediaId = m['id']; if (video) setState(() => poster = m['url']);
      final a = (await Api.post('/analyses', {'media_id': mediaId, 'ai_consent': true, 'language': curLang}))['analysis'];
      setState(() {
        analysis = a;
        final x = a['assessment'] as Map?;
        if (x != null) {
          if (title.text.isEmpty) title.text = x['suggested_title'] ?? '';
          if (desc.text.isEmpty) desc.text = x['summary'] ?? '';
          if (RL.catIcon.containsKey(x['category'])) category = x['category'];
          if (RL.sev.containsKey(x['severity'])) severity = x['severity'];
        }
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
      Expanded(child: OutlinedButton.icon(onPressed: busy ? null : () => pick(ImageSource.camera), icon: const Icon(LucideIcons.camera), label: Text(tr('camera')))),
      const SizedBox(width: 8),
      Expanded(child: OutlinedButton.icon(onPressed: busy ? null : () => pick(ImageSource.gallery), icon: const Icon(LucideIcons.image), label: Text(tr('gallery')))),
      const SizedBox(width: 8),
      Expanded(child: OutlinedButton.icon(onPressed: busy ? null : () => pick(ImageSource.gallery, video: true), icon: const Icon(LucideIcons.film), label: Text(tr('video')))),
    ]),
    if (file != null) Padding(padding: const EdgeInsets.only(top: 12), child: ClipRRect(borderRadius: BorderRadius.circular(12), child: isVideo
        ? Stack(alignment: Alignment.center, children: [
            Container(height: 200, color: Colors.black, child: poster != null ? Image.network(poster!, height: 200, width: double.infinity, fit: BoxFit.cover) : null),
            const Icon(LucideIcons.circlePlay, size: 48, color: Colors.white),
          ])
        : Image.file(File(file!.path), height: 200, width: double.infinity, fit: BoxFit.cover))),
    if (busy) Padding(padding: const EdgeInsets.only(top: 12), child: Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)), const SizedBox(width: 12), Expanded(child: Text(tr('ai_analyzing')))]),
      const SizedBox(height: 12), const Shim(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Sk(h: 14), SizedBox(height: 8), Sk(w: 200, h: 14)])),
    ])))),
    if (analysis?['assessment'] != null) Padding(padding: const EdgeInsets.only(top: 12), child: AiCard(analysis!)),
    if (file != null) ...[
      TextField(controller: title, maxLength: 160, decoration: InputDecoration(labelText: tr('title'))),
      TextField(controller: desc, maxLines: 3, decoration: InputDecoration(labelText: tr('description'))),
      const SizedBox(height: 8),
      Text(tr('category'), style: const TextStyle(fontWeight: FontWeight.w600)), const SizedBox(height: 8),
      Wrap(spacing: 8, runSpacing: 8, children: [for (final c in RL.catIcon.keys) ChoiceChip(showCheckmark: false, avatar: Icon(RL.cat(c), size: 18), label: Text(tr('c_$c')), selected: category == c, onSelected: (_) => setState(() => category = c))]),
      const SizedBox(height: 16),
      Text(tr('severity'), style: const TextStyle(fontWeight: FontWeight.w600)), const SizedBox(height: 8),
      Wrap(spacing: 8, runSpacing: 8, children: [for (final s in RL.sev.keys) ChoiceChip(showCheckmark: false, label: Text(tr(s)), selected: severity == s, selectedColor: RL.sev[s]!.withValues(alpha: .25), onSelected: (_) => setState(() => severity = s))]),
      CheckboxListTile(value: confirm, onChanged: (v) => setState(() => confirm = v ?? false), title: Text(tr('confirm_publish'))),
      FilledButton.icon(onPressed: confirm && !busy && title.text.isNotEmpty ? post : null, icon: const Icon(LucideIcons.send, size: 18), label: Text(tr('post'))),
    ],
    if (err != null) Text(err!, style: const TextStyle(color: Color(0xFFE41E3F))),
  ]);
}

/// Meta AI result card: category, severity, confidence, reasons and recommended responders.
class AiCard extends StatelessWidget {
  final Map a;
  const AiCard(this.a, {super.key});
  @override
  Widget build(BuildContext context) {
    final x = a['assessment'] as Map; final ok = x['is_incident'] == true;
    final conf = ((x['confidence'] ?? 0) as num).toDouble(); final sev = '${x['severity']}';
    final color = RL.sev[sev] ?? RL.primary;
    return Card(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: BorderSide(color: (ok ? color : RL.text2).withValues(alpha: .5))), child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        const Icon(LucideIcons.sparkles, color: RL.primary, size: 20), const SizedBox(width: 8),
        Expanded(child: Text(tr('ai_suggestion'), style: const TextStyle(fontWeight: FontWeight.w700))),
        if (a['mode'] == 'demo') Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2), decoration: BoxDecoration(color: Colors.amber.withValues(alpha: .2), borderRadius: BorderRadius.circular(8)), child: Text(tr('demo'), style: const TextStyle(color: Colors.amber, fontSize: 12, fontWeight: FontWeight.w700))),
      ]),
      const SizedBox(height: 12),
      if (!ok) Text(tr('ai_not_incident'), style: TextStyle(color: RL.text2))
      else Wrap(spacing: 8, runSpacing: 8, children: [
        Chip(avatar: Icon(RL.cat(x['category']), size: 18), label: Text(tr('c_${x['category']}'))),
        Chip(backgroundColor: color.withValues(alpha: .2), side: BorderSide.none, label: Text(tr(sev), style: TextStyle(color: color, fontWeight: FontWeight.w700))),
      ]),
      const SizedBox(height: 12),
      Row(children: [Text(tr('ai_confidence'), style: TextStyle(color: RL.text2, fontSize: 13)), const Spacer(), Text('${(conf * 100).round()}%', style: const TextStyle(fontWeight: FontWeight.w600))]),
      const SizedBox(height: 4),
      ClipRRect(borderRadius: BorderRadius.circular(4), child: TweenAnimationBuilder<double>(tween: Tween(begin: 0, end: conf), duration: const Duration(milliseconds: 600),
        builder: (_, v, _) => LinearProgressIndicator(value: v, minHeight: 6, color: ok ? color : RL.text2, backgroundColor: RL.text2.withValues(alpha: .15)))),
      if ((x['reasons'] as List?)?.isNotEmpty == true) ...[
        const SizedBox(height: 12), Text(tr('ai_why'), style: const TextStyle(fontWeight: FontWeight.w600)), const SizedBox(height: 4),
        for (final r in x['reasons']) Padding(padding: const EdgeInsets.only(top: 4), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('•  ', style: TextStyle(color: RL.text2)), Expanded(child: Text('$r', style: const TextStyle(fontSize: 13)))])),
      ],
      if ((x['responders'] as List?)?.isNotEmpty == true) ...[
        const SizedBox(height: 12), Text(tr('ai_responders'), style: const TextStyle(fontWeight: FontWeight.w600)), const SizedBox(height: 8),
        Wrap(spacing: 8, runSpacing: 8, children: [for (final r in x['responders']) Chip(visualDensity: VisualDensity.compact, label: Text(tr('r_$r')))]),
      ],
      const SizedBox(height: 12),
      Text(tr('ai_applied'), style: TextStyle(color: RL.text2, fontSize: 12)),
    ])));
  }
}
