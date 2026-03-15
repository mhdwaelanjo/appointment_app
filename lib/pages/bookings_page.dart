import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../controllers/appointment_controller.dart';
import '../models/appointment.dart';
import 'add_edit_appointment_page.dart';

class BookingsPage extends GetView<AppointmentController> {
  const BookingsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('EEE, dd MMM yyyy • hh:mm a');

    return Obx(() {
      final items = List<Appointment>.from(controller.appointments)..sort((a, b) => a.startAt.compareTo(b.startAt));

      if (items.isEmpty) {
        return const Center(child: Text('No appointments yet.'));
      }

      return ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: items.length,
        separatorBuilder: (_, index) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final appointment = items[index];
          final isCancelled = appointment.status == AppointmentStatus.cancelled;

          return Card(
            child: ListTile(
              title: Text(
                appointment.title,
                style: TextStyle(
                  decoration: isCancelled ? TextDecoration.lineThrough : TextDecoration.none,
                ),
              ),
              subtitle: Text(
                '${dateFormat.format(appointment.startAt)}\n'
                'Client: ${appointment.clientName}',
              ),
              isThreeLine: true,
              trailing: PopupMenuButton<String>(
                onSelected: (value) async {
                  if (value == 'edit') {
                    await Get.to(
                      () => AddEditAppointmentPage(editing: appointment),
                    );
                  } else if (value == 'cancel') {
                    controller.cancelAppointment(appointment.id);
                  } else if (value == 'delete') {
                    controller.deleteAppointment(appointment.id);
                  } else if (value == 'calendar') {
                    await controller.exportToDeviceCalendar(appointment);
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Event sent to device calendar.')),
                      );
                    }
                  }
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'edit', child: Text('Edit / Reschedule')),
                  PopupMenuItem(value: 'cancel', child: Text('Cancel')),
                  PopupMenuItem(value: 'calendar', child: Text('Add to Calendar')),
                  PopupMenuItem(value: 'delete', child: Text('Delete')),
                ],
              ),
            ),
          );
        },
      );
    });
  }
}
