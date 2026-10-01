<?php
require_once __DIR__ . '/../../config/bootstrap.php';


$lockFile = __DIR__ . '/../../../storage/installed.lock';
if (file_exists($lockFile)) {
    echo "<h2>✅ Already installed</h2>";
    echo "<p>To reinstall delete: <b>storage/installed.lock</b></p>";
    exit;
}

echo "<pre style='font-family:ui-monospace,Consolas;padding:16px'>";

try {
    // SETTINGS
    db_exec("CREATE TABLE IF NOT EXISTS settings (
        skey VARCHAR(80) PRIMARY KEY,
        svalue TEXT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // USERS (email PK)
    db_exec("CREATE TABLE IF NOT EXISTS users (
        email VARCHAR(190) PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('user','seller','admin','delivery') DEFAULT 'user',
        status TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // PROFILE
    db_exec("CREATE TABLE IF NOT EXISTS user_profiles (
        email VARCHAR(190) PRIMARY KEY,
        full_name VARCHAR(160) NULL,
        gender ENUM('male','female','other') NULL,
        dob DATE NULL,
        avatar VARCHAR(255) NULL,
        is_profile_complete TINYINT DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_profile_user FOREIGN KEY (email)
            REFERENCES users(email) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ADDRESSES
    db_exec("CREATE TABLE IF NOT EXISTS user_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(190) NOT NULL,
        name VARCHAR(160) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        line1 VARCHAR(200) NOT NULL,
        line2 VARCHAR(200) NULL,
        landmark VARCHAR(120) NULL,
        city VARCHAR(120) NOT NULL,
        state VARCHAR(120) NOT NULL,
        pincode VARCHAR(10) NOT NULL,
        address_type ENUM('home','work','other') DEFAULT 'home',
        is_default TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_email),
        CONSTRAINT fk_addr_user FOREIGN KEY (user_email)
            REFERENCES users(email) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // PENDING REGISTRATION (OTP)
    db_exec("CREATE TABLE IF NOT EXISTS pending_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) UNIQUE NOT NULL,
        name VARCHAR(120) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        otp_expires_at DATETIME NOT NULL,
        attempts INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // FORGOT OTP
    db_exec("CREATE TABLE IF NOT EXISTS email_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL,
        purpose ENUM('reset_password') NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(email, purpose)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // SELLER REQUESTS
    db_exec("CREATE TABLE IF NOT EXISTS seller_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL,
        business_name VARCHAR(160) NOT NULL,
        gstin VARCHAR(30) NULL,
        pan VARCHAR(30) NULL,
        address TEXT NOT NULL,
        documents_json TEXT NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        admin_note TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(email),
        INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // CATEGORIES
    db_exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        slug VARCHAR(140) UNIQUE NOT NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // SUBCATEGORIES
    db_exec("CREATE TABLE IF NOT EXISTS subcategories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(120) NOT NULL,
        slug VARCHAR(140) UNIQUE NOT NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        INDEX(category_id),
        CONSTRAINT fk_sub_cat FOREIGN KEY (category_id)
            REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // SPEC TEMPLATES
    db_exec("CREATE TABLE IF NOT EXISTS spec_fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subcategory_id INT NOT NULL,
        label VARCHAR(120) NOT NULL,
        field_key VARCHAR(120) NOT NULL,
        type ENUM('text','number','select','multiselect','boolean','textarea') DEFAULT 'text',
        is_required TINYINT DEFAULT 1,
        unit VARCHAR(20) NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        INDEX(subcategory_id),
        CONSTRAINT fk_spec_sub FOREIGN KEY (subcategory_id)
            REFERENCES subcategories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    db_exec("CREATE TABLE IF NOT EXISTS spec_field_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        spec_field_id INT NOT NULL,
        value VARCHAR(120) NOT NULL,
        sort_order INT DEFAULT 0,
        INDEX(spec_field_id),
        CONSTRAINT fk_opt_field FOREIGN KEY (spec_field_id)
            REFERENCES spec_fields(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // PRODUCTS
    db_exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_email VARCHAR(190) NOT NULL,
        category_id INT NOT NULL,
        subcategory_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        slug VARCHAR(220) UNIQUE NOT NULL,
        brand VARCHAR(120) NULL,
        description TEXT NULL,
        mrp DECIMAL(10,2) DEFAULT 0,
        price DECIMAL(10,2) DEFAULT 0,
        stock INT DEFAULT 0,
        images_json TEXT NULL,
        status ENUM('pending','live','rejected') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(seller_email),
        INDEX(category_id),
        INDEX(subcategory_id),
        INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    db_exec("CREATE TABLE IF NOT EXISTS product_specs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        spec_field_id INT NOT NULL,
        value TEXT NOT NULL,
        INDEX(product_id),
        CONSTRAINT fk_ps_prod FOREIGN KEY (product_id)
            REFERENCES products(id) ON DELETE CASCADE,
        CONSTRAINT fk_ps_field FOREIGN KEY (spec_field_id)
            REFERENCES spec_fields(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ORDERS
    db_exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(190) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        shipping_fee DECIMAL(10,2) DEFAULT 0,
        grand_total DECIMAL(10,2) NOT NULL,
        payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
        order_status ENUM('placed','packed','shipped','out_for_delivery','delivered','failed') DEFAULT 'placed',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_email),
        INDEX(payment_status),
        INDEX(order_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    db_exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        seller_email VARCHAR(190) NOT NULL,
        qty INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        status ENUM('placed','packed','shipped','delivered','returned') DEFAULT 'placed',
        INDEX(order_id),
        CONSTRAINT fk_oi_order FOREIGN KEY (order_id)
            REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // PAYMENTS
    db_exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        provider ENUM('razorpay','cod') NOT NULL,
        provider_order_id VARCHAR(80) NULL,
        provider_payment_id VARCHAR(80) NULL,
        status ENUM('created','paid','failed','refunded') NOT NULL DEFAULT 'created',
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(order_id),
        INDEX(provider_order_id),
        CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // --------- SEED 20 + 60 ----------
    function slugify($s){
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }

    $categories = [
        "Electronics","Mobiles","Computers","Home & Kitchen","Fashion","Beauty & Personal Care",
        "Footwear","Sports & Fitness","Books","Toys & Baby","Appliances",
        "Furniture","Automotive","Jewellery","Watches","Health","Music","Gaming","Travel & Luggage"
    ];

    $count = (int)db_query("SELECT COUNT(*) c FROM categories")->fetch_assoc()['c'];
    if ($count === 0) {
        $stmt = db_prepare("INSERT INTO categories(name, slug, sort_order) VALUES (?,?,?)");
        foreach($categories as $i=>$name){
            $slug = slugify($name);
            $sort = $i+1;
            $stmt->bind_param("ssi", $name, $slug, $sort);
            $stmt->execute();
        }
        $stmt->close();
        echo "Seeded 20 categories ✅\n";
    } else echo "Categories already exist (skip)\n";

    $catMap = [];
    $res = db_query("SELECT id, slug FROM categories");
    while($r = $res->fetch_assoc()) $catMap[$r['slug']] = (int)$r['id'];

    $subSeed = [
        "electronics" => ["Televisions","Cameras","Accessories"],
        "mobiles" => ["Smartphones","Mobile Accessories","Tablets"],
        "computers" => ["Laptops","Desktops","Computer Accessories"],
        "home-kitchen" => ["Cookware","Home Decor","Storage & Organizers"],
        "fashion" => ["Men Clothing","Women Clothing","Kids Clothing"],
        "beauty-personal-care" => ["Makeup","Skincare","Haircare"],
        "footwear" => ["Men Footwear","Women Footwear","Kids Footwear"],
        
        "sports-fitness" => ["Gym Equipment","Outdoor Sports","Fitness Accessories"],
        "books" => ["Fiction","Education","Comics"],
        "toys-baby" => ["Toys","Baby Care","School Supplies"],
        "appliances" => ["Refrigerators","Washing Machines","Microwaves"],
        "furniture" => ["Sofas","Beds","Chairs"],
        "automotive" => ["Car Accessories","Bike Accessories","Oils & Fluids"],
        "jewellery" => ["Gold","Silver","Fashion Jewellery"],
        "watches" => ["Men Watches","Women Watches","Smart Watches"],
        "health" => ["Supplements","Medical Devices","Wellness"],
        "music" => ["Instruments","Audio Gear","Accessories"],
        "gaming" => ["Consoles","Games","Gaming Accessories"],
        "travel-luggage" => ["Trolleys","Backpacks","Travel Accessories"]
    ];

    $subCount = (int)db_query("SELECT COUNT(*) c FROM subcategories")->fetch_assoc()['c'];
    if ($subCount === 0) {
        $stmt = db_prepare("INSERT INTO subcategories(category_id, name, slug, sort_order) VALUES (?,?,?,?)");
        $sort = 1;
        foreach($subSeed as $catSlug => $subs){
            $catId = $catMap[$catSlug] ?? null;
            if(!$catId) continue;
            foreach($subs as $sname){
                $sslug = slugify($catSlug.'-'.$sname);
                $stmt->bind_param("issi", $catId, $sname, $sslug, $sort);
                $stmt->execute();
                $sort++;
            }
        }
        $stmt->close();
        echo "Seeded 60 subcategories ✅\n";
    } else echo "Subcategories already exist (skip)\n";

    // lock
    if (!is_dir(__DIR__ . '/../../../storage')) mkdir(__DIR__ . '/../../../storage', 0777, true);
    file_put_contents($lockFile, "installed=" . date('c'));

    echo "\n✅ INSTALL COMPLETED.\n";
    echo "Next: Step 2 OTP Auth.\n";

} catch(Exception $e){
    echo "\n❌ INSTALL FAILED:\n".$e->getMessage();
}

echo "</pre>";
