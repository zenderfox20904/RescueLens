import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// App strings. English is the default; the chosen language is persisted. RTL is derived from the locale.
class L10n extends ChangeNotifier {
  static final L10n i = L10n._();
  L10n._();
  String lang = 'en';
  static const langs = {'en': 'English', 'bn': 'বাংলা', 'hi': 'हिन्दी', 'es': 'Español', 'ar': 'العربية'};
  static const rtl = {'ar', 'ur', 'fa', 'he'};
  bool get isRtl => rtl.contains(lang);

  Future<void> load() async => lang = (await SharedPreferences.getInstance()).getString('rl_lang') ?? 'en';
  Future<void> set(String l) async {
    lang = l;
    await (await SharedPreferences.getInstance()).setString('rl_lang', l);
    notifyListeners();
  }

  String t(String k, [Map<String, Object> p = const {}]) {
    var s = _s[lang]?[k] ?? _s['en']![k] ?? k;
    p.forEach((a, b) => s = s.replaceAll('{$a}', '$b'));
    return s;
  }

  static const _s = <String, Map<String, String>>{
    'en': {
      'login_to_verify': 'Log in to confirm you see it too.',
      'view': 'View',
      'map_unavailable': 'The map is not available on this device right now. The list below shows the same reports.',
      'home': 'Home', 'map': 'Map', 'report': 'Report', 'alerts': 'Alerts', 'profile': 'Profile',
      'min_ago': '{n} min ago', 'h_ago': '{n} h ago', 'd_ago': '{n} d ago', 'just_now': 'Just now',
      'load_error': 'We could not reach RescueLens. Check your connection and try again.', 'retry': 'Try again',
      'sos': 'If a life is at risk, call your local emergency number first.',
      'anonymous': 'Anonymous', 'verified': 'Verified', 'seen_by': '{n} saw this', 'comments': '{n} comments',
      'i_see_it': 'I see it too', 'community_note': 'Community supported does not mean officially verified. Only approximate locations are shown publicly.',
      'map_note': 'Markers show approximate locations. An empty area does not mean a road is safe.',
      'email': 'Email', 'password': 'Password', 'login': 'Log in', 'login_failed': 'Email or password is incorrect.',
      'camera': 'Camera', 'gallery': 'Gallery', 'ai_suggestion': 'Meta AI suggestion', 'demo': 'DEMO',
      'title': 'Title', 'description': 'Description', 'confirm_publish': 'I checked the details and agree to publish',
      'post': 'Post', 'action_failed': 'Something went wrong. Please try again.', 'live_updates': 'Live updates',
      'latest_near_you': 'Latest near you', 'language': 'Language', 'logout': 'Log out', 'sign_in_prompt': 'Sign in to report and get alerts',
      'no_alerts': 'No alerts yet', 'low': 'Low', 'moderate': 'Moderate', 'high': 'High', 'critical': 'Critical', 'nothing_yet': 'Nothing reported yet',
    },
    'bn': {
      'login_to_verify': 'আপনিও দেখছেন নিশ্চিত করতে লগ ইন করুন।',
      'view': 'দেখুন',
      'map_unavailable': 'এই মুহূর্তে এই ডিভাইসে ম্যাপ দেখানো যাচ্ছে না। নিচের তালিকায় একই রিপোর্ট আছে।',
      'home': 'হোম', 'map': 'ম্যাপ', 'report': 'রিপোর্ট', 'alerts': 'সতর্কতা', 'profile': 'প্রোফাইল',
      'min_ago': '{n} মিনিট আগে', 'h_ago': '{n} ঘণ্টা আগে', 'd_ago': '{n} দিন আগে', 'just_now': 'এইমাত্র',
      'load_error': 'RescueLens এ সংযোগ করা যায়নি। ইন্টারনেট দেখে আবার চেষ্টা করুন।', 'retry': 'আবার চেষ্টা করুন',
      'sos': 'জীবন ঝুঁকিতে থাকলে আগে স্থানীয় জরুরি নম্বরে কল করুন।',
      'anonymous': 'বেনামী', 'verified': 'যাচাইকৃত', 'seen_by': '{n} জন দেখেছেন', 'comments': '{n}টি মন্তব্য',
      'i_see_it': 'আমিও দেখছি', 'community_note': 'কমিউনিটি সমর্থিত মানে সরকারি যাচাই নয়। প্রকাশ্যে শুধু আনুমানিক অবস্থান দেখানো হয়।',
      'map_note': 'মার্কারগুলো আনুমানিক অবস্থান দেখায়। খালি এলাকা মানে রাস্তা নিরাপদ নয়।',
      'email': 'ইমেইল', 'password': 'পাসওয়ার্ড', 'login': 'লগ ইন', 'login_failed': 'ইমেইল বা পাসওয়ার্ড ভুল।',
      'camera': 'ক্যামেরা', 'gallery': 'গ্যালারি', 'ai_suggestion': 'Meta AI প্রস্তাবনা', 'demo': 'DEMO',
      'title': 'শিরোনাম', 'description': 'বিবরণ', 'confirm_publish': 'আমি তথ্য যাচাই করেছি এবং প্রকাশে সম্মতি দিচ্ছি',
      'post': 'পোস্ট করুন', 'action_failed': 'কিছু একটা ভুল হয়েছে। আবার চেষ্টা করুন।', 'live_updates': 'সরাসরি আপডেট',
      'latest_near_you': 'আপনার কাছাকাছি সর্বশেষ', 'language': 'ভাষা', 'logout': 'লগ আউট', 'sign_in_prompt': 'রিপোর্ট ও সতর্কতার জন্য লগ ইন করুন',
      'no_alerts': 'এখনো কোনো সতর্কতা নেই', 'low': 'কম', 'moderate': 'মাঝারি', 'high': 'উচ্চ', 'critical': 'সংকটপূর্ণ', 'nothing_yet': 'এখনো কিছু রিপোর্ট হয়নি',
    },
    'hi': {
      'login_to_verify': 'पुष्टि करने के लिए लॉग इन करें।',
      'view': 'देखें',
      'map_unavailable': 'इस डिवाइस पर अभी मानचित्र उपलब्ध नहीं है। नीचे की सूची में वही रिपोर्ट हैं।',
      'home': 'होम', 'map': 'मानचित्र', 'report': 'रिपोर्ट', 'alerts': 'अलर्ट', 'profile': 'प्रोफ़ाइल',
      'min_ago': '{n} मिनट पहले', 'h_ago': '{n} घंटे पहले', 'd_ago': '{n} दिन पहले', 'just_now': 'अभी',
      'load_error': 'RescueLens से कनेक्ट नहीं हो सका। कनेक्शन जांचें और फिर से कोशिश करें।', 'retry': 'फिर से कोशिश करें',
      'sos': 'जान खतरे में हो तो पहले अपने स्थानीय आपातकालीन नंबर पर कॉल करें।',
      'anonymous': 'गुमनाम', 'verified': 'सत्यापित', 'seen_by': '{n} ने देखा', 'comments': '{n} टिप्पणियां',
      'i_see_it': 'मैं भी देख रहा हूं', 'community_note': 'समुदाय समर्थित का मतलब आधिकारिक सत्यापन नहीं है। सार्वजनिक रूप से केवल अनुमानित स्थान दिखाए जाते हैं।',
      'map_note': 'मार्कर अनुमानित स्थान दिखाते हैं। खाली क्षेत्र का मतलब यह नहीं कि सड़क सुरक्षित है।',
      'email': 'ईमेल', 'password': 'पासवर्ड', 'login': 'लॉग इन', 'login_failed': 'ईमेल या पासवर्ड गलत है।',
      'camera': 'कैमरा', 'gallery': 'गैलरी', 'ai_suggestion': 'Meta AI सुझाव', 'title': 'शीर्षक', 'description': 'विवरण',
      'confirm_publish': 'मैंने विवरण जांच लिया है और प्रकाशित करने के लिए सहमत हूं', 'post': 'पोस्ट करें', 'action_failed': 'कुछ गलत हो गया। कृपया फिर से कोशिश करें।',
      'live_updates': 'लाइव अपडेट', 'latest_near_you': 'आपके पास नवीनतम', 'language': 'भाषा', 'logout': 'लॉग आउट', 'sign_in_prompt': 'रिपोर्ट और अलर्ट के लिए साइन इन करें',
      'no_alerts': 'अभी कोई अलर्ट नहीं', 'low': 'कम', 'moderate': 'मध्यम', 'high': 'उच्च', 'critical': 'गंभीर', 'nothing_yet': 'अभी कुछ रिपोर्ट नहीं हुआ',
    },
    'es': {
      'login_to_verify': 'Inicia sesión para confirmar que también lo ves.',
      'view': 'Ver',
      'map_unavailable': 'El mapa no está disponible en este dispositivo ahora. La lista de abajo muestra los mismos reportes.',
      'home': 'Inicio', 'map': 'Mapa', 'report': 'Reportar', 'alerts': 'Alertas', 'profile': 'Perfil',
      'min_ago': 'hace {n} min', 'h_ago': 'hace {n} h', 'd_ago': 'hace {n} d', 'just_now': 'Ahora',
      'load_error': 'No pudimos conectar con RescueLens. Revisa tu conexión e inténtalo de nuevo.', 'retry': 'Reintentar',
      'sos': 'Si hay una vida en riesgo, llama primero a tu número de emergencias local.',
      'anonymous': 'Anónimo', 'verified': 'Verificado', 'seen_by': '{n} lo vieron', 'comments': '{n} comentarios',
      'i_see_it': 'Yo también lo veo', 'community_note': 'Apoyado por la comunidad no significa verificado oficialmente. Solo se muestran ubicaciones aproximadas.',
      'map_note': 'Los marcadores muestran ubicaciones aproximadas. Un área vacía no significa que una vía sea segura.',
      'email': 'Correo', 'password': 'Contraseña', 'login': 'Iniciar sesión', 'login_failed': 'Correo o contraseña incorrectos.',
      'camera': 'Cámara', 'gallery': 'Galería', 'ai_suggestion': 'Sugerencia de Meta AI', 'title': 'Título', 'description': 'Descripción',
      'confirm_publish': 'Revisé los datos y acepto publicar', 'post': 'Publicar', 'action_failed': 'Algo salió mal. Inténtalo de nuevo.',
      'live_updates': 'En vivo', 'latest_near_you': 'Lo último cerca de ti', 'language': 'Idioma', 'logout': 'Cerrar sesión', 'sign_in_prompt': 'Inicia sesión para reportar y recibir alertas',
      'no_alerts': 'Aún no hay alertas', 'low': 'Baja', 'moderate': 'Moderada', 'high': 'Alta', 'critical': 'Crítica', 'nothing_yet': 'Aún no hay reportes',
    },
    'ar': {
      'login_to_verify': 'سجّل الدخول لتأكيد أنك تراه أيضًا.',
      'view': 'عرض',
      'map_unavailable': 'الخريطة غير متاحة على هذا الجهاز الآن. تعرض القائمة أدناه البلاغات نفسها.',
      'home': 'الرئيسية', 'map': 'الخريطة', 'report': 'إبلاغ', 'alerts': 'التنبيهات', 'profile': 'الملف',
      'min_ago': 'منذ {n} د', 'h_ago': 'منذ {n} س', 'd_ago': 'منذ {n} يوم', 'just_now': 'الآن',
      'load_error': 'تعذر الاتصال بـ RescueLens. تحقق من الاتصال وحاول مرة أخرى.', 'retry': 'إعادة المحاولة',
      'sos': 'إذا كانت هناك حياة في خطر، اتصل أولا برقم الطوارئ المحلي.',
      'anonymous': 'مجهول', 'verified': 'موثق', 'seen_by': 'شاهده {n}', 'comments': '{n} تعليقات',
      'i_see_it': 'أراه أيضا', 'community_note': 'دعم المجتمع لا يعني التحقق الرسمي. تعرض المواقع التقريبية فقط للعامة.',
      'map_note': 'العلامات تعرض مواقع تقريبية. المنطقة الفارغة لا تعني أن الطريق آمن.',
      'email': 'البريد الإلكتروني', 'password': 'كلمة المرور', 'login': 'تسجيل الدخول', 'login_failed': 'البريد أو كلمة المرور غير صحيحة.',
      'camera': 'الكاميرا', 'gallery': 'المعرض', 'ai_suggestion': 'اقتراح Meta AI', 'title': 'العنوان', 'description': 'الوصف',
      'confirm_publish': 'راجعت التفاصيل وأوافق على النشر', 'post': 'نشر', 'action_failed': 'حدث خطأ. حاول مرة أخرى.',
      'live_updates': 'تحديثات مباشرة', 'latest_near_you': 'الأحدث بالقرب منك', 'language': 'اللغة', 'logout': 'تسجيل الخروج', 'sign_in_prompt': 'سجل الدخول للإبلاغ وتلقي التنبيهات',
      'no_alerts': 'لا توجد تنبيهات بعد', 'low': 'منخفضة', 'moderate': 'متوسطة', 'high': 'عالية', 'critical': 'حرجة', 'nothing_yet': 'لا توجد بلاغات بعد',
    },
  };
}

String tr(String k, [Map<String, Object> p = const {}]) => L10n.i.t(k, p);

String ago(String iso) {
  final d = DateTime.now().difference(DateTime.parse(iso));
  if (d.inMinutes < 1) return tr('just_now');
  if (d.inMinutes < 60) return tr('min_ago', {'n': d.inMinutes});
  if (d.inHours < 24) return tr('h_ago', {'n': d.inHours});
  return tr('d_ago', {'n': d.inDays});
}
