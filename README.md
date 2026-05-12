# GSN Fees Management System

A professional, robust, and efficient School Fees Management System built for **GS Nyagisozi**. This system is designed to handle student registrations, term-based fee tracking, academic journey logging, and multi-year financial reporting with a premium user interface.

## 🚀 Key Features

- **Professional Dashboard**: Real-time financial summary (Total Students, Collected Fees, and Outstanding Debt).
- **Smart Fee Logic**: Automatic handling of overpayments (remainder logic). Credits from Term 1 automatically apply to Term 2 and 3 within the same year.
- **Selective Class Promotion**: Move entire classes or specific students to the next academic level while preserving all historical payment data.
- **Academic Journey Tracking**: Every student has a professional profile showing their timeline from the day they joined to every class they've passed through.
- **Bulk Import**: Register hundreds of students instantly by uploading a CSV document.
- **Professional Printing**: One-click generation of printable Class Lists and individual Student Fee Slips.
- **Dynamic Year Ranges**: Support for professional academic years like `2025-2026` or `2026-2027`.
- **Public Student Portal**: Students can check their own payment status and print clearance slips using their Registration Number and Academic Year.
- **Data Integrity**: Built-in protection against duplicate registrations in the same class.

## 🛠️ Technology Stack

- **Backend**: PHP 8.x (using PDO for secure database interactions).
- **Database**: MySQL (optimized schema for high-speed reporting).
- **Frontend**: Modern HTML5, CSS3 (using Google Fonts "Outfit" for a premium look), and Vanilla Javascript.
- **Environment**: Optimized for XAMPP/LAMPP on Linux/Windows.

## 📦 Installation Guide

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/your-username/gsn-countable-management.git
   ```

2. **Move to Web Directory**:
   Move the project folder to your `htdocs` (XAMPP/LAMPP) directory.

3. **Database Setup**:
   - Open PHPMyAdmin.
   - Create a new database named `fess_management`.
   - Import the provided SQL structure or use the system to initialize tables.

4. **Configuration**:
   - Check `includes/db_connect.php` to ensure the database credentials match your local setup.

5. **Run**:
   Open your browser and go to `http://localhost/gsn-countable-management/`.

## 📖 How to Use

- **Admin Login**: Go to `/login.php` to access the administrative dashboard.
- **Manage Classes**: Use the "Manage Classes" menu to see collection rates per class and handle end-of-year promotions.
- **Bulk Registration**: In the "Add Student" section, download the CSV template and upload your student list for instant registration.
- **Settings**: Adjust the global Academic Year and Fee Structure (Primary/Secondary prices) in the Settings page.

## 📄 License
This project is built specifically for GS Nyagisozi and follows professional school management standards.
