# 📦 Smart Inventory Management System

A lightweight and efficient Smart Inventory Management System using barcode scanning for real-time stock updates. Built using:

- ✅ TEC-IT Wireless Barcode Scanner (Android App)  
- 🌐 HTML, CSS, JavaScript (Frontend)  
- 🐘 PHP & MySQL (Backend)

---

## 🔧 Features

- 📲 Wireless barcode scanning via Android app  
- 📦 Add, update, and delete inventory items  
- 📈 Real-time stock level management  
- 🔍 Search and filter items  
- 🗃️ MySQL-based persistent storage  

---

## 📱 Requirements

- Android device with TEC-IT Wireless Barcode Scanner installed  
  [Download APK](https://www.tec-it.com/en/download/mobile-data-acquisition/wireless-barcode-scanner/Download.aspx)  
- Web server with PHP 7+ (e.g., XAMPP, LAMP)  
- MySQL or MariaDB  
- Modern Browser (Chrome / Firefox / Edge)  

---

## 🚀 Installation Guide

### 1. 📦 Backend Setup

1. **Clone** or **Download** this repository:  
   ```bash
   git clone https://github.com/your-repo/smart-inventory.git

    Move it into your server’s root directory (e.g., htdocs in XAMPP):

/xampp/htdocs/smart-inventory/

Import the database schema & sample data:

    Open phpMyAdmin.

    Select your MySQL server and click Import.

    Upload inventory.sql from the project folder.

Configure database credentials in config.php:

    <?php
    // config.php
    $host   = 'localhost';
    $user   = 'root';
    $pass   = '';
    $dbname = 'inventory';
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    if ($mysqli->connect_error) {
        die('DB Connection Error: ' . $mysqli->connect_error);
    }
    ?>

2. 📲 Android Barcode Scanner Setup

    Install the TEC-IT Wireless Barcode Scanner APK:
    👉 Download APK

    Configure the target URL:

        Open the app, go to Settings → Target Device.

        Choose Custom / Browser and set:

http://<your-server-ip>/smart-inventory/add_item.php?code=

Example for local network:

        http://192.168.0.100/smart-inventory/add_item.php?code=

🧪 Usage

    📦 Scan an item → the app opens the web endpoint and registers the item

    📝 Use the web interface to:

        Add, edit, delete items

        View inventory

        Check stock levels

📁 Folder Structure

smart-inventory/
├── add_item.php         # API endpoint for barcode input
├── config.php           # DB credentials
├── index.html           # Dashboard
├── style.css            # UI styling
├── script.js            # Client-side logic
├── inventory.sql        # MySQL DB schema + sample data

🛠️ Customization

Extend the system by:

    Adding user authentication

    Generating PDF reports

    Sending low-stock email alerts

    Role-based dashboards (admin, warehouse staff, etc.)

🧑‍💻 Author

Created by [Your Name]
Contact: [your-email@example.com]
GitHub: [github.com/yourusername]
