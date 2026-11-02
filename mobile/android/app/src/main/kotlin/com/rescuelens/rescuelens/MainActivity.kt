package com.rescuelens.rescuelens

import android.content.pm.PackageManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        // Reports whether a Google Maps key was set at build time; without one the app uses OpenStreetMap.
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "rescuelens/config").setMethodCallHandler { call, result ->
            if (call.method == "hasMapsKey") {
                val key = try {
                    packageManager.getApplicationInfo(packageName, PackageManager.GET_META_DATA).metaData?.getString("com.google.android.geo.API_KEY")
                } catch (e: Exception) { null }
                result.success(!key.isNullOrBlank())
            } else result.notImplemented()
        }
    }
}
