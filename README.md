# Choithram Memorial Hospital: Community Management Platform

A PHP and MySQL web application where hospital patients can register, take part in health workshops,
learn from health resources, join a community forum and connect with mentors. Administrators manage
users, events and resources and see engagement reports.

Built for **DCOMP207 Web Programming (Final Project)**, Limkokwing University of Creative Technology, Sierra Leone.

## Group members
| Name | Student ID |
|---|---|
| Gershom Isaac Kingsambo | 905005306 |
| Samuel E.N. Sim Abu | 905004619 |
| Steven Amara | 905005480 |
| Alfreda E. John-Langba | 905004208 |
| Esther Matturie | 905004188 |

## Features
- Registration with health interests, login, logout, password hashing
- Member profile with picture upload, bio and change password
- Personalised dashboard (resources, upcoming events, learning progress, mentors)
- Events and workshops: register, cancel, track participation
- Learning resources library with progress tracking
- Community forum and mentorship requests
- Admin panel: users, events and attendance, resources, forum moderation, reports with CSV export

## Built with
PHP 8, MySQL, PDO, Bootstrap 5, Bootstrap Icons, Chart.js

## Run it locally (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\clinic`.
2. Start Apache and MySQL in XAMPP.
3. In phpMyAdmin create an empty database called `clinic_db`, select it, then **Import** `database/schema.sql`.
4. Copy `config.sample.php` to `config.php` and set the database details (XAMPP defaults: user `root`, empty password).
5. Open `http://localhost/clinic/`, sign up, then make your account an admin:
   `UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';`

## Notes
- The four sample mentors in `schema.sql` cannot log in (their passwords are invalid on purpose).
- Sample events, resources and forum posts are placeholder content for demonstration.
- Add `images/doctor1.jpg`, `images/doctor2.jpg` and `images/doctor3.jpg` and `logo.png` to match the design.
