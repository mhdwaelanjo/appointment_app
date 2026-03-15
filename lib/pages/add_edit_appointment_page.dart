import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../controllers/appointment_controller.dart';
import '../models/appointment.dart';

class AddEditAppointmentPage extends StatefulWidget {
  const AddEditAppointmentPage({super.key, this.editing});

  final Appointment? editing;

  @override
  State<AddEditAppointmentPage> createState() => _AddEditAppointmentPageState();
}

class _AddEditAppointmentPageState extends State<AddEditAppointmentPage> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _clientController = TextEditingController();
  final _notesController = TextEditingController();

  final AppointmentController _controller = Get.find<AppointmentController>();

  DateTime _date = DateTime.now();
  DateTime? _selectedSlot;
  int _duration = 30;
  int _reminder = 30;
  AppointmentStatus _status = AppointmentStatus.scheduled;

  bool get _isEditing => widget.editing != null;

  @override
  void initState() {
    super.initState();
    final item = widget.editing;
    if (item != null) {
      _titleController.text = item.title;
      _clientController.text = item.clientName;
      _notesController.text = item.notes;
      _date = DateTime(item.startAt.year, item.startAt.month, item.startAt.day);
      _selectedSlot = item.startAt;
      _duration = item.endAt.difference(item.startAt).inMinutes;
      _reminder = item.reminderMinutes;
      _status = item.status;
    } else {
      _duration = _controller.sessionDurationMinutes.value;
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _clientController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final slotFormat = DateFormat('hh:mm a');

    return Scaffold(
      appBar: _isEditing ? AppBar(title: const Text('Edit Appointment')) : null,
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _titleController,
              decoration: const InputDecoration(labelText: 'Appointment title'),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Required';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _clientController,
              decoration: const InputDecoration(labelText: 'Client name'),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Required';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _notesController,
              maxLines: 2,
              decoration: const InputDecoration(labelText: 'Notes (optional)'),
            ),
            const SizedBox(height: 12),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Date'),
              subtitle: Text(DateFormat('EEE, dd MMM yyyy').format(_date)),
              trailing: const Icon(Icons.calendar_today),
              onTap: () async {
                final firstDate = DateTime.now().subtract(const Duration(days: 365));
                final lastDate = DateTime.now().add(const Duration(days: 365 * 2));
                final initialDate = _resolveInitialDateForPicker(
                  preferred: _date,
                  firstDate: firstDate,
                  lastDate: lastDate,
                );

                final picked = await showDatePicker(
                  context: context,
                  initialDate: initialDate,
                  firstDate: firstDate,
                  lastDate: lastDate,
                  selectableDayPredicate: _isDateSelectable,
                );
                if (picked == null) {
                  return;
                }

                setState(() {
                  _date = DateTime(picked.year, picked.month, picked.day);
                  _selectedSlot = null;
                });
              },
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: _reminder,
              decoration: const InputDecoration(labelText: 'Reminder before appointment'),
              items: const [10, 15, 30, 60]
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text('$value minutes'),
                    ),
                  )
                  .toList(),
              onChanged: (value) {
                if (value == null) {
                  return;
                }
                setState(() {
                  _reminder = value;
                });
              },
            ),
            if (_isEditing) ...[
              const SizedBox(height: 12),
              DropdownButtonFormField<AppointmentStatus>(
                initialValue: _status,
                decoration: const InputDecoration(labelText: 'Status'),
                items: const [
                  DropdownMenuItem(
                    value: AppointmentStatus.scheduled,
                    child: Text('Scheduled'),
                  ),
                  DropdownMenuItem(
                    value: AppointmentStatus.cancelled,
                    child: Text('Cancelled'),
                  ),
                ],
                onChanged: (value) {
                  if (value == null) {
                    return;
                  }
                  setState(() {
                    _status = value;
                  });
                },
              ),
            ],
            const SizedBox(height: 12),
            Obx(() {
              final slots = _controller.generateAvailableSlots(
                _date,
                durationMinutes: _duration,
              );

              final editing = widget.editing;
              if (_isEditing && editing != null) {
                final alreadyExists = slots.any(
                  (slot) => slot.isAtSameMomentAs(editing.startAt),
                );
                if (!alreadyExists && _status == AppointmentStatus.scheduled) {
                  slots.insert(0, editing.startAt);
                }
              }

              return DropdownButtonFormField<DateTime>(
                initialValue: _selectedSlot,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Available slot'),
                items: slots
                    .map(
                      (slot) => DropdownMenuItem<DateTime>(
                        value: slot,
                        child: Text(slotFormat.format(slot)),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _selectedSlot = value),
                validator: (value) => value == null ? 'Select a time slot' : null,
              );
            }),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _onSave,
              icon: Icon(_isEditing ? Icons.save : Icons.add),
              label: Text(_isEditing ? 'Update Appointment' : 'Create Appointment'),
            ),
          ],
        ),
      ),
    );
  }

  void _onSave() {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final startAt = _selectedSlot;
    if (startAt == null) {
      return;
    }

    String? error;

    if (_isEditing) {
      error = _controller.updateAppointment(
        widget.editing!,
        title: _titleController.text.trim(),
        clientName: _clientController.text.trim(),
        notes: _notesController.text.trim(),
        startAt: startAt,
        durationMinutes: _duration,
        reminderMinutes: _reminder,
        status: _status,
      );
    } else {
      error = _controller.addAppointment(
        title: _titleController.text.trim(),
        clientName: _clientController.text.trim(),
        notes: _notesController.text.trim(),
        startAt: startAt,
        durationMinutes: _duration,
        reminderMinutes: _reminder,
      );
    }

    if (error != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error)),
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(_isEditing ? 'Appointment updated successfully.' : 'Appointment created successfully.'),
      ),
    );

    if (_isEditing) {
      Get.back<void>();
      return;
    }

    _formKey.currentState?.reset();
    setState(() {
      _titleController.clear();
      _clientController.clear();
      _notesController.clear();
      _selectedSlot = null;
      _duration = _controller.sessionDurationMinutes.value;
      _reminder = 30;
      _date = DateTime.now();
      _status = AppointmentStatus.scheduled;
    });
  }

  bool _isDateSelectable(DateTime date) {
    final normalizedDate = DateTime(date.year, date.month, date.day);

    if (_isEditing && widget.editing != null) {
      final editingDate = DateTime(
        widget.editing!.startAt.year,
        widget.editing!.startAt.month,
        widget.editing!.startAt.day,
      );
      if (DateUtils.isSameDay(normalizedDate, editingDate)) {
        return true;
      }
    }

    final slots = _controller.generateAvailableSlots(
      normalizedDate,
      durationMinutes: _duration,
    );

    return slots.isNotEmpty;
  }

  DateTime _resolveInitialDateForPicker({required DateTime preferred, required DateTime firstDate, required DateTime lastDate}) {
    final normalizedPreferred = DateTime(preferred.year, preferred.month, preferred.day);
    final minDate = DateTime(firstDate.year, firstDate.month, firstDate.day);
    final maxDate = DateTime(lastDate.year, lastDate.month, lastDate.day);

    var clamped = normalizedPreferred;
    if (clamped.isBefore(minDate)) {
      clamped = minDate;
    }
    if (clamped.isAfter(maxDate)) {
      clamped = maxDate;
    }

    if (_isDateSelectable(clamped)) {
      return clamped;
    }

    var forward = clamped;
    while (forward.isBefore(maxDate)) {
      forward = forward.add(const Duration(days: 1));
      if (_isDateSelectable(forward)) {
        return forward;
      }
    }

    var backward = clamped;
    while (backward.isAfter(minDate)) {
      backward = backward.subtract(const Duration(days: 1));
      if (_isDateSelectable(backward)) {
        return backward;
      }
    }

    return clamped;
  }
}
