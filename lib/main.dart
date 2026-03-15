import 'package:flutter/material.dart';
import 'package:get/get.dart';

import 'controllers/appointment_controller.dart';
import 'pages/home_shell.dart';
import 'services/notification_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await NotificationService.instance.init();
  Get.put(AppointmentController());

  runApp(const SmartAppointmentApp());
}

class SmartAppointmentApp extends StatelessWidget {
  const SmartAppointmentApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Smart Appointment Management System',
      theme: ThemeData(colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal), useMaterial3: true),
      home: const HomeShell(),
    );
  }
}
