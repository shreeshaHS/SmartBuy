<?php
/**
 * Simple route dispatcher
 * - All routes are called as: /public/?page=<route>
 */

function dispatch(string $route){
    $route = trim($route);
    if ($route === '' || $route === '/') $route = 'home';

    $map = [
        // Home / Store
        'home' => __DIR__.'/../app/views/store/home.php',
        'products' => __DIR__.'/../app/modules/shop/products.php',
        'product'  => __DIR__.'/../app/modules/shop/product.php',
        'cart'     => __DIR__.'/../app/modules/shop/cart.php',
        'checkout' => __DIR__.'/../app/modules/shop/checkout.php',

        // ✅ Order Success
        'order-success' => __DIR__.'/../app/modules/orders/order_success.php',

        // Auth
        'register'   => __DIR__.'/../app/modules/auth/register.php',
        'verify'     => __DIR__.'/../app/modules/auth/verify.php',
        'resend-otp' => __DIR__.'/../app/modules/auth/resend_otp.php',
        'login'      => __DIR__.'/../app/modules/auth/login.php',
        'logout'     => __DIR__.'/../app/modules/auth/logout.php',
        'forgot'     => __DIR__.'/../app/modules/auth/forgot.php',
        'reset'      => __DIR__.'/../app/modules/auth/reset.php',

        // Profile
        'profile'                => __DIR__.'/../app/modules/profile/index.php',
        'profile/complete'       => __DIR__.'/../app/modules/profile/complete.php',
        'profile/address'        => __DIR__.'/../app/modules/profile/address.php',
        'profile/address/delete' => __DIR__.'/../app/modules/profile/address_delete.php',
        'profile/become-seller'  => __DIR__.'/../app/modules/profile/become_seller.php',

        // Seller
        'seller/dashboard'   => __DIR__.'/../app/modules/seller/dashboard.php',
        'seller/add-product' => __DIR__.'/../app/modules/seller/add_product.php',
        'seller/orders'      => __DIR__.'/../app/modules/seller/order.php',
        'seller/order-view'  => __DIR__.'/../app/modules/seller/order_view.php',
        'seller/mark-packed' => __DIR__.'/../app/modules/seller/mark_packed.php',
        'seller/inventory'   => __DIR__.'/../app/modules/seller/inventory.php',

        // Admin
        'admin/install'   => __DIR__.'/../app/modules/admin/install.php',
        'admin/dashboard' => __DIR__.'/../app/modules/admin/dashboard.php',
'seller/inventory' => __DIR__ . '/../app/modules/seller/inventory.php',
        // Admin: Seller approval
        'admin/sellers/requests' => __DIR__.'/../app/modules/admin/sellers/request.php',
        'admin/sellers/view'     => __DIR__.'/../app/modules/admin/sellers/view_request.php',
        'admin/sellers/approve'  => __DIR__.'/../app/modules/admin/sellers/approve.php',
        'admin/sellers/reject'   => __DIR__.'/../app/modules/admin/sellers/reject.php',
'tools/import_pincode' => __DIR__.'/../app/tools/import_pincode.php',
        // Admin: Products moderation
        // Admin: Products moderation
'admin/products'         => __DIR__.'/../app/modules/admin/products/pending.php', // ✅ alias default
'admin/products/pending' => __DIR__.'/../app/modules/admin/products/pending.php',
'admin/products/live'    => __DIR__.'/../app/modules/admin/products/live.php',
'admin/products/approve' => __DIR__.'/../app/modules/admin/products/approve.php',
'admin/products/reject'  => __DIR__.'/../app/modules/admin/products/reject.php',
        // Admin: Catalog
        'admin/catalog/categories'    => __DIR__.'/../app/modules/admin/catalog/categories.php',
        'admin/catalog/subcategories' => __DIR__.'/../app/modules/admin/catalog/subcategories.php',
        'admin/catalog/spec-fields'   => __DIR__.'/../app/modules/admin/catalog/spec_fields.php',
        'admin/catalog/subcat-specs'  => __DIR__.'/../app/modules/admin/catalog/subcat_specs.php',

        // Admin: Delivery
        'admin/delivery/assign' => __DIR__.'/../app/modules/admin/delivery/assign.php',

        // Delivery
        'delivery/dashboard'  => __DIR__.'/../app/modules/delivery/dashboard.php',
        'delivery/orders'     => __DIR__.'/../app/modules/delivery/orders.php',
        'delivery/view'       => __DIR__.'/../app/modules/delivery/order_view.php',
        'delivery/pickup'     => __DIR__.'/../app/modules/delivery/pickup.php',
        'delivery/out'        => __DIR__.'/../app/modules/delivery/out_for_delivery.php',
        'delivery/deliver'    => __DIR__.'/../app/modules/delivery/deliver.php',
        'delivery/verify-otp' => __DIR__.'/../app/modules/delivery/verify_otp.php',

        // APIs
        'api/subcategories'         => __DIR__.'/../app/modules/api/subcategories.php',
        'api/subcat_specs'          => __DIR__.'/../app/modules/api/subcat_specs.php',
        'api/cart-add'              => __DIR__.'/../app/modules/api/cart_add.php',
        'api/cart-update'           => __DIR__.'/../app/modules/api/cart_update.php',
        'api/address-save'          => __DIR__.'/../app/modules/api/address_save.php',
        'api/place-order'           => __DIR__.'/../app/modules/api/place_order.php',
        'api/razorpay-create-order' => __DIR__.'/../app/modules/api/razorpay_create_order.php',
        'api/razorpay-verify'       => __DIR__.'/../app/modules/api/razorpay_verify.php',
        'api/razorpay-success'       => __DIR__.'/../app/modules/api/razorpay_success.php',

        // Account
        'account/orders'   => __DIR__.'/../app/modules/account/orders.php',
        'account/wishlist' => __DIR__.'/../app/modules/account/wishlist.php',
        'account/return'   => __DIR__.'/../app/modules/account/return.php',
        'orders'           => __DIR__.'/../app/modules/account/orders.php',
        'wishlist'         => __DIR__.'/../app/modules/account/wishlist.php',
        'return'           => __DIR__.'/../app/modules/account/return.php',

        // Admin: Marketing + Returns
        'admin/coupons'  => __DIR__.'/../app/modules/admin/marketing/coupons.php',
        'admin/reviews'  => __DIR__.'/../app/modules/admin/marketing/reviews.php',
        'admin/returns'  => __DIR__.'/../app/modules/admin/returns/manage.php',
'admin/orders'         => __DIR__.'/../app/modules/admin/orders.php',
'admin/order-view'     => __DIR__.'/../app/modules/admin/order_view.php',
        // Delivery return pickups
        'delivery/return_pickups' => __DIR__.'/../app/modules/delivery/returns_pickup.php',
'order-success' => __DIR__.'/../app/modules/orders/order_success.php',
        // API
        'api/wishlist-toggle' => __DIR__.'/../app/modules/api/wishlist-toggle.php',
        'profile/wishlist' => __DIR__.'/../app/modules/profile/wishlist.php',
        'api/apply_coupon'    => __DIR__.'/../app/modules/api/apply_coupon.php',
        'api/remove_coupon'   => __DIR__.'/../app/modules/api/remove_coupon.php',
       'api/set-pincode' => __DIR__.'/../app/modules/api/set_pincode.php',
        'api/review_submit'   => __DIR__.'/../app/modules/api/review_submit.php',
   'admin/delivery-create' => __DIR__.'/../app/modules/admin/delivery_create.php',
'admin/delivery-list'   => __DIR__.'/../app/modules/admin/delivery_list.php',
    'api/upload_seller_doc' => __DIR__.'/../app/modules/api/upload_seller_doc.php',
'api/upi-create'   => __DIR__.'/../app/modules/api/upi_create.php',
'api/upi-markpaid' => __DIR__.'/../app/modules/api/upi_markpaid.php',
];

    if (isset($map[$route])) {
        return require $map[$route];
    }

    // Pretty product URL: /?page=product/<slug>
    if (preg_match('#^product/([a-zA-Z0-9\-]+)$#', $route, $m)) {
        $_GET['slug'] = $m[1];
        return require __DIR__.'/../app/modules/shop/product.php';
    }

    http_response_code(404);
    require __DIR__ . '/../public/error/404.php';
}