import 'package:flutter/material.dart';
import 'package:get/get.dart';

import 'add_edit_appointment_page.dart';
import 'bookings_page.dart';
import 'dashboard_page.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  final _index = 0.obs;

  static const _pages = [
    BookingsPage(),
    DashboardPage(),
    AddEditAppointmentPage(),
  ];

  static const _titles = [
    'Bookings',
    'Admin Dashboard',
    'Add Appointment',
  ];

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => Scaffold(
        appBar: AppBar(
          title: Text(_titles[_index.value]),
        ),
        body: IndexedStack(
          index: _index.value,
          children: _pages,
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index.value,
          onDestinationSelected: (value) => _index.value = value,
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.list_alt_outlined),
              selectedIcon: Icon(Icons.list_alt),
              label: 'Bookings',
            ),
            NavigationDestination(
              icon: Icon(Icons.calendar_month_outlined),
              selectedIcon: Icon(Icons.calendar_month),
              label: 'Dashboard',
            ),
            NavigationDestination(
              icon: Icon(Icons.add_circle_outline),
              selectedIcon: Icon(Icons.add_circle),
              label: 'Add',
            ),
          ],
        ),
      ),
    );
  }
}
