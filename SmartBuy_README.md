# SmartBuy E-Commerce System — Full README

## Overview
SmartBuy is a multi-role eCommerce platform built with PHP + MySQL (XAMPP).
Supports Customer, Seller, Admin, and Delivery workflows with Email + OTP delivery.

---

## Features
- Customer shopping + wishlist
- Seller onboarding + inventory
- Admin moderation system
- Delivery boy OTP verification
- Email system (PHPMailer SMTP)
- Invoice-ready architecture

---

## Requirements
- XAMPP (Apache + MySQL)
- PHP 8+
- Composer NOT required
- PHPMailer included

---

## Installation

### 1. Copy Project
Place inside:
C:\xampp\htdocs\smartbuy2.O

### 2. Create Database
CREATE DATABASE smartbuy;

Import your SQL dump.

---

## Configuration

### BASE URL
app/config/bootstrap.php
define('BASE_URL', 'http://localhost/smartbuy2.O/public/');

---

## Email Setup (SMTP)

File:
app/config/mail.php

Use Gmail App Password.

Example:
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=your@gmail.com
MAIL_PASS=app_password
MAIL_FROM_NAME=Smart Buy

---

## Email Templates
Location:
app/views/emails/

Templates:
- otp.php
- password_reset_otp.php
- order_placed.php
- delivery_otp.php
- delivered.php

---

## Delivery OTP System

Flow:
Out for delivery → Send OTP → Customer shares OTP → Delivered

Columns required in orders table:
delivery_otp VARCHAR(10)
delivery_otp_expires DATETIME

---

## Roles

Customer:
- Shop products
- Wishlist
- Checkout
- OTP delivery confirmation

Seller:
- Upload products
- Manage inventory

Admin:
- Approve sellers/products
- Assign delivery

Delivery:
- Assigned orders
- Send OTP
- Verify delivery

---

## Routes
Defined in routes/web.php

Example:
'delivery/verify_otp' => app/modules/delivery/verify_otp.php

---

## Troubleshooting

Email not sending:
- Use Gmail App Password
- Enable SMTPDebug

Images not loading:
Check public/uploads permissions

Server errors:
Check DB columns match code

---

## Security
- CSRF protection
- Prepared statements
- OTP expiry
- Escaped output

---

## Production Tips
- Use HTTPS
- Move env secrets
- Disable SMTP debug
- Add logging

---

## Author Notes
Modular learning-grade architecture.
Expandable into full marketplace.

---

Enjoy building SmartBuy 🚀
