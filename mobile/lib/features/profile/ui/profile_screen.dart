import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';
import 'package:rescuelens/core/i18n.dart';
import 'package:rescuelens/core/theme.dart';
import 'package:rescuelens/core/widgets.dart';
import 'package:rescuelens/features/alerts/ui/alerts_screen.dart' show showPrefs;
import 'package:rescuelens/features/feed/ui/home_header.dart';
import 'package:rescuelens/features/incident/ui/incident_widgets.dart';
import 'package:rescuelens/features/incident/ui/media_view.dart';
import 'package:rescuelens/features/profile/ui/profile_forms.dart';
import 'package:rescuelens/features/profile/ui/timeline.dart';

const kDanger = Color(0xFFE41E3F);

void toast(BuildContext c, String m) => ScaffoldMessenger.of(c)..hideCurrentSnackBar()..showSnackBar(SnackBar(duration: const Duration(seconds: 3), content: Text(m), behavior: SnackBarBehavior.floating, margin: const EdgeInsets.fromLTRB(12, 0, 12, 100)));

/// Profile: header with photo, name, account type and alert area, stats, my reports, and grouped settings.
class ProfileScreen extends StatefulWidget {
  final VoidCallback onChanged;
  const ProfileScreen({super.key, required this.onChanged});
  @override
  State<ProfileScreen> createState() => _ProfileState();
}

class _ProfileState extends State<ProfileScreen> {
  Map? me; Map stats = {}; bool uploading = false, loading = true;
  @override
  void initState() { super.initState(); load().then((_) { if (debugOpen == 'settings' && mounted) openSettings(); }); }
  Future<void> load() async {
    if (Api.token == null) { setState(() => loading = false); return; }
    try {
      final r = await Api.get('/auth/me');
      Me.user = r['user'];
      if (mounted) setState(() { me = r['user']; stats = r['stats'] ?? {}; loading = false; });
    } catch (_) { if (mounted) setState(() => loading = false); }
  }

