import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/incident/ui/media_view.dart';
import 'package:rescuelens/features/incident/ui/post_actions.dart';
import 'package:rescuelens/features/feed/ui/home_header.dart';

/// Facebook style comments. `preview` shows the two latest with "See more comments"; full mode lists all with a composer.
/// One level of threaded replies, likes, edit and delete (own, or any for moderators) with confirmation and Undo.
class CommentsSection extends StatefulWidget {
  final String incidentId; final bool preview; final VoidCallback? onOpenAll; final ValueChanged<int>? onCount;
  const CommentsSection(this.incidentId, {super.key, this.preview = false, this.onOpenAll, this.onCount});
  @override
  State<CommentsSection> createState() => CommentsSectionState();
}

class CommentsSectionState extends State<CommentsSection> {
  List? rows; int total = 0; bool error = false;
  Map? replyTo; int? editing;
  final input = TextEditingController(), edit = TextEditingController();
  final focus = FocusNode();
  bool sending = false;
  @override
  void initState() { super.initState(); load(); }
  @override
  void dispose() { input.dispose(); edit.dispose(); focus.dispose(); super.dispose(); }
  Future<void> load() async {
    try {
      final r = await Api.get('/incidents/${widget.incidentId}/comments?lang=$curLang${widget.preview ? '&limit=2' : ''}');
      if (!mounted) return;
      setState(() { rows = r['data']; total = (r['total'] ?? (r['data'] as List).length) as int; error = false; });
      widget.onCount?.call(total);
    } catch (_) { if (mounted) setState(() => error = true); }
  }
  void snack(String m, {VoidCallback? undo}) => ScaffoldMessenger.of(context)..hideCurrentSnackBar()..showSnackBar(SnackBar(content: Text(m), behavior: SnackBarBehavior.floating, action: undo == null ? null : SnackBarAction(label: tr('undo'), onPressed: undo)));
  Future<void> send() async {
    final b = input.text.trim(); if (b.isEmpty || sending) return;
    setState(() => sending = true);
    try { await Api.post('/incidents/${widget.incidentId}/comments', {'body': b, if (replyTo != null) 'parent_id': replyTo!['id']}); input.clear(); replyTo = null; focus.unfocus(); await load(); }
    catch (e) { snack('$e'); } finally { if (mounted) setState(() => sending = false); }
  }
  Future<void> like(Map c) async {
    if (Api.token == null) { snack(tr('login_to_verify')); return; }
    setState(() { c['liked'] = !(c['liked'] == true); c['likes'] = (c['likes'] ?? 0) + (c['liked'] ? 1 : -1); });
    try { await Api.post('/comments/${c['id']}/like', {}); } catch (_) { load(); }
  }
  Future<void> save(Map c) async {
    try { await Api.patch('/comments/${c['id']}', {'body': edit.text.trim()}); setState(() => editing = null); snack(tr('saved')); load(); } catch (e) { snack('$e'); }
  }
  Future<void> remove(Map c) async {
    if (!await confirmAction(context, title: tr('delete_comment_q'), body: tr('delete_comment_body'), confirm: tr('delete'), icon: LucideIcons.trash2)) return;
    try {
      await Api.delete('/comments/${c['id']}'); await load();
      snack(tr('comment_deleted'), undo: () async { try { await Api.post('/comments/${c['id']}/restore', {}); await load(); } catch (_) {} });
    } catch (e) { snack('$e'); }
  }
  void reply(Map c) {
    if (widget.preview) { widget.onOpenAll?.call(); return; }
    setState(() => replyTo = c['parent_id'] == null ? c : (rows!.firstWhere((x) => x['id'] == c['parent_id'], orElse: () => c)));
    focus.requestFocus();
  }

