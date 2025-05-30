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
- MySQL

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

2. 📲 Android Barcode Scanner Setup

    Install the TEC-IT Wireless Barcode Scanner APK:
    👉 Download APK

🧪 Usage

    📦 Scan an item → the app opens the web endpoint and registers the item

    📝 Use the web interface to:

        Add, edit, delete items

        View inventory

        Check stock levels