  Future<void> pickPhoto() async {
    final src = await showModalBottomSheet<ImageSource>(context: context, showDragHandle: true, builder: (c) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
      ListTile(leading: const Icon(LucideIcons.camera), title: Text(tr('camera')), onTap: () => Navigator.pop(c, ImageSource.camera)),
      ListTile(leading: const Icon(LucideIcons.images), title: Text(tr('gallery')), onTap: () => Navigator.pop(c, ImageSource.gallery)),
    ])));
    if (src == null) return;
    XFile? f;
    try { f = await ImagePicker().pickImage(source: src, maxWidth: 1024, maxHeight: 1024, imageQuality: 85, preferredCameraDevice: CameraDevice.front); } catch (_) { if (mounted) toast(context, tr('camera_unavailable')); return; }
    if (f == null) return;
    setState(() => uploading = true);
    try {
      final r = await Api.upload('/auth/me/avatar', f.path);
      Me.user = r['user'];
      if (mounted) { setState(() => me = r['user']); toast(context, tr('photo_updated')); }
    } catch (e) { if (mounted) toast(context, '$e'); }
    if (mounted) setState(() => uploading = false);
  }

  Future<void> open(Widget page) async {
    final changed = await Navigator.push(context, MaterialPageRoute(builder: (_) => page));
    if (changed == true) load();
  }

  /// Settings live behind the gear on the profile header (Facebook style), and inline for guests.
  List<Widget> settingsGroups(BuildContext context) {
    final authed = Api.token != null;
    return [
      if (authed) _Group(index: 1, title: tr('s_account'), tiles: [
        _Tile(LucideIcons.user, tr('edit_profile'), onTap: () => open(EditProfileScreen(me: me ?? {}))),
        _Tile(LucideIcons.lock, tr('change_password'), onTap: () => open(const ChangePasswordScreen())),
        _Tile(LucideIcons.link, tr('linked_accounts'), sub: 'Google · Facebook', onTap: () => showLinked(context)),
      ]),
      _Group(index: 2, title: tr('s_prefs'), tiles: [
        _Tile(LucideIcons.languages, tr('language'), sub: L10n.langs[L10n.i.lang], onTap: () => pickLanguage(context).then((_) => widget.onChanged())),
        _Tile(LucideIcons.moon, tr('theme'), sub: tr('theme_${L10n.i.theme}'), onTap: () => pickTheme(context)),
        if (authed) _Tile(LucideIcons.radar, tr('alert_radius'), sub: '${me?['alert_radius_km'] ?? 5} ${me?['distance_unit'] ?? 'km'}', onTap: () => editRadius(context, me ?? {}).then((_) => load())),
      ]),
      if (authed) _Group(index: 3, title: tr('s_notifications'), tiles: [
        _Tile(LucideIcons.bellRing, tr('n_prefs'), onTap: () => showPrefs(context)),
      ]),
      if (authed) _Group(index: 4, title: tr('s_privacy'), tiles: [
        _Tile(LucideIcons.eyeOff, tr('privacy_location'), sub: tr('privacy_location_sub')),
        _Tile(LucideIcons.trash2, tr('delete_account'), color: kDanger, onTap: () => deleteAccount(context, widget.onChanged)),
      ]),
      if (authed) _Group(index: 5, title: tr('s_trash'), tiles: [
        _Tile(LucideIcons.archiveRestore, tr('my_trash'), sub: tr('trash_sub'), onTap: () => open(const TrashScreen())),
      ]),
      _Group(index: 6, title: tr('s_help'), tiles: [
        _Tile(LucideIcons.heartPulse, tr('help_emergency'), sub: tr('sos')),
        _Tile(LucideIcons.info, tr('about'), onTap: () => showAboutDialog(context: context, applicationName: 'RescueLens', applicationVersion: '1.0.0',
          applicationIcon: const LogoMark(size: 40), applicationLegalese: '© 2026 RescueLens', children: [Padding(padding: const EdgeInsets.only(top: 12), child: Text(tr('about_body')))])),
      ]),
      if (authed) _Group(index: 7, tiles: [
        _Tile(LucideIcons.logOut, tr('logout'), color: kDanger, onTap: () async { if (!await confirmAction(context, title: tr('logout_q'), body: tr('logout_body'), confirm: tr('logout'), icon: LucideIcons.logOut, danger: true)) return; await Api.setToken(null); Me.user = null; if (context.mounted && Navigator.canPop(context)) Navigator.pop(context); widget.onChanged(); }),
      ]),
    ];
  }

  void openSettings() => Navigator.push(context, MaterialPageRoute(builder: (c) => StatefulBuilder(builder: (c, setS) => Scaffold(
    appBar: AppBar(title: Text(tr('settings'))),
    body: ListView(padding: const EdgeInsets.fromLTRB(12, 0, 12, 40), children: [...settingsGroups(c),
      const Padding(padding: EdgeInsets.all(16), child: Text('© 2026 RescueLens', textAlign: TextAlign.center, style: TextStyle(fontSize: 12)))]))))).then((_) => load());

  @override
  Widget build(BuildContext context) {
    final authed = Api.token != null;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final card = dark ? RL.darkCard : RL.card;
    final type = '${me?['account_type'] ?? 'citizen'}';
    final org = me?['organization'] as Map?;
    return RefreshIndicator(onRefresh: load, child: ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 120), children: [
      if (authed && loading) const PostSkeleton(),
      if (authed && me != null) Entrance(child: Container(decoration: BoxDecoration(color: card, borderRadius: BorderRadius.circular(20)), clipBehavior: Clip.antiAlias, child: Column(children: [
        Stack(clipBehavior: Clip.none, alignment: Alignment.bottomCenter, children: [
          Container(height: 96, color: RL.primary.withValues(alpha: dark ? .22 : .12)),
          Positioned(top: 6, right: 6, child: IconButton.filledTonal(tooltip: tr('settings'), onPressed: openSettings, icon: const Icon(LucideIcons.settings))),
          Positioned(bottom: -46, child: GestureDetector(onTap: uploading ? null : pickPhoto, child: Stack(children: [
            Container(padding: const EdgeInsets.all(4), decoration: BoxDecoration(color: card, shape: BoxShape.circle),
              child: Hero(tag: 'avatar', child: CircleAvatar(radius: 46, backgroundColor: RL.primary.withValues(alpha: .2),
                child: ClipOval(child: SizedBox.expand(child: me!['avatar_url'] != null ? NetImage('${me!['avatar_url']}', fit: BoxFit.cover)
                  : Center(child: Text('${me!['name']}'.trim().isEmpty ? '?' : '${me!['name']}'.trim()[0].toUpperCase(), style: const TextStyle(fontSize: 36, fontWeight: FontWeight.w800, color: RL.primary)))))))),
            Positioned(right: 2, bottom: 2, child: Container(padding: const EdgeInsets.all(6), decoration: BoxDecoration(color: RL.primary, shape: BoxShape.circle, border: Border.all(color: card, width: 2)),
              child: uploading ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(LucideIcons.camera, size: 14, color: Colors.white))),
          ]))),
        ]),
        const SizedBox(height: 54),
        Text('${org?['name'] ?? me!['name']}', textAlign: TextAlign.center, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        Wrap(alignment: WrapAlignment.center, spacing: 6, runSpacing: 6, children: [
          Chip(avatar: Icon(type == 'citizen' ? LucideIcons.user : LucideIcons.building2, size: 16, color: RL.primary), label: Text(tr('acct_$type')), visualDensity: VisualDensity.compact),
          if (me!['role'] == 'admin' || me!['role'] == 'moderator') Chip(avatar: const Icon(LucideIcons.shield, size: 16, color: RL.primary), label: Text(tr('role_${me!['role']}')), visualDensity: VisualDensity.compact),
          Chip(avatar: const Icon(LucideIcons.locateFixed, size: 16, color: RL.primary), label: Text('${me!['alert_area_set'] == true ? tr('your_alert_area') : tr('set_alert_area')} · ${me!['alert_radius_km'] ?? 5} ${me!['distance_unit'] ?? 'km'}'), visualDensity: VisualDensity.compact),
        ]),
        if ((me!['bio'] ?? '').toString().isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(20, 8, 20, 0), child: Text('${me!['bio']}', textAlign: TextAlign.center, style: TextStyle(color: RL.text2))),
        Padding(padding: const EdgeInsets.fromLTRB(12, 14, 12, 4), child: Row(children: [
          for (final k in ['reports', 'verified', 'confirmations']) Expanded(child: Column(children: [
            TweenAnimationBuilder<double>(tween: Tween(begin: 0, end: ((stats[k] ?? 0) as num).toDouble()), duration: const Duration(milliseconds: 700), curve: Curves.easeOutCubic,
              builder: (_, v, _) => Text('${v.round()}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
            Text(tr('stat_$k'), style: TextStyle(color: RL.text2, fontSize: 12)),
          ])),
        ])),
        Padding(padding: const EdgeInsets.fromLTRB(16, 10, 16, 16), child: Row(children: [
          Expanded(child: FilledButton.icon(onPressed: () => open(EditProfileScreen(me: me!)), icon: const Icon(LucideIcons.pencil, size: 18), label: Text(tr('edit_profile')))),
          const SizedBox(width: 8),
          Expanded(child: OutlinedButton.icon(onPressed: () => open(const MyReportsScreen()), icon: const Icon(LucideIcons.newspaper, size: 18), label: Text(tr('my_reports')))),
        ])),
      ]))),
      if (authed && me?['id'] != null) ProfileTimeline(key: ValueKey('tl${me!['id']}'), userId: '${me!['id']}', isMe: true),
      if (!authed) Entrance(child: Card(child: Padding(padding: const EdgeInsets.all(20), child: Column(children: [
        const Icon(LucideIcons.circleUser, size: 56, color: RL.primary), const SizedBox(height: 8),
        Text(tr('profile_signin'), textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w700)),
        const SizedBox(height: 12),
        FilledButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AuthPage())).then((_) { widget.onChanged(); load(); }), child: Text(tr('login'))),
      ])))),
      if (!authed) ...settingsGroups(context),
      Padding(padding: EdgeInsets.all(16), child: Text('© 2026 RescueLens', textAlign: TextAlign.center, style: TextStyle(color: RL.text2, fontSize: 12))),
    ]));
  }
}