  Widget tile(Map c) {
    final isReply = c['parent_id'] != null;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final bubble = dark ? const Color(0xFF3A3B3C) : const Color(0xFFF0F2F5);
    final mod = ['moderator', 'admin'].contains(c['author_role']);
    return Padding(key: ValueKey('c${c['id']}'), padding: EdgeInsets.only(left: isReply ? 44 : 0, top: 6, bottom: 2), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Avatar(name: '${c['author']}', url: c['author_avatar'], r: isReply ? 13 : 16),
      const SizedBox(width: 8),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (editing == c['id']) ...[
          TextField(controller: edit, autofocus: true, maxLines: null, maxLength: 1000, decoration: InputDecoration(isDense: true, filled: true, fillColor: bubble, border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none))),
          Row(children: [TextButton(onPressed: () => setState(() => editing = null), child: Text(tr('cancel'))), FilledButton(onPressed: () => save(c), child: Text(tr('save')))]),
        ] else Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Flexible(child: Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8), decoration: BoxDecoration(color: bubble, borderRadius: BorderRadius.circular(18)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(mainAxisSize: MainAxisSize.min, children: [Flexible(child: Text('${c['author']}', overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13))),
                if (mod) ...[const SizedBox(width: 4), const Icon(LucideIcons.badgeCheck, size: 14, color: RL.primary)]]),
              ExpandableText('${c['body_translated'] ?? c['body']}', lines: 4),
            ]))),
          if (c['can_manage'] == true) SizedBox(width: 32, child: PopupMenuButton<String>(padding: EdgeInsets.zero, icon: const Icon(LucideIcons.ellipsis, size: 18, color: RL.text2),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            onSelected: (v) { if (v == 'e') { edit.text = '${c['body']}'; setState(() => editing = c['id']); } else { remove(c); } },
            itemBuilder: (_) => [
              if (c['is_mine'] == true) PopupMenuItem(value: 'e', child: ListTile(dense: true, contentPadding: EdgeInsets.zero, leading: const Icon(LucideIcons.pencil), title: Text(tr('edit')))),
              PopupMenuItem(value: 'd', child: ListTile(dense: true, contentPadding: EdgeInsets.zero, leading: const Icon(LucideIcons.trash2, color: Color(0xFFE41E3F)), title: Text(tr('delete'), style: const TextStyle(color: Color(0xFFE41E3F))))),
            ])),
        ]),
        Padding(padding: const EdgeInsets.only(left: 10, top: 2), child: Wrap(spacing: 14, crossAxisAlignment: WrapCrossAlignment.center, children: [
          Text('${ago(c['created_at'])}${c['edited_at'] != null ? ' · ${tr('edited')}' : ''}', style: const TextStyle(color: RL.text2, fontSize: 12)),
          InkWell(onTap: () => like(c), child: Text('${tr('like')}${(c['likes'] ?? 0) > 0 ? ' · ${c['likes']}' : ''}', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: c['liked'] == true ? RL.primary : RL.text2))),
          if (Api.token != null) InkWell(onTap: () => reply(c), child: Text(tr('reply'), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: RL.text2))),
        ])),
      ])),
    ]));
  }

  @override
  Widget build(BuildContext context) {
    if (rows == null) return error ? TextButton(onPressed: load, child: Text(tr('retry'))) : const Padding(padding: EdgeInsets.all(8), child: Shim(child: Sk(h: 36)));
    final tops = rows!.where((c) => c['parent_id'] == null).toList();
    final list = <Widget>[
      if (widget.preview && total > rows!.length) Align(alignment: Alignment.centerLeft, child: TextButton(onPressed: widget.onOpenAll, child: Text(tr('more_comments'), style: const TextStyle(color: RL.text2, fontWeight: FontWeight.w700)))),
      for (final c in tops) ...[tile(c), for (final r in rows!.where((x) => x['parent_id'] == c['id'])) tile(r)],
      if (!widget.preview && rows!.isEmpty) Padding(padding: const EdgeInsets.all(16), child: Text(tr('no_comments'), textAlign: TextAlign.center, style: const TextStyle(color: RL.text2))),
    ];
    return AnimatedSize(duration: const Duration(milliseconds: 200), alignment: Alignment.topCenter, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      ...list,
      if (!widget.preview && Api.token != null) Padding(padding: const EdgeInsets.only(top: 10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (replyTo != null) Row(children: [const SizedBox(width: 8), Expanded(child: Text(tr('replying_to', {'name': '${replyTo!['author']}'}), style: const TextStyle(color: RL.text2, fontSize: 12))), TextButton(onPressed: () => setState(() => replyTo = null), child: Text(tr('cancel')))]),
        Row(children: [
          Avatar(name: '${Me.user?['name'] ?? ''}', url: Me.user?['avatar_url'], r: 16), const SizedBox(width: 8),
          Expanded(child: TextField(key: const Key('comment-input'), controller: input, focusNode: focus, minLines: 1, maxLines: 4, maxLength: 1000, textInputAction: TextInputAction.send, onSubmitted: (_) => send(),
            decoration: InputDecoration(counterText: '', isDense: true, hintText: replyTo != null ? tr('write_reply') : tr('write_comment'), filled: true, border: OutlineInputBorder(borderRadius: BorderRadius.circular(22), borderSide: BorderSide.none)))),
          IconButton(onPressed: sending ? null : send, icon: const Icon(LucideIcons.send, color: RL.primary)),
        ]),
      ])),
    ]));
  }
}

/// Small avatar that shows a photo when available, else initials.
class Avatar extends StatelessWidget {
  final String name; final String? url; final double r;
  const Avatar({super.key, required this.name, this.url, this.r = 18});
  @override
  Widget build(BuildContext context) {
    final ini = name.trim().split(RegExp(r'\s+')).where((s) => s.isNotEmpty).take(2).map((s) => s[0].toUpperCase()).join();
    return CircleAvatar(radius: r, backgroundColor: RL.primary, child: url == null ? Text(ini.isEmpty ? '?' : ini, style: TextStyle(color: Colors.white, fontSize: r * .7, fontWeight: FontWeight.w700))
      : ClipOval(child: SizedBox.square(dimension: r * 2, child: NetImage(url!, fit: BoxFit.cover))));
  }
}
