import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kDebugMode;
import 'i18n.dart';

String get curLang => L10n.i.lang;
String title(Map i) => '${i['title_translated'] ?? i['title']}'.replaceAll('[DEMO] ', '');

const _mon = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
/// "2 Nov 2026, 5:41 PM" in device local time.
String fmtDate(dynamic iso) {
  final d = DateTime.tryParse('${iso ?? ''}')?.toLocal();
  if (d == null) return '';
  final h = d.hour % 12 == 0 ? 12 : d.hour % 12;
  return '${d.day} ${_mon[d.month - 1]} ${d.year}, $h:${d.minute.toString().padLeft(2, '0')} ${d.hour < 12 ? 'AM' : 'PM'}';
}

/// Debug only screenshot aid: SIMCTL_CHILD_RL_OPEN=settings|trash|incident:ID[:sheet] when launching with simctl. Ignored in release builds.
String? get debugOpen => kDebugMode ? (Platform.environment['RL_OPEN'] ?? (const String.fromEnvironment('RL_OPEN').isEmpty ? null : const String.fromEnvironment('RL_OPEN'))) : null;
