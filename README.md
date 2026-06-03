# Laravel-Used REST API Starter

This document covers only the API routes that the Laravel DTR app calls from `app/Services/DtrApiClient.php`.

It is meant to be a starter that you can edit and expand later.

## Base URL

Set this in Laravel with `DTR_API_BASE_URL`.

Example:

```text
https://dtr2026-read.iamlance.site
```

## Authentication

Every request uses a shared JWT bearer token.

Required headers:

```http
Authorization: Bearer <jwt>
Accept: application/json
Content-Type: application/json
```

Match these values between the API and Laravel:

- API secret: `Jwt__Key` in `conn.env` or environment variables
- Laravel secret: `DTR_API_JWT_KEY`
- Issuer: `Jwt:Issuer` / `DTR_API_JWT_ISSUER`
- Audience: `Jwt:Audience` / `DTR_API_JWT_AUDIENCE`

No login endpoint is used yet.

## Endpoint Summary

| Laravel method | HTTP | Route | Purpose |
| --- | --- | --- | --- |
| `getStatuses()` | `GET` | `/api/TblEmpstatus` | Load employee status options |
| `getOffices()` | `GET` | `/api/SdoOffice` | Load office options |
| `getBios()` | `GET` | `/api/TblBio` | Load employee bio records used by the DTR template |
| `getSignatories()` | `GET` | `/api/TblSchoolSignatories` | Load signatory names and designations |
| `searchLogs()` | `GET` | `/api/TblLogs/search-by-filters` | Load monthly logs for the DTR preview |

## Endpoint Details

### 1. `GET /api/TblEmpstatus`

Used by Laravel to populate status dropdowns.

Response example:

```json
[
  {
    "sId": 1,
    "sEmp": "CASUAL"
  }
]
```

Laravel uses:

- `sId`
- `sEmp`

---

### 2. `GET /api/SdoOffice`

Used by Laravel to populate office dropdowns.

Response example:

```json
[
  {
    "officeId": 3,
    "officeOsId": 502678,
    "officeName": "BANGAL INTEGRATED SCHOOL"
  }
]
```

Laravel uses:

- `officeId`
- `officeOsId`
- `officeName`

The API may return extra office fields too, but Laravel does not currently use them.

---

### 3. `GET /api/TblBio`

Used by Laravel to build the DTR preview and report rows.

Response example:

```json
[
  {
    "bId": 1,
    "bGuid": "2376D9BF-1CCE-45CF-B3FF-FABA04894F20",
    "bFirstname": "JUAN",
    "bLastname": "DELA CRUZ",
    "bDesignation": "TEACHER I",
    "bOfficeId": 107141,
    "bMacId": 300,
    "bHrsAmIn": "08:00",
    "bHrsAmOut": "12:00",
    "bHrsPmIn": "13:00",
    "bHrsPmOut": "17:00",
    "bSignatories": "48", // foreign key from signatories table
    "bUnit": "21", //foreign key from header1 table
    "bJobStatus": "Permanent",
    "bIsGlobal": false,
    "bIsActive": true
  }
]
```

Laravel uses these fields:

- `bId`
- `bGuid`
- `bFirstname`
- `bLastname`
- `bDesignation`
- `bOfficeId`
- `bMacId`
- `bHrsAmIn`
- `bHrsAmOut`
- `bHrsPmIn`
- `bHrsPmOut`
- `bSignatories`
- `bUnit`
- `bJobStatus`
- `bIsGlobal`
- `bIsActive`

---

### 4. `GET /api/TblSchoolSignatories`

Used by Laravel to map signatory names to designations in the report.

Response example:

```json
[
  {
    "fldId": 1,
    "fldPname": "Jane Doe",
    "fldPdesignation": "SCHOOL PRINCIPAL II"
  }
]
```

Laravel uses:

- `fldId`
- `fldPname`
- `fldPdesignation`

---

### 5. `GET /api/TblLogs/search-by-filters`

Used by Laravel to load logs for the selected month, office, and MAC ID.
It is also used repeatedly for the global-log flow.

Query parameters:

- `year` - optional, but required when `month` is provided
- `month` - optional, 1 to 12
- `fldOfficeId` - optional
- `fldMacId` - optional

Validation rules:

- At least one filter must be provided
- `month` requires `year`
- `year` must be between `1` and `9999`
- `month` must be between `1` and `12`

Request example:

```http
GET /api/TblLogs/search-by-filters?year=2026&month=3&fldOfficeId=1&fldMacId=11
```

Response example:

```json
[
  {
    "fldId": 2584309,
    "fldMacId": "11",
    "fldDatetime": "2026-03-26 18:00:09",
    "fldLog": "LOGOUT:PM",
    "fldOfficeId": "1",
    "deviceId": "1-ICTU"
  }
]
```

Laravel uses:

- `fldId`
- `fldMacId`
- `fldDatetime`
- `fldLog`
- `fldOfficeId`
- `deviceId`

The repository sorts this endpoint by `fldId`.

---

## Laravel Flow Summary

This is the current request order used by the Laravel app:

1. Load statuses and offices for the dashboard
2. Load bios and signatories for the DTR preview
3. Search logs for the selected month and filters


