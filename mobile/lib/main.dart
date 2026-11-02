import 'package:flutter/material.dart';
import 'src/api.dart';
import 'src/screens.dart';
import 'src/theme.dart';
import 'src/widgets.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Api.load();
  runApp(const RescueLensApp());
}

class RescueLensApp extends StatelessWidget {
  const RescueLensApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
        title: 'RescueLens', debugShowCheckedModeBanner: false,
        theme: RL.theme(Brightness.light), darkTheme: RL.theme(Brightness.dark),
        home: const Home(),
      );
}

class Home extends StatefulWidget {
  const Home({super.key});
  @override
  State<Home> createState() => _HomeState();
}

class _HomeState extends State<Home> {
  int tab = 0;
  @override
  Widget build(BuildContext context) {
    final authed = Api.token != null;
    final pages = [const FeedScreen(), const MapScreen(), authed ? const ReportScreen() : LoginScreen(onDone: () => setState(() {}))];
    return Scaffold(
      appBar: AppBar(title: const Row(children: [LogoMark(size: 32), SizedBox(width: 8), Text('RescueLens')]), actions: [
        if (authed) IconButton(icon: const Icon(Icons.logout), onPressed: () async { await Api.setToken(null); setState(() {}); }),
      ]),
      body: pages[tab],
      bottomNavigationBar: NavigationBar(selectedIndex: tab, onDestinationSelected: (i) => setState(() => tab = i), destinations: const [
        NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'ফিড'),
        NavigationDestination(icon: Icon(Icons.map_outlined), selectedIcon: Icon(Icons.map), label: 'ম্যাপ'),
        NavigationDestination(icon: Icon(Icons.add_a_photo_outlined), label: 'রিপোর্ট'),
      ]),
    );
  }
}
