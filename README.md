[Uploading inventory.js…]()
[login.js](https://github.com/user-attachments/files/28041571/login.js)
[DSpeedway_Capstone_Updated.docx9.docx(05) (3) (1).docx](https://github.com/user-attachments/files/28041586/DSpeedway_Capstone_Updated.docx9.docx.05.3.1.docx)
[Uploading login_system (1).sql…]()
D Speedway: Automotive Repair
A Smart Inventory and Parts Planning System
A web-based point-of-sale and business management system built for D Speedway Car Care Services, a motorcycle repair and parts shop. It handles daily operations from sales recording to inventory tracking, all in one place.



Purpose
This system replaces manual record-keeping with a centralized dashboard that lets the shop owner and managers record transactions, monitor stock, track expenses, and review financial performance — without needing any third-party software.

 Features
-Authentication — Secure login with role-based access (Owner / Manager), password reset via email link
- Daily Sales — Record parts and labor transactions per customer, with cash or GCash payment support and automatic receipt generation
- Inventory Management — Track product stock levels, restock items, and get low-stock/reorder alerts
- Expense Tracking — Log daily expenses by category, linked to the corresponding sale date
- Monthly Reports — Week-by-week financial breakdown showing gross sales, expenses, and net income
- Sales History — Browse, filter, and delete past transactions
- Demand Forecasting — 3-month moving average to predict reorder needs per product
- Audit Log — Tracks all create, update, and delete actions by user
- Database Backup — Owner can create, download, restore, and manage SQL backups
- User Management — Owner can add manager accounts, edit credentials, or remove users
- Profile Settings — Users can update their own name, email, and password

Tech Stack
- Backend:PHP, MySQL
- Frontend:HTML, CSS, Bootstrap , JS
- Libraries: SweetAlert2, Bootstrap Icons, Chart.js,

Roles
Owner   | Full access — includes user management, delete operations, backups, and audit logs 
Manager | Can record sales and expenses; cannot delete or manage users 
