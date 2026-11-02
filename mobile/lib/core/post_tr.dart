import 'package:flutter/foundation.dart';
import 'package:rescuelens/core/api.dart';
import 'package:rescuelens/core/format.dart';

/// Whole post translation through Meta AI: title, body, AI analysis and comments together, toggled in one tap.
/// State per incident id: {'status': 'loading'|'on'|'error', 'data': {...}}. Shared so the card and its comments switch together.
class PostTr {
  static final notifier = ValueNotifier<Map<String, Map>>({});
  static Map? of(String id) {
    final s = notifier.value[id];
    if (s == null) return null;
    if (s['status'] == 'on' && s['data']?['lang'] != curLang) return null;
    return s;
  }
  static Map? data(String id) { final s = of(id); return s?['status'] == 'on' ? s!['data'] as Map? : null; }
  static void _set(String id, Map? v) { final m = Map<String, Map>.of(notifier.value); if (v == null) { m.remove(id); } else { m[id] = v; } notifier.value = m; }
  static Future<void> toggle(String id) async {
    final s = of(id);
    if (s?['status'] == 'loading') return;
    if (s?['status'] == 'on') { _set(id, null); return; }
    _set(id, {'status': 'loading'});
    try {
      final r = await Api.get('/incidents/$id/translation?lang=$curLang');
      _set(id, {'status': 'on', 'data': r['data']});
    } catch (_) { _set(id, {'status': 'error'}); }
  }
}
