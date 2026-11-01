# NovaTransfer - Online File Transfer System

A fast, secure, and modern PHP-based online file transfer application. Built with vanilla modern JavaScript and CSS glassmorphism aesthetics, supporting real-time upload progress, expiration policies, password protection, and metadata storage in JSON.

---

## Key Features

1. **Upload Files**:
   - Modern drag-and-drop zone and click-to-browse dialog.
   - Real-time animated gradient upload progress bar using `XMLHttpRequest.upload.onprogress`.
   - Real-time upload speed calculation (KB/s, MB/s), uploaded bytes / total bytes, and ETA estimation.
   - Cancel button for in-flight transfers.

2. **File Validation & Security**:
   - **Pre-upload Client-Side Validation**: Instant feedback before data is transmitted over the network.
   - **Server-Side Validation**: Strict checks on size (`upload_max_filesize` / `post_max_size`), MIME type (`finfo`), and blocked executable extensions (`.php`, `.exe`, `.bat`, `.cmd`, `.sh`, `.vbs`, etc.).
   - Filename sanitization protecting against path traversal (`../`) and null-byte injection.

3. **Prevent Unauthorized Access**:
   - Storage directory is protected with `.htaccess` (`Require all denied` / `Deny from all`) and 403 Forbidden PHP fallbacks.
   - Uploaded files are stored with obfuscated unique identifiers (`<uuid>.vault`) without original extensions, preventing direct execution.
   - All downloads are streamed through PHP controllers (`api/download.php`) with path containment validation (`realpath`), preventing arbitrary file retrieval.
   - Optional password protection with bcrypt password hashing (`password_hash`).

4. **JSON Metadata Database**:
   - File metadata is recorded in `data/files.json` using thread-safe file locking (`flock(..., LOCK_EX)`).
   - Tracks file UUID, share token, sanitized original name, size, MIME type, category, upload timestamp, downloads count, expiration, and password hash.

5. **Display & Management of Uploaded Files**:
   - Rich card grid with file type category badges (Documents, Images, Video, Audio, Archives, Code).
   - Real-time search filter and category tab pills.
   - Sort by Newest, Oldest, Size, and Name.
   - Storage usage and downloads stats pill.

6. **Sharing & Downloading**:
   - One-click **Copy Link** with instant animated toast notifications.
   - Integrated offline **QR Code Generator** for mobile download scanning.
   - Dedicated public recipient page (`download.php?token=...`) with metadata card, security scan badge, and optional password unlock screen.
   - Direct chunked streaming download handler.

---

## Project Structure

```
d:\File Transfer System\
├── .htaccess                   # Root security rules & PHP upload size directives
├── config.php                  # System configuration, paths, and upload limits
├── index.php                   # Main dashboard & upload interface
├── download.php                # Public recipient download landing page
├── api/
│   ├── upload.php              # Upload endpoint (validation, storage, JSON metadata)
│   ├── files.php               # List, search, filter, and delete files
│   └── download.php            # Secure streaming & password authentication endpoint
├── includes/
│   ├── Storage.php             # JSON metadata store + thread-safe locking (flock)
│   ├── Security.php            # Security validation, MIME check, and password hashing
│   └── Utils.php               # Byte formatting, categories, and helpers
├── assets/
│   ├── css/
│   │   └── style.css           # Vanilla CSS design system (dark/light themes, glassmorphism)
│   └── js/
│       ├── app.js              # Frontend upload progress, filters, and clipboard logic
│       └── qrcode.min.js       # Offline canvas-based QR code engine
├── storage/
│   ├── .htaccess               # Deny all direct HTTP access
│   ├── index.php               # 403 Forbidden gate
│   └── files/                  # Obfuscated file payloads (<uuid>.vault)
└── data/
    ├── .htaccess               # Deny all direct HTTP access
    ├── index.php               # 403 Forbidden gate
    └── files.json              # Structured JSON database of file transfers
```

---

## Getting Started

### 1. Run with PHP Built-in Server

Open a terminal in the project directory and run:

```bash
php -S 127.0.0.1:8000
```

Then visit:
```
http://127.0.0.1:8000/index.php
```

### 2. Run with XAMPP / Apache

1. Copy or clone this folder into your XAMPP `htdocs` directory (e.g. `C:\xampp\htdocs\file-transfer`).
2. Start Apache from the XAMPP Control Panel.
3. Access `http://localhost/file-transfer/index.php`.
