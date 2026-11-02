import 'i18n.dart';

String get curLang => L10n.i.lang;
String title(Map i) => '${i['title_translated'] ?? i['title']}'.replaceAll('[DEMO] ', '');
