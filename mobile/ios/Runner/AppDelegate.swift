import Flutter
import UIKit
import GoogleMaps

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    let key = (Bundle.main.object(forInfoDictionaryKey: "GMSApiKey") as? String) ?? ""
    let hasKey = !key.isEmpty && !key.hasPrefix("$(")
    if hasKey { GMSServices.provideAPIKey(key) }
    // Lets Dart show a friendly fallback instead of opening a map that would abort without a key.
    if let registrar = engineBridge.pluginRegistry.registrar(forPlugin: "RLConfig") {
      FlutterMethodChannel(name: "rescuelens/config", binaryMessenger: registrar.messenger()).setMethodCallHandler { call, result in
        result(call.method == "hasMapsKey" ? hasKey : FlutterMethodNotImplemented)
      }
    }
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
  }
}