class _Tile {
  final IconData icon; final String title; final String? sub; final VoidCallback? onTap; final Color? color;
  const _Tile(this.icon, this.title, {this.sub, this.onTap, this.color});
}

class _Group extends StatelessWidget {
  final String? title; final List<_Tile> tiles; final int index;
  const _Group({this.title, required this.tiles, this.index = 0});
  @override
  Widget build(BuildContext context) => Entrance(index: index, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    if (title != null) Padding(padding: const EdgeInsets.fromLTRB(8, 18, 8, 6), child: Text(title!.toUpperCase(), style: TextStyle(color: RL.text2, fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: .6))),
    if (title == null) const SizedBox(height: 14),
    Card(margin: EdgeInsets.zero, clipBehavior: Clip.antiAlias, child: Column(children: [
      for (final (i, t) in tiles.indexed) ...[
        if (i > 0) const Divider(height: 1, indent: 56),
        ListTile(
          leading: Container(width: 34, height: 34, decoration: BoxDecoration(color: (t.color ?? RL.primary).withValues(alpha: .12), borderRadius: BorderRadius.circular(10)), child: Icon(t.icon, size: 20, color: t.color ?? RL.primary)),
          title: Text(t.title, style: TextStyle(fontWeight: FontWeight.w600, color: t.color)),
          subtitle: t.sub == null ? null : Text(t.sub!, maxLines: 2, overflow: TextOverflow.ellipsis),
          trailing: t.onTap == null ? null : Icon(LucideIcons.chevronRight, color: RL.text2),
          onTap: t.onTap),
      ],
    ])),
  ]));
}

/// The reporter's own posts, newest first.
class MyReportsScreen extends StatefulWidget {
  const MyReportsScreen({super.key});
  @override
  State<MyReportsScreen> createState() => _MyReportsState();
}

class _MyReportsState extends State<MyReportsScreen> {
  List? items; bool error = false;
  @override
  void initState() { super.initState(); load(); }
  Future<void> load() async {
    try { final r = await Api.get('/incidents?mine=1&per_page=50&lang=$curLang'); if (mounted) setState(() { items = r['data']; error = false; }); } catch (_) { if (mounted) setState(() => error = true); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(tr('my_reports'))), body: RefreshIndicator(onRefresh: load, child: ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 40), children: [
    if (error) ErrorCard(onRetry: load),
    if (items == null && !error) ...[const PostSkeleton(), const PostSkeleton()],
    if (items != null && items!.isEmpty) Padding(padding: const EdgeInsets.all(32), child: Text(tr('nothing_yet'), textAlign: TextAlign.center, style: TextStyle(color: RL.text2))),
    for (final (n, i) in (items ?? []).indexed) Entrance(index: n, child: PostTile(i)),
  ])));
}
