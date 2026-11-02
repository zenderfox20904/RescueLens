import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';

const _danger = Color(0xFFE41E3F);

/// Three dot menu shown only when the viewer owns the post or is a moderator/admin.
class PostMenu extends StatelessWidget {
  final Map i; final ValueChanged<Map>? onUpdated; final VoidCallback? onRemoved, onRestored;
  const PostMenu(this.i, {super.key, this.onUpdated, this.onRemoved, this.onRestored});
  @override
  Widget build(BuildContext context) {
    if (i['can_manage'] != true) return const SizedBox.shrink();
    return PopupMenuButton<String>(
      tooltip: tr('more'), icon: const Icon(LucideIcons.ellipsis, color: RL.text2), position: PopupMenuPosition.under,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      onSelected: (v) => v == 'edit' ? edit(context) : remove(context),
      itemBuilder: (_) => [
        PopupMenuItem(value: 'edit', child: ListTile(contentPadding: EdgeInsets.zero, leading: const Icon(LucideIcons.pencil), title: Text(tr('edit_post')))),
        PopupMenuItem(value: 'delete', child: ListTile(contentPadding: EdgeInsets.zero, leading: const Icon(LucideIcons.trash2, color: _danger), title: Text(tr('move_to_trash'), style: const TextStyle(color: _danger)))),
      ]);
  }

  Future<void> edit(BuildContext context) async {
    final r = await Navigator.push<Map>(context, MaterialPageRoute(builder: (_) => EditPostScreen(i)));
    if (r != null) onUpdated?.call(r);
  }

  Future<void> remove(BuildContext context) async {
    final ok = await confirmAction(context, title: tr('delete_post_q'), body: tr('delete_post_body'), confirm: tr('move_to_trash'), icon: LucideIcons.trash2);
    if (!ok || !context.mounted) return;
    final m = ScaffoldMessenger.of(context);
    try {
      await Api.delete('/incidents/${i['id']}');
      onRemoved?.call();
      m.showSnackBar(SnackBar(behavior: SnackBarBehavior.floating, content: Text(tr('post_trashed')), action: SnackBarAction(label: tr('undo'), onPressed: () async {
        try { await Api.post('/incidents/${i['id']}/restore', {}); onRestored?.call(); m.showSnackBar(SnackBar(behavior: SnackBarBehavior.floating, content: Text(tr('restored')))); } catch (_) {}
      })));
    } catch (_) { m.showSnackBar(SnackBar(behavior: SnackBarBehavior.floating, content: Text(tr('action_failed')))); }
  }
}

/// Prefilled edit form for a report.
class EditPostScreen extends StatefulWidget {
  final Map i;
  const EditPostScreen(this.i, {super.key});
  @override
  State<EditPostScreen> createState() => _EditPostState();
}

class _EditPostState extends State<EditPostScreen> {
  final form = GlobalKey<FormState>();
  late final title = TextEditingController(text: '${widget.i['title'] ?? ''}');
  late final desc = TextEditingController(text: '${widget.i['description'] ?? ''}');
  late String cat = '${widget.i['category']}', sev = '${widget.i['severity']}';
  bool busy = false;
  Future<void> save() async {
    if (!form.currentState!.validate()) return;
    setState(() => busy = true);
    try {
      final r = await Api.patch('/incidents/${widget.i['id']}', {'title': title.text.trim(), 'description': desc.text.trim(), 'category': cat, 'severity': sev});
      if (mounted) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(behavior: SnackBarBehavior.floating, content: Text(tr('saved')))); Navigator.pop(context, r['data']); }
    } catch (e) { if (mounted) { setState(() => busy = false); ScaffoldMessenger.of(context).showSnackBar(SnackBar(behavior: SnackBarBehavior.floating, content: Text(tr('action_failed')))); } }
  }
  @override
  Widget build(BuildContext context) {
    final sevColor = {'low': const Color(0xFF31A24C), 'moderate': const Color(0xFFF7B928), 'high': const Color(0xFFF5793B), 'critical': _danger};
    return Scaffold(appBar: AppBar(title: Text(tr('edit_post')), actions: [
      Padding(padding: const EdgeInsets.only(right: 12), child: FilledButton(onPressed: busy ? null : save, child: Text(tr('save')))),
    ]), body: Form(key: form, autovalidateMode: AutovalidateMode.onUserInteraction, child: ListView(padding: const EdgeInsets.fromLTRB(16, 20, 16, 40), children: [
      TextFormField(controller: title, maxLength: 160, decoration: InputDecoration(labelText: tr('title'), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
        validator: (v) => (v ?? '').trim().length < 3 ? tr('v_title') : null),
      const SizedBox(height: 8),
      TextFormField(controller: desc, maxLength: 2000, minLines: 4, maxLines: 10, decoration: InputDecoration(labelText: tr('description'), alignLabelWithHint: true, border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)))),
      const SizedBox(height: 12),
      Text(tr('category'), style: const TextStyle(fontWeight: FontWeight.w700)), const SizedBox(height: 8),
      Wrap(spacing: 8, runSpacing: 8, children: [for (final c in RL.catIcon.keys) ChoiceChip(showCheckmark: false, avatar: Icon(RL.cat(c), size: 18), label: Text(tr('c_$c')), selected: cat == c, onSelected: (_) => setState(() => cat = c))]),
      const SizedBox(height: 16),
      Text(tr('severity'), style: const TextStyle(fontWeight: FontWeight.w700)), const SizedBox(height: 8),
      Wrap(spacing: 8, runSpacing: 8, children: [for (final s in sevColor.keys) ChoiceChip(showCheckmark: false, avatar: CircleAvatar(radius: 6, backgroundColor: sevColor[s]), label: Text(tr(s)), selected: sev == s, onSelected: (_) => setState(() => sev = s))]),
      const SizedBox(height: 16),
      Text(tr('edit_note'), style: const TextStyle(color: RL.text2, fontSize: 12)),
    ])));
  }
}

/// Text that truncates with a "See more" toggle.
class ExpandableText extends StatefulWidget {
  final String text; final int lines;
  const ExpandableText(this.text, {super.key, this.lines = 3});
  @override
  State<ExpandableText> createState() => _ExpandableTextState();
}

class _ExpandableTextState extends State<ExpandableText> {
  bool open = false;
  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, c) {
    final style = DefaultTextStyle.of(context).style;
    final tp = TextPainter(text: TextSpan(text: widget.text, style: style), maxLines: widget.lines, textDirection: Directionality.of(context))..layout(maxWidth: c.maxWidth);
    final long = tp.didExceedMaxLines;
    return AnimatedSize(duration: const Duration(milliseconds: 220), curve: Curves.easeOutCubic, alignment: Alignment.topCenter, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(widget.text, maxLines: open ? null : widget.lines, overflow: open ? null : TextOverflow.ellipsis),
      if (long) GestureDetector(onTap: () => setState(() => open = !open), child: Padding(padding: const EdgeInsets.only(top: 2), child: Text(tr(open ? 'see_less' : 'see_more'), style: const TextStyle(color: RL.text2, fontWeight: FontWeight.w700)))),
    ]));
  });
}
