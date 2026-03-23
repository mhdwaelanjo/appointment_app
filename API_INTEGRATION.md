# API Integration Guide — Smart Appointment Management System

This document describes the REST API contract required by the Flutter client.  
All datetime values use **ISO 8601 / UTC** format (`YYYY-MM-DDTHH:mm:ssZ`) unless stated otherwise.  
Time-of-day values use **`HH:mm`** (24-hour, e.g. `"09:00"`).  
Weekday integers follow the Dart / ISO-8601 convention: `1 = Monday … 7 = Sunday`.

---

## Table of Contents

1. [Base URL & Headers](#1-base-url--headers)
2. [Authentication](#2-authentication)
3. [Common Response Envelope](#3-common-response-envelope)
4. [Error Responses](#4-error-responses)
5. [Appointments](#5-appointments)
   - 5.1 [List Appointments](#51-list-appointments)
   - 5.2 [Get Appointment](#52-get-appointment)
   - 5.3 [Create Appointment](#53-create-appointment)
   - 5.4 [Update Appointment](#54-update-appointment)
   - 5.5 [Cancel Appointment](#55-cancel-appointment)
   - 5.6 [Delete Appointment](#56-delete-appointment)
6. [Available Slots](#6-available-slots)
7. [Working Days (Availability)](#7-working-days-availability)
   - 7.1 [Get All Working Days](#71-get-all-working-days)
   - 7.2 [Update a Working Day](#72-update-a-working-day)
8. [Breaks](#8-breaks)
   - 8.1 [Get Breaks for a Weekday](#81-get-breaks-for-a-weekday)
   - 8.2 [Add a Break](#82-add-a-break)
   - 8.3 [Remove a Break](#83-remove-a-break)
9. [Temporary Closures](#9-temporary-closures)
   - 9.1 [List Temporary Closures](#91-list-temporary-closures)
   - 9.2 [Add Temporary Closure](#92-add-temporary-closure)
   - 9.3 [Remove Temporary Closure](#93-remove-temporary-closure)
10. [Settings](#10-settings)
    - 10.1 [Get Settings](#101-get-settings)
    - 10.2 [Update Settings](#102-update-settings)
11. [Data Models Reference](#11-data-models-reference)

---

## 1. Base URL & Headers

```
Base URL: https://api.example.com/v1
```

Every request must include:

| Header          | Value                        |
|-----------------|------------------------------|
| `Content-Type`  | `application/json`           |
| `Accept`        | `application/json`           |
| `Authorization` | `Bearer <access_token>`      |

---

## 2. Authentication

Authentication is **Bearer token** based. Obtain a token from your identity provider and pass it with every request.

If the token is missing or expired the API returns **`401 Unauthorized`**.

---

## 3. Common Response Envelope

All successful responses are wrapped in:

```json
{
  "success": true,
  "data": { ... }
}
```

List responses add pagination metadata:

```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "total": 42,
    "page": 1,
    "per_page": 20
  }
}
```

---

## 4. Error Responses

```json
{
  "success": false,
  "error": {
    "code": "SLOT_CONFLICT",
    "message": "Selected slot conflicts with another appointment."
  }
}
```

| HTTP Status | Meaning                                   |
|-------------|-------------------------------------------|
| 400         | Validation / business-rule error          |
| 401         | Missing or invalid auth token             |
| 403         | Forbidden (insufficient permissions)      |
| 404         | Resource not found                        |
| 409         | Conflict (e.g. slot already booked)       |
| 422         | Unprocessable entity (schema violation)   |
| 500         | Internal server error                     |

---

## 5. Appointments

### 5.1 List Appointments

Retrieve all appointments. Supports optional filtering.

```
GET /appointments
```

**Query Parameters**

| Parameter  | Type   | Required | Description                                        |
|------------|--------|----------|----------------------------------------------------|
| `date`     | string | No       | Filter by date `YYYY-MM-DD`. Returns appointments whose `start_at` falls on this date. |
| `status`   | string | No       | Filter by status: `scheduled` or `cancelled`.      |
| `page`     | int    | No       | Page number (default: `1`).                        |
| `per_page` | int    | No       | Items per page (default: `20`, max: `100`).        |

**Example Request**

```
GET /appointments?date=2026-03-23&status=scheduled
```

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": [
    {
      "id": "APT-1001",
      "title": "Consultation",
      "client_name": "John Miller",
      "notes": "First-time booking",
      "start_at": "2026-03-23T10:00:00Z",
      "end_at": "2026-03-23T10:30:00Z",
      "reminder_minutes": 30,
      "status": "scheduled"
    },
    {
      "id": "APT-1002",
      "title": "Follow-up",
      "client_name": "Sophia Clark",
      "notes": "",
      "start_at": "2026-03-23T14:00:00Z",
      "end_at": "2026-03-23T15:00:00Z",
      "reminder_minutes": 60,
      "status": "scheduled"
    }
  ],
  "meta": {
    "total": 2,
    "page": 1,
    "per_page": 20
  }
}
```

---

### 5.2 Get Appointment

```
GET /appointments/{id}
```

**Path Parameters**

| Parameter | Type   | Description    |
|-----------|--------|----------------|
| `id`      | string | Appointment ID |

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "id": "APT-1001",
    "title": "Consultation",
    "client_name": "John Miller",
    "notes": "First-time booking",
    "start_at": "2026-03-23T10:00:00Z",
    "end_at": "2026-03-23T10:30:00Z",
    "reminder_minutes": 30,
    "status": "scheduled"
  }
}
```

**Error `404 Not Found`**

```json
{
  "success": false,
  "error": {
    "code": "APPOINTMENT_NOT_FOUND",
    "message": "Appointment not found."
  }
}
```

---

### 5.3 Create Appointment

```
POST /appointments
```

**Request Body**

```json
{
  "title": "Consultation",
  "client_name": "John Miller",
  "notes": "First-time booking",
  "start_at": "2026-03-23T10:00:00Z",
  "duration_minutes": 30,
  "reminder_minutes": 30
}
```

| Field              | Type    | Required | Constraints                              |
|--------------------|---------|----------|------------------------------------------|
| `title`            | string  | Yes      | Non-empty                                |
| `client_name`      | string  | Yes      | Non-empty                                |
| `notes`            | string  | No       | Defaults to `""`                         |
| `start_at`         | string  | Yes      | ISO 8601 UTC datetime                    |
| `duration_minutes` | integer | Yes      | Positive integer (e.g. `30`, `60`)       |
| `reminder_minutes` | integer | Yes      | One of `10`, `15`, `30`, `60`           |

**Business Rules Validated by the Server**

- `start_at` must fall within the configured working hours of its weekday.
- The computed slot (`start_at` → `start_at + duration_minutes`) must not overlap any existing **scheduled** appointment.
- The slot must not fall within a break or temporary closure.
- The weekday must be enabled.

**Example Response `201 Created`**

```json
{
  "success": true,
  "data": {
    "id": "APT-1748291347291",
    "title": "Consultation",
    "client_name": "John Miller",
    "notes": "First-time booking",
    "start_at": "2026-03-23T10:00:00Z",
    "end_at": "2026-03-23T10:30:00Z",
    "reminder_minutes": 30,
    "status": "scheduled"
  }
}
```

**Error `409 Conflict` — Slot already taken**

```json
{
  "success": false,
  "error": {
    "code": "SLOT_CONFLICT",
    "message": "Selected slot conflicts with another appointment."
  }
}
```

**Error `400 Bad Request` — Outside working hours**

```json
{
  "success": false,
  "error": {
    "code": "OUTSIDE_WORKING_HOURS",
    "message": "Appointment is outside configured working hours."
  }
}
```

**Error `400 Bad Request` — Day not available**

```json
{
  "success": false,
  "error": {
    "code": "DAY_NOT_AVAILABLE",
    "message": "Selected day is not available for appointments."
  }
}
```

**Error `400 Bad Request` — Blocked by break or closure**

```json
{
  "success": false,
  "error": {
    "code": "TIME_BLOCKED",
    "message": "Selected time is blocked by a break or temporary closure."
  }
}
```

---

### 5.4 Update Appointment

Update all mutable fields of an appointment (reschedule, rename, change reminder, or change status).

```
PUT /appointments/{id}
```

**Request Body**

```json
{
  "title": "Follow-up",
  "client_name": "John Miller",
  "notes": "Rescheduled at client request",
  "start_at": "2026-03-23T11:00:00Z",
  "duration_minutes": 30,
  "reminder_minutes": 15,
  "status": "scheduled"
}
```

| Field              | Type    | Required | Constraints                              |
|--------------------|---------|----------|------------------------------------------|
| `title`            | string  | Yes      | Non-empty                                |
| `client_name`      | string  | Yes      | Non-empty                                |
| `notes`            | string  | No       | Defaults to `""`                         |
| `start_at`         | string  | Yes      | ISO 8601 UTC datetime                    |
| `duration_minutes` | integer | Yes      | Positive integer                         |
| `reminder_minutes` | integer | Yes      | One of `10`, `15`, `30`, `60`           |
| `status`           | string  | Yes      | `"scheduled"` or `"cancelled"`           |

> When `status` is `"cancelled"`, slot-conflict and working-hours validation is **skipped**.

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "id": "APT-1001",
    "title": "Follow-up",
    "client_name": "John Miller",
    "notes": "Rescheduled at client request",
    "start_at": "2026-03-23T11:00:00Z",
    "end_at": "2026-03-23T11:30:00Z",
    "reminder_minutes": 15,
    "status": "scheduled"
  }
}
```

Errors are identical to [Create Appointment](#53-create-appointment) plus `404 Not Found`.

---

### 5.5 Cancel Appointment

Shortcut to set `status = cancelled` without changing any other field.

```
PATCH /appointments/{id}/cancel
```

No request body required.

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "id": "APT-1001",
    "title": "Consultation",
    "client_name": "John Miller",
    "notes": "First-time booking",
    "start_at": "2026-03-23T10:00:00Z",
    "end_at": "2026-03-23T10:30:00Z",
    "reminder_minutes": 30,
    "status": "cancelled"
  }
}
```

---

### 5.6 Delete Appointment

Permanently removes an appointment record.

```
DELETE /appointments/{id}
```

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": null
}
```

---

## 6. Available Slots

Returns the list of bookable start times for a given date and session duration. The server applies the same logic as the client: working hours minus breaks, temporary closures, and existing scheduled appointments.

```
GET /availability/slots
```

**Query Parameters**

| Parameter          | Type    | Required | Description                                    |
|--------------------|---------|----------|------------------------------------------------|
| `date`             | string  | Yes      | Target date `YYYY-MM-DD`                       |
| `duration_minutes` | integer | No       | Session duration in minutes (default: `30`)    |

**Example Request**

```
GET /availability/slots?date=2026-03-23&duration_minutes=30
```

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "date": "2026-03-23",
    "duration_minutes": 30,
    "slots": [
      "2026-03-23T09:00:00Z",
      "2026-03-23T09:30:00Z",
      "2026-03-23T10:30:00Z",
      "2026-03-23T11:00:00Z",
      "2026-03-23T11:30:00Z",
      "2026-03-23T13:00:00Z",
      "2026-03-23T13:30:00Z",
      "2026-03-23T14:00:00Z",
      "2026-03-23T14:30:00Z",
      "2026-03-23T15:00:00Z",
      "2026-03-23T15:30:00Z",
      "2026-03-23T16:00:00Z",
      "2026-03-23T16:30:00Z"
    ]
  }
}
```

**Example Response — Day not available `200 OK`**

```json
{
  "success": true,
  "data": {
    "date": "2026-03-22",
    "duration_minutes": 30,
    "slots": []
  }
}
```

---

## 7. Working Days (Availability)

### 7.1 Get All Working Days

Returns the availability configuration for every weekday (`1–7`).

```
GET /availability/working-days
```

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": [
    {
      "weekday": 1,
      "weekday_label": "Monday",
      "enabled": true,
      "start": "09:00",
      "end": "17:00"
    },
    {
      "weekday": 2,
      "weekday_label": "Tuesday",
      "enabled": true,
      "start": "09:00",
      "end": "17:00"
    },
    {
      "weekday": 3,
      "weekday_label": "Wednesday",
      "enabled": true,
      "start": "09:00",
      "end": "17:00"
    },
    {
      "weekday": 4,
      "weekday_label": "Thursday",
      "enabled": true,
      "start": "09:00",
      "end": "17:00"
    },
    {
      "weekday": 5,
      "weekday_label": "Friday",
      "enabled": true,
      "start": "09:00",
      "end": "17:00"
    },
    {
      "weekday": 6,
      "weekday_label": "Saturday",
      "enabled": false,
      "start": "09:00",
      "end": "17:00"
    },
    {
      "weekday": 7,
      "weekday_label": "Sunday",
      "enabled": false,
      "start": "09:00",
      "end": "17:00"
    }
  ]
}
```

---

### 7.2 Update a Working Day

```
PUT /availability/working-days/{weekday}
```

**Path Parameters**

| Parameter | Type    | Description                          |
|-----------|---------|--------------------------------------|
| `weekday` | integer | `1` (Monday) through `7` (Sunday)    |

**Request Body**

```json
{
  "enabled": true,
  "start": "08:00",
  "end": "18:00"
}
```

| Field     | Type    | Required | Constraints                                    |
|-----------|---------|----------|------------------------------------------------|
| `enabled` | boolean | Yes      |                                                |
| `start`   | string  | Yes      | `HH:mm` 24-hour format; must be before `end`  |
| `end`     | string  | Yes      | `HH:mm` 24-hour format; must be after `start` |

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "weekday": 1,
    "weekday_label": "Monday",
    "enabled": true,
    "start": "08:00",
    "end": "18:00"
  }
}
```

**Error `400 Bad Request` — Invalid time range**

```json
{
  "success": false,
  "error": {
    "code": "INVALID_TIME_RANGE",
    "message": "Start time must be before end time."
  }
}
```

---

## 8. Breaks

Breaks are recurring weekly time blocks during which no appointments can be booked.

### 8.1 Get Breaks for a Weekday

```
GET /availability/breaks/{weekday}
```

**Path Parameters**

| Parameter | Type    | Description                       |
|-----------|---------|-----------------------------------|
| `weekday` | integer | `1` (Monday) through `7` (Sunday) |

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "weekday": 1,
    "breaks": [
      {
        "index": 0,
        "start": "12:00",
        "end": "13:00"
      }
    ]
  }
}
```

---

### 8.2 Add a Break

```
POST /availability/breaks/{weekday}
```

**Request Body**

```json
{
  "start": "12:00",
  "end": "13:00"
}
```

| Field   | Type   | Required | Constraints                                    |
|---------|--------|----------|------------------------------------------------|
| `start` | string | Yes      | `HH:mm` 24-hour format; must be before `end`  |
| `end`   | string | Yes      | `HH:mm` 24-hour format; must be after `start` |

**Example Response `201 Created`**

```json
{
  "success": true,
  "data": {
    "weekday": 1,
    "breaks": [
      {
        "index": 0,
        "start": "12:00",
        "end": "13:00"
      }
    ]
  }
}
```

**Error `409 Conflict` — Break overlaps existing break**

```json
{
  "success": false,
  "error": {
    "code": "BREAK_OVERLAP",
    "message": "This break overlaps an existing break."
  }
}
```

**Error `400 Bad Request` — Invalid time range**

```json
{
  "success": false,
  "error": {
    "code": "INVALID_TIME_RANGE",
    "message": "Break time range is invalid."
  }
}
```

---

### 8.3 Remove a Break

```
DELETE /availability/breaks/{weekday}/{index}
```

**Path Parameters**

| Parameter | Type    | Description                            |
|-----------|---------|----------------------------------------|
| `weekday` | integer | `1` (Monday) through `7` (Sunday)      |
| `index`   | integer | Zero-based position in the breaks list |

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "weekday": 1,
    "breaks": []
  }
}
```

---

## 9. Temporary Closures

Temporary closures block specific date + time ranges on top of recurring breaks. A full-day closure means `start = "00:00"` and `end = "23:59"`.

### 9.1 List Temporary Closures

```
GET /availability/closures
```

**Query Parameters**

| Parameter | Type   | Required | Description                         |
|-----------|--------|----------|-------------------------------------|
| `date`    | string | No       | Filter by date `YYYY-MM-DD`         |

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": [
    {
      "id": "CLS-1748291400000",
      "date": "2026-03-25",
      "start": "09:00",
      "end": "12:00",
      "reason": "Staff training"
    },
    {
      "id": "CLS-1748291500000",
      "date": "2026-03-30",
      "start": "00:00",
      "end": "23:59",
      "reason": "Public holiday"
    }
  ]
}
```

---

### 9.2 Add Temporary Closure

```
POST /availability/closures
```

**Request Body**

```json
{
  "date": "2026-03-25",
  "start": "09:00",
  "end": "12:00",
  "reason": "Staff training"
}
```

| Field    | Type   | Required | Constraints                                                |
|----------|--------|----------|------------------------------------------------------------|
| `date`   | string | Yes      | `YYYY-MM-DD`                                               |
| `start`  | string | Yes      | `HH:mm` 24-hour format; must be before `end`              |
| `end`    | string | Yes      | `HH:mm` 24-hour format; must be after `start`             |
| `reason` | string | No       | Human-readable explanation; defaults to `""`               |

**Business Rules**

- If the incoming range is full-day (`00:00–23:59`) and any closure already exists on that date → `409`.
- If a full-day closure already exists on the date → `409`.
- Overlapping partial ranges on the same date → `409`.

**Example Response `201 Created`**

```json
{
  "success": true,
  "data": {
    "id": "CLS-1748291400000",
    "date": "2026-03-25",
    "start": "09:00",
    "end": "12:00",
    "reason": "Staff training"
  }
}
```

**Error `409 Conflict` — Overlapping closure**

```json
{
  "success": false,
  "error": {
    "code": "CLOSURE_OVERLAP",
    "message": "This closure overlaps an existing closure."
  }
}
```

**Error `400 Bad Request` — Invalid time range**

```json
{
  "success": false,
  "error": {
    "code": "INVALID_TIME_RANGE",
    "message": "Closure time range is invalid."
  }
}
```

---

### 9.3 Remove Temporary Closure

```
DELETE /availability/closures/{id}
```

**Path Parameters**

| Parameter | Type   | Description       |
|-----------|--------|-------------------|
| `id`      | string | Closure ID        |

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": null
}
```

---

## 10. Settings

### 10.1 Get Settings

```
GET /settings
```

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "session_duration_minutes": 30
  }
}
```

---

### 10.2 Update Settings

```
PUT /settings
```

**Request Body**

```json
{
  "session_duration_minutes": 60
}
```

| Field                      | Type    | Required | Constraints            |
|----------------------------|---------|----------|------------------------|
| `session_duration_minutes` | integer | Yes      | `30` or `60`           |

**Example Response `200 OK`**

```json
{
  "success": true,
  "data": {
    "session_duration_minutes": 60
  }
}
```

---

## 11. Data Models Reference

### Appointment

| Field               | Type    | Description                                          |
|---------------------|---------|------------------------------------------------------|
| `id`                | string  | Unique identifier (e.g. `"APT-1001"`)                |
| `title`             | string  | Appointment type/title                               |
| `client_name`       | string  | Name of the client                                   |
| `notes`             | string  | Optional free-text notes                             |
| `start_at`          | string  | ISO 8601 UTC start datetime                          |
| `end_at`            | string  | ISO 8601 UTC end datetime (derived: start + duration)|
| `reminder_minutes`  | integer | Minutes before `start_at` to trigger a notification |
| `status`            | string  | `"scheduled"` or `"cancelled"`                       |

### WorkingDay

| Field            | Type    | Description                                |
|------------------|---------|--------------------------------------------|
| `weekday`        | integer | `1` (Monday) – `7` (Sunday)                |
| `weekday_label`  | string  | Human-readable label (e.g. `"Monday"`)     |
| `enabled`        | boolean | Whether appointments are allowed this day  |
| `start`          | string  | Day start time `HH:mm`                     |
| `end`            | string  | Day end time `HH:mm`                       |

### Break

| Field   | Type    | Description                      |
|---------|---------|----------------------------------|
| `index` | integer | Zero-based position in the list  |
| `start` | string  | Break start time `HH:mm`         |
| `end`   | string  | Break end time `HH:mm`           |

### TemporaryClosure

| Field    | Type   | Description                                                    |
|----------|--------|----------------------------------------------------------------|
| `id`     | string | Unique identifier (e.g. `"CLS-1748291400000"`)                 |
| `date`   | string | Affected date `YYYY-MM-DD`                                     |
| `start`  | string | Closure start time `HH:mm` (`"00:00"` for full-day)           |
| `end`    | string | Closure end time `HH:mm` (`"23:59"` for full-day)             |
| `reason` | string | Optional human-readable reason                                 |

### Settings

| Field                      | Type    | Description                              |
|----------------------------|---------|------------------------------------------|
| `session_duration_minutes` | integer | Default session length (`30` or `60` min)|

---

## Error Code Reference

| Code                   | HTTP Status | Trigger                                               |
|------------------------|-------------|-------------------------------------------------------|
| `APPOINTMENT_NOT_FOUND`| 404         | No appointment with the given ID                      |
| `SLOT_CONFLICT`        | 409         | Slot overlaps an existing scheduled appointment       |
| `OUTSIDE_WORKING_HOURS`| 400         | Slot falls outside the day's configured working hours |
| `DAY_NOT_AVAILABLE`    | 400         | The weekday is disabled                               |
| `TIME_BLOCKED`         | 400         | Slot falls inside a break or temporary closure        |
| `INVALID_TIME_RANGE`   | 400         | `start` is not before `end`                           |
| `BREAK_OVERLAP`        | 409         | New break overlaps an existing break on the same day  |
| `CLOSURE_OVERLAP`      | 409         | New closure overlaps an existing closure on that date |
| `FULL_DAY_CLOSURE_EXISTS`| 409      | A full-day closure already exists on the date         |
