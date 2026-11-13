# Campus Lost & Found: University Web Service (PHP 8 + MySQL)

**Course:** Information and Communication Technologies (ICT) — Final Capstone Project  
**Institution:** Astana IT University, Department of Computer Science  
**Group:** SE-2626  
**Instructor:** Kazbekova Diana  
**Team Members & Roles:**
- **Yerkanat Akhmet** (ID: 262736) — Lead Analyst & Project Manager (Requirements R1–R12, Survey Validation, Usability Testing, Analytical Data Conclusions)
- **Bekei Kydyrali** (ID: 261036) — Programmer, Cloud Architect & UX Designer (PHP 8 MVC Prototype, 8-Table MySQL Schema, Bcrypt & CSRF Security, Admin Analytics Panel, IoT Smart Drop-Box Architecture)

---

## 1. Local Run & Deployment Instructions (3 Steps)
1. Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
2. Ensure the project is located at `C:\xampp\htdocs\lostfound` (or access via alias: `http://localhost/lostfound/`).
3. Open **`http://localhost/lostfound/install.php`** in your browser and click **"Create database and load test data"**.
   - This automatically creates the 8 normalized tables in MySQL and seeds 123 items, 44 users, 15 campus locations, and 10 categories.
4. Access the live prototype at: **`http://localhost/lostfound/`**.

*Configuration Note:* If you configured a MySQL password in your XAMPP installation, update `'pass'` in `config.php`. Default XAMPP credentials are user `root` with an empty password `""`.

---

## 2. Test Accounts for Every Role

| User Role | Institutional Email | Password | Access Capabilities |
|---|---|---|---|
| **Student** | `aigerim.akhmetov1@astanait.edu.kz` | `Student#2026` | Browse feed, filter categories/buildings, search keywords, post lost/found items with photo, send safe messages, mark items "Resolved", view in-app alerts. |
| **Administrator / Security** | `admin@astanait.edu.kz` | `Admin#2026` | Access `admin.php`, review incident reports, delete inappropriate posts, block malicious accounts, inspect audit trail (`admin_logs`), view live KPI analytics, generate 1-click SQL disaster recovery backup. |

---

## 3. Application Architecture & File Structure

| File | Subsystem & Functionality |
|---|---|
| `index.php` | Main feed with real-time category radio chips, campus building dropdowns, keyword search bar, responsive 12-item pagination. |
| `post.php` | Multi-field report creation form: Lost/Found toggle, category picker, 15 campus locations, client-side JS image preview, and 2 MB file guard. |
| `item.php` | Detailed listing view, in-app private message thread with post owner, community flagging modal, and "Mark as Resolved" control. |
| `my.php` | Personal student dashboard: active listings, resolved history, and unread notification alerts with pulsing badge. |
| `admin.php` | Campus Security Control Center: 4 tabs (Dashboard overview, Reports moderation, User blocking, Real-time Data Analytics). |
| `register.php`, `login.php`, `logout.php` | Authentication subsystem enforcing `@astanait.edu.kz` domain constraint, Bcrypt cost 12 hashing, CSRF checks, and IP rate-limiting. |
| `install.php` | Automated database installer and test data generator (123 items across 10 categories). |
| `schema.sql` | Relational 3NF DDL defining 8 normalized tables with foreign keys and referential integrity (`CASCADE` / `RESTRICT`). |

---

## 4. Defense-in-Depth Cybersecurity & Data Compliance
- **Bcrypt Password Encryption:** Passwords hashed with `PASSWORD_BCRYPT` (cost factor 12).
- **100% Prepared PDO Queries:** Every database interaction uses parameterised queries with bound parameters, completely preventing SQL Injection.
- **CSRF Protection:** State-altering forms include a 32-byte cryptographically secure token verified with `hash_equals()`.
- **IP Rate Limiting:** Login attempts are restricted to 10 requests per 15 minutes per IP address.
- **Strict Session Security:** Session cookies enforce `HttpOnly` and `SameSite=Lax` flags.
- **Kazakhstan Data Residency Compliance:** All personal data (emails, user IDs, private messages) is physically stored on local servers within the Republic of Kazakhstan pursuant to Article 12 of Law No. 94-V.

---

## 5. Database Disaster Recovery & Backup
- **In-App Backup:** Log in as Administrator -> open `admin.php?tab=dashboard` -> click **"Download database backup (.sql)"**.
- **Restore:** Open phpMyAdmin (`http://localhost/phpmyadmin/`), select `lostfound` database, and click **Import**.
- **Terminal CLI Command:**  
  `C:\xampp\mysql\bin\mysqldump.exe -u root lostfound > lostfound_backup.sql`
