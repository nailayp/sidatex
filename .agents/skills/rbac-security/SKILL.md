---
name: rbac-security
description: Reviews SIDATEX role-based access, PRIVATE and RILIS visibility, upload permissions, direct URL access, and secure Laravel file handling.
---

# RBAC and Security Skill

Treat authorization as a server-side requirement.

Expected role boundaries:
- Super Admin: upload, process, manage, release.
- Pegawai: view/filter/download RILIS; no upload/process.
- Viewer Eksekutif/Berkepentingan: view/comment and access PRIVATE only as explicitly approved.
- DRAFT and Trash are not ordinary dashboard data.

Review every route, controller, service, and query for authorization. Test direct URL access and forged request parameters. Hiding a button is not sufficient.

For uploads, validate size, extension, MIME/content, path, storage location, and error handling. Prevent path traversal and unsafe file access. Do not expose secrets, credentials, raw exception traces, or private data in responses/logs.
