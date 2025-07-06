<?php
/**
 * Plugin Name: Legal Document Generator Pro
 * Description: Complete subscription-based legal document generator with WooCommerce integration
 * Version: 4.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// ===== CONFIGURATION =====
// Replace these with your actual WooCommerce product IDs
define('LDG_PRO_PRODUCT_ID', 465);        // Replace with your Pro plan product ID
define('LDG_ENTERPRISE_PRODUCT_ID', 571); // Replace with your Enterprise plan product ID

// ===== ACTIVATION & DEACTIVATION HOOKS =====
register_activation_hook(__FILE__, 'ldg_activate_plugin');
register_deactivation_hook(__FILE__, 'ldg_deactivate_plugin');

function ldg_activate_plugin() {
    // Create subscription roles
    add_role('ldg_free_member', 'Free Member', array('read' => true));
    add_role('ldg_pro_member', 'Pro Member', array('read' => true));
    add_role('ldg_enterprise_member', 'Enterprise Member', array('read' => true));
    
    // Assign free role to existing users who don't have any LDG role
    $users = get_users();
    foreach ($users as $user) {
        if (!in_array('ldg_free_member', $user->roles) && 
            !in_array('ldg_pro_member', $user->roles) && 
            !in_array('ldg_enterprise_member', $user->roles)) {
            $user->add_role('ldg_free_member');
        }
    }
}

function ldg_deactivate_plugin() {
    // Remove subscription roles
    remove_role('ldg_free_member');
    remove_role('ldg_pro_member');
    remove_role('ldg_enterprise_member');
}

// ===== AUTO-ASSIGN FREE MEMBERSHIP TO NEW USERS =====
add_action('user_register', 'ldg_assign_free_membership');
function ldg_assign_free_membership($user_id) {
    $user = get_user_by('id', $user_id);
    $user->add_role('ldg_free_member');
    
    // Set default plan in meta
    update_user_meta($user_id, 'subscription_plan', 'free');
}

// ===== SUBSCRIPTION MANAGEMENT FUNCTIONS (WooCommerce Integrated) =====
function ldg_get_user_plan($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) return 'free';
    
    // Check for manual override first (for admin testing)
    $manual_plan = get_user_meta($user_id, 'ldg_manual_plan', true);
    if ($manual_plan) {
        return $manual_plan;
    }
    
    // Check cache (valid for 1 hour for performance)
    $cached_plan = get_user_meta($user_id, 'ldg_cached_plan', true);
    $last_check = get_user_meta($user_id, 'ldg_plan_last_check', true);
    
    if ($cached_plan && $last_check && (time() - $last_check) < 3600) {
        return $cached_plan;
    }
    
    // Check WooCommerce integration if available
    if (function_exists('wc_get_orders')) {
        
        // Method 1: Check WooCommerce Subscriptions (if you have the plugin)
        if (function_exists('wcs_get_users_subscriptions')) {
            $subscriptions = wcs_get_users_subscriptions($user_id);
            
            foreach ($subscriptions as $subscription) {
                if ($subscription->has_status(array('active', 'pending-cancel'))) {
                    foreach ($subscription->get_items() as $item) {
                        $product_id = $item->get_product_id();
                        
                        if ($product_id == LDG_ENTERPRISE_PRODUCT_ID) {
                            $plan = 'enterprise';
                            update_user_meta($user_id, 'ldg_cached_plan', $plan);
                            update_user_meta($user_id, 'ldg_plan_last_check', time());
                            return $plan;
                        }
                        if ($product_id == LDG_PRO_PRODUCT_ID) {
                            $plan = 'pro';
                            update_user_meta($user_id, 'ldg_cached_plan', $plan);
                            update_user_meta($user_id, 'ldg_plan_last_check', time());
                            return $plan;
                        }
                    }
                }
            }
        }
        
        // Method 2: Check recent completed orders (for simple products)
        $orders = wc_get_orders(array(
            'customer' => $user_id,
            'status' => array('completed', 'processing'),
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $highest_plan = 'free';
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                
                if ($product_id == LDG_ENTERPRISE_PRODUCT_ID) {
                    $highest_plan = 'enterprise';
                    break 2; // Break out of both loops
                }
                if ($product_id == LDG_PRO_PRODUCT_ID && $highest_plan !== 'enterprise') {
                    $highest_plan = 'pro';
                }
            }
        }
        
        // Cache the result
        update_user_meta($user_id, 'ldg_cached_plan', $highest_plan);
        update_user_meta($user_id, 'ldg_plan_last_check', time());
        
        return $highest_plan;
    }
    
    // Fallback: Check user roles (original method)
    $user = get_user_by('id', $user_id);
    
    if (in_array('ldg_enterprise_member', $user->roles)) {
        return 'enterprise';
    } elseif (in_array('ldg_pro_member', $user->roles)) {
        return 'pro';
    } elseif (in_array('ldg_free_member', $user->roles)) {
        return 'free';
    }
    
    return 'free'; // Default to free
}

function ldg_get_plan_limits($plan) {
    $limits = array(
        'free' => array(
            'downloads' => 2,
            'templates' => array('rental', 'employment')
        ),
        'pro' => array(
            'downloads' => 25,
            'templates' => array('rental', 'employment', 'nda', 'service', 'loan')
        ),
        'enterprise' => array(
            'downloads' => -1, // Unlimited
            'templates' => array('rental', 'employment', 'nda', 'service', 'loan', 'purchase')
        ),
        'none' => array(
            'downloads' => 0,
            'templates' => array()
        )
    );
    
    return isset($limits[$plan]) ? $limits[$plan] : $limits['none'];
}

function ldg_track_download($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) return false;
    
    $month_key = 'ldg_downloads_' . date('Y_m');
    $downloads = get_user_meta($user_id, $month_key, true);
    $downloads = $downloads ? intval($downloads) : 0;
    
    update_user_meta($user_id, $month_key, $downloads + 1);
    
    return true;
}

function ldg_get_remaining_downloads($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) return 0;
    
    $plan = ldg_get_user_plan($user_id);
    $limits = ldg_get_plan_limits($plan);
    
    if ($limits['downloads'] == -1) {
        return -1; // Unlimited
    }
    
    $month_key = 'ldg_downloads_' . date('Y_m');
    $used = get_user_meta($user_id, $month_key, true);
    $used = $used ? intval($used) : 0;
    
    return max(0, $limits['downloads'] - $used);
}

function ldg_can_access_template($template, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = ldg_get_user_plan($user_id);
    $limits = ldg_get_plan_limits($plan);
    
    return in_array($template, $limits['templates']);
}

// ===== WOOCOMMERCE INTEGRATION HOOKS =====

/**
 * When order is completed, upgrade user's plan
 */
add_action('woocommerce_order_status_completed', 'ldg_handle_order_completion');
add_action('woocommerce_order_status_processing', 'ldg_handle_order_completion'); // For digital products

function ldg_handle_order_completion($order_id) {
    if (!function_exists('wc_get_order')) return;
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $user_id = $order->get_user_id();
    if (!$user_id) return; // Guest checkout
    
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        
        if ($product_id == LDG_ENTERPRISE_PRODUCT_ID) {
            // Upgrade to Enterprise
            $user = get_user_by('id', $user_id);
            $user->remove_role('ldg_free_member');
            $user->remove_role('ldg_pro_member');
            $user->add_role('ldg_enterprise_member');
            
            update_user_meta($user_id, 'subscription_plan', 'enterprise');
            update_user_meta($user_id, 'ldg_plan_start_date', current_time('mysql'));
            
            // Clear cache
            delete_user_meta($user_id, 'ldg_cached_plan');
            
            // Send welcome email
            ldg_send_welcome_email($user_id, 'enterprise');
            
            // Log for debugging
            error_log('LDG: User ' . $user_id . ' upgraded to Enterprise via order ' . $order_id);
            
        } elseif ($product_id == LDG_PRO_PRODUCT_ID) {
            // Upgrade to Pro (only if not already Enterprise)
            $current_plan = ldg_get_user_plan($user_id);
            if ($current_plan !== 'enterprise') {
                $user = get_user_by('id', $user_id);
                $user->remove_role('ldg_free_member');
                $user->add_role('ldg_pro_member');
                
                update_user_meta($user_id, 'subscription_plan', 'pro');
                update_user_meta($user_id, 'ldg_plan_start_date', current_time('mysql'));
                
                // Clear cache
                delete_user_meta($user_id, 'ldg_cached_plan');
                
                // Send welcome email
                ldg_send_welcome_email($user_id, 'pro');
                
                // Log for debugging
                error_log('LDG: User ' . $user_id . ' upgraded to Pro via order ' . $order_id);
            }
        }
    }
}

/**
 * Handle subscription status changes (if using WooCommerce Subscriptions)
 */
if (function_exists('wcs_get_subscriptions')) {
    add_action('woocommerce_subscription_status_cancelled', 'ldg_handle_subscription_cancelled');
    add_action('woocommerce_subscription_status_expired', 'ldg_handle_subscription_cancelled');
    add_action('woocommerce_subscription_status_on-hold', 'ldg_handle_subscription_suspended');
    add_action('woocommerce_subscription_status_active', 'ldg_handle_subscription_reactivated');
}

function ldg_handle_subscription_cancelled($subscription) {
    $user_id = $subscription->get_user_id();
    
    // Downgrade to free plan
    $user = get_user_by('id', $user_id);
    $user->remove_role('ldg_pro_member');
    $user->remove_role('ldg_enterprise_member');
    $user->add_role('ldg_free_member');
    
    update_user_meta($user_id, 'subscription_plan', 'free');
    delete_user_meta($user_id, 'ldg_cached_plan');
    
    error_log('LDG: User ' . $user_id . ' subscription cancelled/expired');
}

function ldg_handle_subscription_suspended($subscription) {
    $user_id = $subscription->get_user_id();
    
    // Temporarily downgrade to free
    $user = get_user_by('id', $user_id);
    $user->remove_role('ldg_pro_member');
    $user->remove_role('ldg_enterprise_member');
    $user->add_role('ldg_free_member');
    
    update_user_meta($user_id, 'subscription_plan', 'free');
    update_user_meta($user_id, 'ldg_plan_suspended', true);
    delete_user_meta($user_id, 'ldg_cached_plan');
    
    error_log('LDG: User ' . $user_id . ' subscription suspended');
}

function ldg_handle_subscription_reactivated($subscription) {
    $user_id = $subscription->get_user_id();
    
    // Restore plan based on subscription product
    foreach ($subscription->get_items() as $item) {
        $product_id = $item->get_product_id();
        
        if ($product_id == LDG_ENTERPRISE_PRODUCT_ID) {
            $user = get_user_by('id', $user_id);
            $user->remove_role('ldg_free_member');
            $user->remove_role('ldg_pro_member');
            $user->add_role('ldg_enterprise_member');
            update_user_meta($user_id, 'subscription_plan', 'enterprise');
            
        } elseif ($product_id == LDG_PRO_PRODUCT_ID) {
            $user = get_user_by('id', $user_id);
            $user->remove_role('ldg_free_member');
            $user->add_role('ldg_pro_member');
            update_user_meta($user_id, 'subscription_plan', 'pro');
        }
    }
    
    delete_user_meta($user_id, 'ldg_plan_suspended');
    delete_user_meta($user_id, 'ldg_cached_plan');
    
    error_log('LDG: User ' . $user_id . ' subscription reactivated');
}

// ===== WELCOME EMAIL FUNCTION =====
function ldg_send_welcome_email($user_id, $plan) {
    $user = get_user_by('id', $user_id);
    if (!$user) return;
    
    $subject = sprintf('Welcome to %s Plan! 🎉', ucfirst($plan));
    
    $plan_benefits = array(
        'pro' => array(
            'downloads' => '25 downloads per month',
            'templates' => '5 document templates',
            'support' => 'Email support'
        ),
        'enterprise' => array(
            'downloads' => 'Unlimited downloads',
            'templates' => 'All 6 document templates',
            'support' => 'Priority support'
        )
    );
    
    $benefits = isset($plan_benefits[$plan]) ? $plan_benefits[$plan] : array();
    
    $message = "
    <h2>Welcome to Legal Document Generator {$plan} Plan!</h2>
    
    <p>Hi {$user->display_name},</p>
    
    <p>Thank you for upgrading! Your {$plan} plan is now active and includes:</p>
    
    <ul>
        <li>✅ {$benefits['downloads']}</li>
        <li>✅ {$benefits['templates']}</li>
        <li>✅ {$benefits['support']}</li>
    </ul>
    
    <p><a href='" . home_url('/legal-documents/') . "' style='background: #135eed; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 20px 0;'>Start Creating Documents</a></p>
    
    <p>If you have any questions, please don't hesitate to contact our support team.</p>
    
    <p>Best regards,<br>The Legal Documents Team</p>
    ";
    
    wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
}

// ===== AJAX HANDLERS =====
add_action('wp_ajax_ldg_track_download', 'ldg_ajax_track_download');

function ldg_ajax_track_download() {
    check_ajax_referer('ldg_download', 'nonce');
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('Not logged in');
    }
    
    $remaining = ldg_get_remaining_downloads($user_id);
    if ($remaining === 0) {
        wp_send_json_error('Download limit reached');
    }
    
    ldg_track_download($user_id);
    
    wp_send_json_success(array(
        'remaining' => ldg_get_remaining_downloads($user_id)
    ));
}

// ===== UPGRADE PROMPT FUNCTION =====
function ldg_render_upgrade_prompt($template) {
    $templates = array(
        'rental' => 'Rental Agreement',
        'employment' => 'Employment Contract',
        'nda' => 'NDA Agreement',
        'service' => 'Service Agreement',
        'loan' => 'Loan Agreement',
        'purchase' => 'Purchase Agreement',
    );
    
    $template_name = isset($templates[$template]) ? $templates[$template] : 'this document';
    
    // Get upgrade URLs
    $pro_url = function_exists('wc_get_checkout_url') ? 
        wc_get_checkout_url() . '?add-to-cart=' . LDG_PRO_PRODUCT_ID : 
        home_url('/pricing/');
        
    $enterprise_url = function_exists('wc_get_checkout_url') ? 
        wc_get_checkout_url() . '?add-to-cart=' . LDG_ENTERPRISE_PRODUCT_ID : 
        home_url('/pricing/');
    
    return '
    <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 12px; max-width: 600px; margin: 40px auto;">
        <div style="font-size: 72px; margin-bottom: 20px;">🔒</div>
        <h2 style="color: #333; margin-bottom: 20px;">Upgrade Required</h2>
        <p style="font-size: 18px; color: #666; margin-bottom: 30px;">
            Access to ' . $template_name . ' requires a Pro or Enterprise subscription.
        </p>
        <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
            <a href="' . $pro_url . '" style="background: #135eed; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">
                Upgrade to Pro ($19.99/mo)
            </a>
            <a href="' . $enterprise_url . '" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">
                Upgrade to Enterprise ($39.99/mo)
            </a>
        </div>
        <p style="margin-top: 20px;">
            <a href="/" style="color: #135eed;">← Back to Home</a>
        </p>
    </div>';
}

// ===== ENQUEUE STYLES AND SCRIPTS =====
add_action('wp_enqueue_scripts', 'ldg_enqueue_scripts');
function ldg_enqueue_scripts() {
    wp_enqueue_script('jquery');
}

// ===== MAIN SHORTCODE =====
add_shortcode('legal_documents', 'render_legal_documents');

function render_legal_documents($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<div class="ldg-login-prompt" style="text-align:center; padding:40px; background:#f8f9fa; border-radius:8px;">
            <h3>Please Login to Access Legal Documents</h3>
            <p>You need to be logged in to create legal documents.</p>
            <a href="' . wp_login_url(get_permalink()) . '" class="button" style="background:#135eed; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block; margin-top:10px;">Login Now</a>
        </div>';
    }
    
    // Shortcode attributes
    $atts = shortcode_atts(array(
        'templates' => 'rental,employment,nda,service',
        'template' => '',
        'plan_required' => '',
    ), $atts);
    
    $single_template = !empty($atts['template']);
    $user_id = get_current_user_id();
    $user_plan = ldg_get_user_plan($user_id);
    $remaining_downloads = ldg_get_remaining_downloads($user_id);
    
    // Check template access for single template mode
    if ($single_template && !ldg_can_access_template($atts['template'], $user_id)) {
        return ldg_render_upgrade_prompt($atts['template']);
    }
    
    ob_start();
    ?>
    <div id="legal-doc-generator">
        <style>
            /* Modern Container Styles */
            #legal-doc-generator {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                padding: 40px 20px;
                min-height: 100vh;
                position: relative;
                background: #f5f7fa;
            }
            
            .ldg-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                position: relative;
                z-index: 1;
            }
            
            /* Subscription Status Bar */
            .ldg-user-status {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 25px;
                border-radius: 12px;
                margin-bottom: 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            }
            
            .ldg-status-info {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .plan-badge {
                background: rgba(255,255,255,0.2);
                padding: 6px 16px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .plan-badge.free { background: #6c757d; }
            .plan-badge.pro { background: #28a745; }
            .plan-badge.enterprise { background: #6f42c1; }
            
            .ldg-downloads-remaining {
                font-size: 14px;
                opacity: 0.9;
            }
            
            .ldg-upgrade-btn {
                background: white;
                color: #667eea;
                padding: 10px 24px;
                border-radius: 25px;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            
            .ldg-upgrade-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.2);
                text-decoration: none;
                color: #667eea;
            }
            
            /* Header Section */
            .ldg-header {
                text-align: center;
                margin-bottom: 40px;
                position: relative;
            }
            
            .ldg-header::after {
                content: '';
                position: absolute;
                bottom: -10px;
                left: 50%;
                transform: translateX(-50%);
                width: 80px;
                height: 4px;
                background: #135eed;
                border-radius: 2px;
                animation: expandWidth 0.6s ease-out;
            }
            
            @keyframes expandWidth {
                from { width: 0; }
                to { width: 80px; }
            }
            
            .ldg-header h1 {
                font-size: 32px;
                color: #2c3e50;
                margin-bottom: 10px;
                font-weight: 600;
            }
            
            .ldg-header p {
                color: #6c757d;
                font-size: 18px;
            }
            
            /* Template Selector */
            .ldg-templates {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
                max-width: 1000px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .ldg-template-btn {
                background: white;
                border: 2px solid #e1e8ed;
                padding: 20px;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.3s ease;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.04);
                position: relative;
            }
            
            .ldg-template-btn:hover {
                border-color: #135eed;
                transform: translateY(-3px);
                box-shadow: 0 8px 16px rgba(19, 94, 237, 0.15);
            }
            
            .ldg-template-btn.active {
                background: linear-gradient(135deg, #135eed 0%, #0f4bc8 100%);
                color: white;
                border-color: transparent;
                transform: scale(1.05);
                box-shadow: 0 4px 15px rgba(19, 94, 237, 0.3);
            }
            
            .ldg-template-icon {
                font-size: 36px;
                display: block;
                margin-bottom: 10px;
            }
            
            .ldg-template-locked {
                position: relative;
                opacity: 0.6;
            }
            
            .ldg-template-locked::after {
                content: '🔒';
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 24px;
            }
            
            /* Main Content Container */
            .ldg-main-container {
                max-width: 1100px;
                margin: 0 auto;
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(19, 94, 237, 0.08);
                overflow: hidden;
                border: 1px solid rgba(19, 94, 237, 0.1);
                position: relative;
            }
            
            /* Grid Layout - TWO COLUMNS */
            .ldg-grid {
                display: grid;
                grid-template-columns: 380px 1fr;
                gap: 0;
                height: 660px;
            }
            
            /* Form Section - LEFT SIDE */
            .ldg-form {
                background: #f8f9fa;
                padding: 30px;
                overflow-y: auto;
                border-right: 1px solid #e1e8ed;
            }
            
            .ldg-form::-webkit-scrollbar {
                width: 6px;
            }
            
            .ldg-form::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 3px;
            }
            
            .ldg-form::-webkit-scrollbar-thumb {
                background: #135eed;
                border-radius: 3px;
            }
            
            .ldg-form h3 {
                font-size: 20px;
                margin-bottom: 25px;
                color: #135eed;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 10px;
                padding-bottom: 15px;
                border-bottom: 2px solid #e1e8ed;
            }
            
            .ldg-form h3::before {
                content: '📝';
                font-size: 24px;
            }
            
            .ldg-form-group {
                margin-bottom: 20px;
            }
            
            .ldg-form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #495057;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                position: relative;
                padding-left: 10px;
            }
            
            .ldg-form-group label::before {
                content: '';
                position: absolute;
                left: 0;
                top: 50%;
                transform: translateY(-50%);
                width: 3px;
                height: 12px;
                background: #135eed;
                border-radius: 3px;
            }
            
            .ldg-form-group input,
            .ldg-form-group textarea,
            .ldg-form-group select {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e1e8ed;
                border-radius: 8px;
                font-size: 15px;
                transition: all 0.3s ease;
                background: white;
                font-family: inherit;
                box-sizing: border-box;
            }
            
            .ldg-form-group input:focus,
            .ldg-form-group textarea:focus,
            .ldg-form-group select:focus {
                outline: none;
                border-color: #135eed;
                box-shadow: 0 0 0 3px rgba(19, 94, 237, 0.1);
            }
            
            .ldg-form-group textarea {
                resize: vertical;
                min-height: 80px;
            }
            
            /* Preview Section - RIGHT SIDE */
            .ldg-preview-container {
                display: flex;
                flex-direction: column;
                background: white;
            }
            
            .ldg-preview-header {
                background: #135eed;
                color: white;
                padding: 20px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #e1e8ed;
            }
            
            .ldg-preview-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .ldg-preview-header h3::before {
                content: '👁️';
            }
            
            .ldg-preview {
                flex: 1;
                padding: 40px 50px;
                overflow-y: auto;
                background: white;
                font-family: 'Times New Roman', Georgia, serif;
                font-size: 11pt;
                line-height: 1.5;
                color: #000;
            }
            
            /* Modern Buttons */
            .ldg-btn {
                padding: 12px 30px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                margin: 0 8px;
                font-size: 15px;
                font-weight: 600;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            
            .ldg-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
            
            .ldg-btn.primary {
                background: #135eed;
                color: white;
            }
            
            .ldg-btn.primary:hover {
                background: #0f4bc8;
                box-shadow: 0 5px 15px rgba(19, 94, 237, 0.3);
            }
            
            .ldg-btn.success {
                background: white;
                color: #135eed;
                border: 2px solid #135eed;
            }
            
            .ldg-btn.success:hover {
                background: #135eed;
                color: white;
                box-shadow: 0 5px 15px rgba(19, 94, 237, 0.3);
            }
            
            /* Document Styles */
            .ldg-doc-title {
                text-align: center;
                font-size: 16pt;
                font-weight: bold;
                margin-bottom: 20px;
                text-transform: uppercase;
                letter-spacing: 1px;
                color: #000;
                padding-bottom: 10px;
            }
            
            .ldg-section {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            .ldg-section h3 {
                font-size: 12pt;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #000;
                color: #000;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .ldg-field-value {
                border-bottom: 1px solid #000;
                display: inline-block;
                min-width: 150px;
                padding-bottom: 1px;
                font-weight: normal;
                color: #000;
            }
            
            /* Template Fields */
            .template-fields {
                display: none;
                animation: fadeIn 0.3s ease;
            }
            
            .template-fields.active {
                display: block;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            /* Upgrade Modal */
            .ldg-upgrade-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                z-index: 10000;
                display: none;
                align-items: center;
                justify-content: center;
            }
            
            .ldg-upgrade-content {
                background: white;
                padding: 40px;
                border-radius: 16px;
                max-width: 500px;
                width: 90%;
                text-align: center;
                position: relative;
            }
            
            .ldg-close-modal {
                position: absolute;
                top: 15px;
                right: 20px;
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #999;
            }
            
            .ldg-plans-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
                margin-top: 30px;
            }
            
            .ldg-plan-card {
                border: 2px solid #e1e8ed;
                padding: 20px;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s;
            }
            
            .ldg-plan-card:hover {
                border-color: #135eed;
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(19,94,237,0.2);
            }
            
            .ldg-plan-card.recommended {
                border-color: #135eed;
                position: relative;
            }
            
            .ldg-plan-card.recommended::before {
                content: 'RECOMMENDED';
                position: absolute;
                top: -10px;
                left: 50%;
                transform: translateX(-50%);
                background: #135eed;
                color: white;
                padding: 3px 15px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
            }
            
            /* Responsive Design */
            @media (max-width: 1024px) {
                .ldg-grid {
                    grid-template-columns: 1fr;
                    height: auto;
                }
                
                .ldg-form {
                    border-right: none;
                    border-bottom: 1px solid #e1e8ed;
                    max-height: 400px;
                }
                
                .ldg-preview-container {
                    height: 500px;
                }
            }
            
            @media (max-width: 640px) {
                .ldg-header h1 {
                    font-size: 24px;
                }
                
                .ldg-templates {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                }
                
                .ldg-user-status {
                    flex-direction: column;
                    gap: 15px;
                    text-align: center;
                }
            }
        </style>

        <div class="ldg-wrapper <?php echo $single_template ? 'single-template-mode' : ''; ?>">
            <!-- User Status Bar -->
            <div class="ldg-user-status">
                <div class="ldg-status-info">
                    <span class="plan-badge <?php echo $user_plan; ?>">
                        <?php echo ucfirst($user_plan); ?> Plan
                    </span>
                    <div class="ldg-downloads-remaining">
                        Downloads remaining this month: 
                        <?php echo $remaining_downloads == -1 ? 'Unlimited' : $remaining_downloads; ?>
                    </div>
                </div>
                <?php if ($user_plan !== 'enterprise'): ?>
                    <a href="<?php echo function_exists('wc_get_checkout_url') ? home_url('/pricing/') : '#'; ?>" class="ldg-upgrade-btn">
                        <?php echo $user_plan === 'free' ? '🚀 Upgrade Now' : '⬆️ Upgrade Plan'; ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (!$single_template): ?>
            <!-- Header -->
            <div class="ldg-header">
                <h1>Legal Document Generator</h1>
                <p>Create professional legal documents in minutes</p>
            </div>
            
            <!-- Template Selector -->
            <div class="ldg-templates">
                <?php
                $templates = array(
                    'rental' => array('icon' => '🏠', 'name' => 'Rental Agreement'),
                    'employment' => array('icon' => '💼', 'name' => 'Employment Contract'),
                    'nda' => array('icon' => '🔒', 'name' => 'NDA Agreement'),
                    'service' => array('icon' => '🛠️', 'name' => 'Service Agreement'),
                    'loan' => array('icon' => '💰', 'name' => 'Loan Agreement'),
                    'purchase' => array('icon' => '🛒', 'name' => 'Purchase Agreement'),
                );
                
                $allowed_templates = explode(',', $atts['templates']);
                foreach ($allowed_templates as $template_key) {
                    $template_key = trim($template_key);
                    if (isset($templates[$template_key])) {
                        $template = $templates[$template_key];
                        $can_access = ldg_can_access_template($template_key, $user_id);
                        ?>
                        <button class="ldg-template-btn <?php echo !$can_access ? 'ldg-template-locked' : ''; ?>" 
                                onclick="<?php echo $can_access ? "selectTemplate('" . $template_key . "')" : "showUpgradeModal()"; ?>">
                            <span class="ldg-template-icon"><?php echo $template['icon']; ?></span>
                            <div><?php echo $template['name']; ?></div>
                        </button>
                        <?php
                    }
                }
                ?>
            </div>
            <?php endif; ?>
            
            <!-- Main Container -->
            <div class="ldg-main-container">
                <div class="ldg-grid">
                    <!-- Form Section -->
                    <div class="ldg-form">
                        <h3>Document Details</h3>
                        
                        <!-- Rental Agreement Fields -->
                        <div id="rental-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Landlord Name</label>
                                <input type="text" class="ldg-input" data-field="landlord_name" placeholder="Enter full legal name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Landlord Address</label>
                                <textarea class="ldg-input" data-field="landlord_address" rows="2" placeholder="Street address, city, state, zip"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Tenant Name</label>
                                <input type="text" class="ldg-input" data-field="tenant_name" placeholder="Enter full legal name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Tenant Address</label>
                                <textarea class="ldg-input" data-field="tenant_address" rows="2" placeholder="Street address, city, state, zip"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Property Address</label>
                                <textarea class="ldg-input" data-field="property_address" rows="2" placeholder="Complete rental property address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Monthly Rent</label>
                                <input type="number" class="ldg-input" data-field="rent" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Security Deposit</label>
                                <input type="number" class="ldg-input" data-field="deposit" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Lease Start Date</label>
                                <input type="date" class="ldg-input" data-field="start_date">
                            </div>
                            <div class="ldg-form-group">
                                <label>Lease End Date</label>
                                <input type="date" class="ldg-input" data-field="end_date">
                            </div>
                        </div>
                        
                        <!-- Employment Contract Fields -->
                        <div id="employment-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Employer Name</label>
                                <input type="text" class="ldg-input" data-field="employer_name" placeholder="Company name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Employer Address</label>
                                <textarea class="ldg-input" data-field="employer_address" rows="2" placeholder="Company address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Employee Name</label>
                                <input type="text" class="ldg-input" data-field="employee_name" placeholder="Full legal name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Employee Address</label>
                                <textarea class="ldg-input" data-field="employee_address" rows="2" placeholder="Home address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Job Title</label>
                                <input type="text" class="ldg-input" data-field="job_title" placeholder="Position title">
                            </div>
                            <div class="ldg-form-group">
                                <label>Department</label>
                                <input type="text" class="ldg-input" data-field="department" placeholder="Department name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Annual Salary</label>
                                <input type="number" class="ldg-input" data-field="salary" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Start Date</label>
                                <input type="date" class="ldg-input" data-field="emp_start_date">
                            </div>
                            <div class="ldg-form-group">
                                <label>Working Hours</label>
                                <input type="text" class="ldg-input" data-field="working_hours" placeholder="e.g., 9:00 AM - 5:00 PM">
                            </div>
                            <div class="ldg-form-group">
                                <label>Vacation Days</label>
                                <input type="number" class="ldg-input" data-field="vacation_days" placeholder="Annual vacation days">
                            </div>
                        </div>
                        
                        <!-- NDA Agreement Fields -->
                        <div id="nda-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Disclosing Party Name</label>
                                <input type="text" class="ldg-input" data-field="disclosing_party" placeholder="Party sharing information">
                            </div>
                            <div class="ldg-form-group">
                                <label>Disclosing Party Address</label>
                                <textarea class="ldg-input" data-field="disclosing_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Receiving Party Name</label>
                                <input type="text" class="ldg-input" data-field="receiving_party" placeholder="Party receiving information">
                            </div>
                            <div class="ldg-form-group">
                                <label>Receiving Party Address</label>
                                <textarea class="ldg-input" data-field="receiving_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Purpose of Disclosure</label>
                                <textarea class="ldg-input" data-field="nda_purpose" rows="3" placeholder="Describe the purpose of sharing confidential information"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Confidentiality Period (Years)</label>
                                <input type="number" class="ldg-input" data-field="nda_period" min="1" value="2">
                            </div>
                            <div class="ldg-form-group">
                                <label>Effective Date</label>
                                <input type="date" class="ldg-input" data-field="nda_date">
                            </div>
                        </div>
                        
                        <!-- Service Agreement Fields -->
                        <div id="service-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Service Provider Name</label>
                                <input type="text" class="ldg-input" data-field="provider_name" placeholder="Provider name or company">
                            </div>
                            <div class="ldg-form-group">
                                <label>Provider Address</label>
                                <textarea class="ldg-input" data-field="provider_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Client Name</label>
                                <input type="text" class="ldg-input" data-field="client_name" placeholder="Client name or company">
                            </div>
                            <div class="ldg-form-group">
                                <label>Client Address</label>
                                <textarea class="ldg-input" data-field="client_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Services Description</label>
                                <textarea class="ldg-input" data-field="services_desc" rows="4" placeholder="Detailed description of services to be provided"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Service Fee</label>
                                <input type="number" class="ldg-input" data-field="service_fee" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Payment Terms</label>
                                <select class="ldg-input" data-field="payment_terms">
                                    <option value="">Select payment terms...</option>
                                    <option value="Net 30">Net 30 Days</option>
                                    <option value="Net 15">Net 15 Days</option>
                                    <option value="Upon Receipt">Due Upon Receipt</option>
                                    <option value="50% Upfront">50% Upfront, 50% on Completion</option>
                                </select>
                            </div>
                            <div class="ldg-form-group">
                                <label>Start Date</label>
                                <input type="date" class="ldg-input" data-field="service_start">
                            </div>
                            <div class="ldg-form-group">
                                <label>End Date</label>
                                <input type="date" class="ldg-input" data-field="service_end">
                            </div>
                        </div>
                        
                        <!-- Loan Agreement Fields -->
                        <div id="loan-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Lender Name</label>
                                <input type="text" class="ldg-input" data-field="lender_name" placeholder="Lender full name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Lender Address</label>
                                <textarea class="ldg-input" data-field="lender_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Borrower Name</label>
                                <input type="text" class="ldg-input" data-field="borrower_name" placeholder="Borrower full name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Borrower Address</label>
                                <textarea class="ldg-input" data-field="borrower_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Loan Amount</label>
                                <input type="number" class="ldg-input" data-field="loan_amount" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Interest Rate (%)</label>
                                <input type="number" class="ldg-input" data-field="interest_rate" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Loan Term (Months)</label>
                                <input type="number" class="ldg-input" data-field="loan_term" placeholder="12">
                            </div>
                            <div class="ldg-form-group">
                                <label>First Payment Date</label>
                                <input type="date" class="ldg-input" data-field="first_payment">
                            </div>
                        </div>
                        
                        <!-- Purchase Agreement Fields -->
                        <div id="purchase-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Seller Name</label>
                                <input type="text" class="ldg-input" data-field="seller_name" placeholder="Seller full name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Seller Address</label>
                                <textarea class="ldg-input" data-field="seller_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Buyer Name</label>
                                <input type="text" class="ldg-input" data-field="buyer_name" placeholder="Buyer full name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Buyer Address</label>
                                <textarea class="ldg-input" data-field="buyer_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Item Description</label>
                                <textarea class="ldg-input" data-field="item_description" rows="3" placeholder="Detailed description of item being sold"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Purchase Price</label>
                                <input type="number" class="ldg-input" data-field="purchase_price" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Deposit Amount</label>
                                <input type="number" class="ldg-input" data-field="purchase_deposit" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Closing Date</label>
                                <input type="date" class="ldg-input" data-field="closing_date">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="ldg-preview-container">
                        <div class="ldg-preview-header">
                            <h3>Document Preview</h3>
                            <div>
                                <button class="ldg-btn primary" onclick="ldgRefreshPreview()">Refresh</button>
                                <button class="ldg-btn success" onclick="ldgDownloadPDF()">Download as PDF</button>
                            </div>
                        </div>
                        <div class="ldg-preview" id="ldg-preview">
                            <?php if ($single_template): ?>
                            <!-- Content will be loaded by JavaScript -->
                            <?php else: ?>
                            <div style="text-align: center; color: #666; padding: 100px 20px;">
                                <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;">📄</div>
                                <h3 style="color: #333; font-weight: 500; font-size: 20px;">Select a Template to Begin</h3>
                                <p style="font-size: 14px; margin-top: 10px; color: #666;">Choose a document type from the options above to start creating your legal document.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upgrade Modal -->
        <div class="ldg-upgrade-modal" id="upgradeModal">
            <div class="ldg-upgrade-content">
                <span class="ldg-close-modal" onclick="closeUpgradeModal()">×</span>
                <h2>🚀 Upgrade Your Plan</h2>
                <p>Unlock more templates and downloads!</p>
                
                <div class="ldg-plans-grid">
                    <div class="ldg-plan-card recommended" onclick="upgradeToPlan('pro')">
                        <h3>Pro Plan</h3>
                        <div style="font-size: 28px; color: #135eed; font-weight: bold;">$19.99</div>
                        <div style="color: #666; margin-bottom: 15px;">per month</div>
                        <ul style="text-align: left; list-style: none; padding: 0;">
                            <li>✓ 25 downloads/month</li>
                            <li>✓ 5 document types</li>
                            <li>✓ Email support</li>
                        </ul>
                    </div>
                    
                    <div class="ldg-plan-card" onclick="upgradeToPlan('enterprise')">
                        <h3>Enterprise</h3>
                        <div style="font-size: 28px; color: #28a745; font-weight: bold;">$39.99</div>
                        <div style="color: #666; margin-bottom: 15px;">per month</div>
                        <ul style="text-align: left; list-style: none; padding: 0;">
                            <li>✓ Unlimited downloads</li>
                            <li>✓ All document types</li>
                            <li>✓ Priority support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    var currentTemplate = <?php echo $single_template ? "'" . $atts['template'] . "'" : 'null'; ?>;
    var documentData = {};
    var singleTemplateMode = <?php echo $single_template ? 'true' : 'false'; ?>;
    var userPlan = '<?php echo $user_plan; ?>';
    var remainingDownloads = <?php echo $remaining_downloads; ?>;
    
    jQuery(document).ready(function($) {
        // Auto-update on input
        $(document).on('input change', '.ldg-input', function() {
            var field = $(this).data('field');
            documentData[field] = $(this).val();
            ldgUpdatePreview();
        });
        
        // Auto-load template if in single template mode
        if (singleTemplateMode && currentTemplate) {
            document.querySelectorAll('.template-fields').forEach(fields => {
                fields.classList.remove('active');
            });
            
            var templateFields = document.getElementById(currentTemplate + '-fields');
            if (templateFields) {
                templateFields.classList.add('active');
            }
            
            ldgUpdatePreview();
        }
    });
    
    function showUpgradeModal() {
        document.getElementById('upgradeModal').style.display = 'flex';
    }
    
    function closeUpgradeModal() {
        document.getElementById('upgradeModal').style.display = 'none';
    }
    
    function upgradeToPlan(plan) {
        <?php if (function_exists('wc_get_checkout_url')): ?>
        var urls = {
            'pro': '<?php echo wc_get_checkout_url() . "?add-to-cart=" . LDG_PRO_PRODUCT_ID; ?>',
            'enterprise': '<?php echo wc_get_checkout_url() . "?add-to-cart=" . LDG_ENTERPRISE_PRODUCT_ID; ?>'
        };
        window.location.href = urls[plan];
        <?php else: ?>
        window.location.href = '/pricing/';
        <?php endif; ?>
    }

    function selectTemplate(templateId) {
        if (singleTemplateMode) return;
        
        currentTemplate = templateId;
        
        document.querySelectorAll('.ldg-template-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.closest('.ldg-template-btn').classList.add('active');
        
        document.querySelectorAll('.template-fields').forEach(fields => {
            fields.classList.remove('active');
        });
        
        var templateFields = document.getElementById(templateId + '-fields');
        if (templateFields) {
            templateFields.classList.add('active');
        }
        
        documentData = {};
        document.querySelectorAll('.ldg-input').forEach(input => {
            input.value = '';
        });
        
        ldgUpdatePreview();
        
        document.querySelector('.ldg-main-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function ldgUpdatePreview() {
        if (!currentTemplate) return;
        
        var previewHTML = '';
        
        switch(currentTemplate) {
            case 'rental':
                previewHTML = generateRentalAgreement();
                break;
            case 'employment':
                previewHTML = generateEmploymentContract();
                break;
            case 'nda':
                previewHTML = generateNDAgreement();
                break;
            case 'service':
                previewHTML = generateServiceAgreement();
                break;
            case 'loan':
                previewHTML = generateLoanAgreement();
                break;
            case 'purchase':
                previewHTML = generatePurchaseAgreement();
                break;
        }
        
        document.getElementById('ldg-preview').innerHTML = previewHTML;
    }

    function generateRentalAgreement() {
        return `
            <div class="ldg-doc-title">RESIDENTIAL LEASE AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 30px;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES TO THIS AGREEMENT</h3>
                <p><strong>LANDLORD:</strong><br>
                <span class="ldg-field-value">${documentData.landlord_name || '[Landlord Full Legal Name]'}</span><br>
                <span class="ldg-field-value">${documentData.landlord_address || '[Landlord Complete Address]'}</span></p>
                
                <p style="margin-top: 20px;"><strong>TENANT:</strong><br>
                <span class="ldg-field-value">${documentData.tenant_name || '[Tenant Full Legal Name]'}</span><br>
                <span class="ldg-field-value">${documentData.tenant_address || '[Tenant Complete Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. PROPERTY DETAILS</h3>
                <p>The Landlord agrees to rent to the Tenant the property located at:</p>
                <p style="margin-left: 20px;"><span class="ldg-field-value">${documentData.property_address || '[Complete Property Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>3. LEASE TERMS</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px 0;"><strong>Monthly Rent:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.rent ? parseFloat(documentData.rent).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>Security Deposit:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.deposit ? parseFloat(documentData.deposit).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>Lease Start Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.start_date || '[Start Date]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>Lease End Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.end_date || '[End Date]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>4. TERMS AND CONDITIONS</h3>
                <ol style="line-height: 2;">
                    <li>Rent is due on the 1st day of each month</li>
                    <li>Late payment fee of $50.00 will be charged after the 5th day</li>
                    <li>Tenant is responsible for all utilities unless otherwise specified</li>
                    <li>No smoking is permitted on the premises</li>
                    <li>No pets allowed without prior written consent from Landlord</li>
                    <li>Tenant must give 30 days written notice before vacating</li>
                    <li>Property must be returned in the same condition as received</li>
                </ol>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateEmploymentContract() {
        return `
            <div class="ldg-doc-title">EMPLOYMENT CONTRACT</div>
            
            <p style="text-align: center; margin-bottom: 20px; font-size: 11pt;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>EMPLOYER:</strong><br>
                <span class="ldg-field-value">${documentData.employer_name || '[Company Name]'}</span><br>
                <span class="ldg-field-value">${documentData.employer_address || '[Company Address]'}</span></p>
                
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>EMPLOYEE:</strong><br>
                <span class="ldg-field-value">${documentData.employee_name || '[Employee Full Name]'}</span><br>
                <span class="ldg-field-value">${documentData.employee_address || '[Employee Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. POSITION DETAILS</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                    <tr>
                        <td style="padding: 5px 0; width: 200px;"><strong>Job Title:</strong></td>
                        <td><span class="ldg-field-value">${documentData.job_title || '[Position Title]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Department:</strong></td>
                        <td><span class="ldg-field-value">${documentData.department || '[Department]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Start Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.emp_start_date || '[Start Date]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Working Hours:</strong></td>
                        <td><span class="ldg-field-value">${documentData.working_hours || '[Hours]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>3. COMPENSATION & BENEFITS</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                    <tr>
                        <td style="padding: 5px 0; width: 200px;"><strong>Annual Salary:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.salary ? parseFloat(documentData.salary).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Vacation Days:</strong></td>
                        <td><span class="ldg-field-value">${documentData.vacation_days || '[Number]'}</span> days per year</td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>4. TERMS OF EMPLOYMENT</h3>
                <ol style="font-size: 11pt; line-height: 1.6; margin-left: 20px;">
                    <li>This is an at-will employment relationship</li>
                    <li>Employee must maintain confidentiality of company information</li>
                    <li>Employee is eligible for company benefits after 90 days</li>
                    <li>Performance reviews will be conducted annually</li>
                    <li>Two weeks notice is required for resignation</li>
                </ol>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateNDAgreement() {
        return `
            <div class="ldg-doc-title">NON-DISCLOSURE AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 20px; font-size: 11pt;">Effective Date: <strong>${documentData.nda_date || new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>DISCLOSING PARTY:</strong><br>
                <span class="ldg-field-value">${documentData.disclosing_party || '[Disclosing Party Name]'}</span><br>
                <span class="ldg-field-value">${documentData.disclosing_address || '[Address]'}</span></p>
                
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>RECEIVING PARTY:</strong><br>
                <span class="ldg-field-value">${documentData.receiving_party || '[Receiving Party Name]'}</span><br>
                <span class="ldg-field-value">${documentData.receiving_address || '[Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. PURPOSE</h3>
                <p style="font-size: 11pt;">The purpose of this Agreement is to prevent the unauthorized disclosure of Confidential Information as defined below. The parties intend to enter into discussions regarding:</p>
                <p style="margin: 15px; padding: 10px; background: #f8f9fa; border-left: 3px solid #000; font-size: 11pt;">
                    ${documentData.nda_purpose || '[Purpose of disclosure]'}
                </p>
            </div>
            
            <div class="ldg-section">
                <h3>3. CONFIDENTIAL INFORMATION</h3>
                <p style="font-size: 11pt;">For purposes of this Agreement, "Confidential Information" means all information or material that has or could have commercial value or other utility in the business in which Disclosing Party is engaged.</p>
            </div>
            
            <div class="ldg-section">
                <h3>4. OBLIGATIONS</h3>
                <ol style="font-size: 11pt; line-height: 1.6; margin-left: 20px;">
                    <li>Receiving Party shall hold and maintain the Confidential Information in strict confidence</li>
                    <li>Receiving Party shall not disclose Confidential Information to third parties</li>
                    <li>Receiving Party shall not use Confidential Information for any purpose except to evaluate and engage in discussions concerning a potential business relationship</li>
                    <li>This Agreement shall remain in effect for <strong>${documentData.nda_period || '2'} years</strong></li>
                </ol>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateServiceAgreement() {
        return `
            <div class="ldg-doc-title">SERVICE AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 30px;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p><strong>SERVICE PROVIDER:</strong><br>
                <span class="ldg-field-value">${documentData.provider_name || '[Provider Name]'}</span><br>
                <span class="ldg-field-value">${documentData.provider_address || '[Provider Address]'}</span></p>
                
                <p style="margin-top: 20px;"><strong>CLIENT:</strong><br>
                <span class="ldg-field-value">${documentData.client_name || '[Client Name]'}</span><br>
                <span class="ldg-field-value">${documentData.client_address || '[Client Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. SERVICES TO BE PROVIDED</h3>
                <p style="margin: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #000;">
                    ${documentData.services_desc || '[Detailed description of services to be provided]'}
                </p>
            </div>
            
            <div class="ldg-section">
                <h3>3. COMPENSATION & PAYMENT TERMS</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px 0; width: 40%;"><strong>Service Fee:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.service_fee ? parseFloat(documentData.service_fee).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>Payment Terms:</strong></td>
                        <td><span class="ldg-field-value">${documentData.payment_terms || '[Payment Terms]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>4. PROJECT TIMELINE</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px 0; width: 40%;"><strong>Start Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.service_start || '[Start Date]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>End Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.service_end || '[End Date]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>5. TERMS AND CONDITIONS</h3>
                <ol style="line-height: 2;">
                    <li>Provider will perform services as an independent contractor</li>
                    <li>All work product shall become property of the Client upon payment</li>
                    <li>Provider warrants all work will be original and not infringe on any copyrights</li>
                    <li>Either party may terminate with 15 days written notice</li>
                </ol>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateLoanAgreement() {
        return `
            <div class="ldg-doc-title">LOAN AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 20px; font-size: 11pt;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>LENDER:</strong><br>
                <span class="ldg-field-value">${documentData.lender_name || '[Lender Name]'}</span><br>
                <span class="ldg-field-value">${documentData.lender_address || '[Lender Address]'}</span></p>
                
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>BORROWER:</strong><br>
                <span class="ldg-field-value">${documentData.borrower_name || '[Borrower Name]'}</span><br>
                <span class="ldg-field-value">${documentData.borrower_address || '[Borrower Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. LOAN TERMS</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                    <tr>
                        <td style="padding: 5px 0; width: 200px;"><strong>Loan Amount:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.loan_amount ? parseFloat(documentData.loan_amount).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Interest Rate:</strong></td>
                        <td><span class="ldg-field-value">${documentData.interest_rate || '[Rate]'}</span>% per annum</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Loan Term:</strong></td>
                        <td><span class="ldg-field-value">${documentData.loan_term || '[Term]'}</span> months</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Monthly Payment:</strong></td>
                        <td>$<span class="ldg-field-value">${calculateMonthlyPayment() || '[Calculate]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>First Payment Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.first_payment || '[Date]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>3. REPAYMENT</h3>
                <p style="font-size: 11pt;">Borrower agrees to repay the loan in equal monthly installments of principal and interest.</p>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generatePurchaseAgreement() {
        return `
            <div class="ldg-doc-title">PURCHASE AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 20px; font-size: 11pt;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>SELLER:</strong><br>
                <span class="ldg-field-value">${documentData.seller_name || '[Seller Name]'}</span><br>
                <span class="ldg-field-value">${documentData.seller_address || '[Seller Address]'}</span></p>
                
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>BUYER:</strong><br>
                <span class="ldg-field-value">${documentData.buyer_name || '[Buyer Name]'}</span><br>
                <span class="ldg-field-value">${documentData.buyer_address || '[Buyer Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. ITEM DESCRIPTION</h3>
                <p style="margin: 15px; padding: 10px; background: #f8f9fa; border-left: 3px solid #000; font-size: 11pt;">
                    ${documentData.item_description || '[Description of item/property being sold]'}
                </p>
            </div>
            
            <div class="ldg-section">
                <h3>3. PURCHASE TERMS</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                    <tr>
                        <td style="padding: 5px 0; width: 200px;"><strong>Purchase Price:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.purchase_price ? parseFloat(documentData.purchase_price).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Deposit Amount:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.purchase_deposit ? parseFloat(documentData.purchase_deposit).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Closing Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.closing_date || '[Date]'}</span></td>
                    </tr>
                </table>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateSignatureSection() {
        return `
            <div style="margin-top: 50px; page-break-inside: avoid;">
                <p style="text-align: center; margin-bottom: 30px; font-size: 11pt;"><strong>IN WITNESS WHEREOF, the parties have executed this Agreement as of the date first written above.</strong></p>
                
                <table style="width: 100%; margin-top: 40px; font-size: 11pt;">
                    <tr>
                        <td style="width: 45%; text-align: left;">
                            <div style="border-bottom: 1px solid #000; margin-bottom: 5px; height: 30px;"></div>
                            <p style="margin: 5px 0;">Signature</p>
                            <p style="margin: 5px 0;">Date: _________________</p>
                        </td>
                        <td style="width: 10%;"></td>
                        <td style="width: 45%; text-align: left;">
                            <div style="border-bottom: 1px solid #000; margin-bottom: 5px; height: 30px;"></div>
                            <p style="margin: 5px 0;">Signature</p>
                            <p style="margin: 5px 0;">Date: _________________</p>
                        </td>
                    </tr>
                </table>
            </div>
        `;
    }
    
    function calculateMonthlyPayment() {
        if (documentData.loan_amount && documentData.interest_rate && documentData.loan_term) {
            var principal = parseFloat(documentData.loan_amount);
            var rate = parseFloat(documentData.interest_rate) / 100 / 12;
            var term = parseInt(documentData.loan_term);
            
            if (rate === 0) {
                return (principal / term).toFixed(2);
            }
            
            var payment = principal * (rate * Math.pow(1 + rate, term)) / (Math.pow(1 + rate, term) - 1);
            return payment.toFixed(2);
        }
        return null;
    }
    
    function ldgRefreshPreview() {
        ldgUpdatePreview();
        document.getElementById('ldg-preview').style.opacity = '0.5';
        setTimeout(function() {
            document.getElementById('ldg-preview').style.opacity = '1';
        }, 300);
    }

    function ldgDownloadPDF() {
        if (!currentTemplate) {
            alert('Please select a template and fill in some details first.');
            return;
        }
        
        if (remainingDownloads === 0) {
            alert('You have reached your download limit for this month. Please upgrade your plan for more downloads.');
            showUpgradeModal();
            return;
        }
        
        // Track the download via AJAX
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'ldg_track_download',
            nonce: '<?php echo wp_create_nonce('ldg_download'); ?>'
        }, function(response) {
            if (response.success && remainingDownloads > 0) {
                remainingDownloads--;
                jQuery('.ldg-downloads-remaining').text('Downloads remaining this month: ' + 
                    (remainingDownloads == -1 ? 'Unlimited' : remainingDownloads));
            }
        });
        
        ldgUpdatePreview();
        
        var previewElement = document.getElementById('ldg-preview');
        var content = previewElement.innerHTML;
        
        var printHTML = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>${currentTemplate.charAt(0).toUpperCase() + currentTemplate.slice(1)}_Agreement</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Times New Roman', Georgia, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: black;
            background: white;
        }
        
        .ldg-doc-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .ldg-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .ldg-section h3 {
            font-size: 12pt;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid black;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .ldg-field-value {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 150px;
            padding-bottom: 1px;
        }
        
        p {
            margin-bottom: 8px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        td {
            padding: 8px 0;
            vertical-align: top;
        }
        
        ol {
            margin-left: 20px;
            padding-left: 0;
        }
        
        li {
            margin-bottom: 5px;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .ldg-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    ${content}
</body>
</html>`;

        var blob = new Blob([printHTML], {type: 'text/html'});
        var url = URL.createObjectURL(blob);
        
        var printWindow = window.open(url, '_blank');
        
        if (printWindow) {
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                }, 500);
            };
            
            setTimeout(function() {
                URL.revokeObjectURL(url);
            }, 10000);
            
            setTimeout(function() {
                alert('✅ Print page opened! If it doesn\'t auto-print, press Ctrl+P (or Cmd+P on Mac) in the new tab.');
            }, 1000);
        } else {
            alert('Popup blocked! Please allow popups for this site and try again.');
        }
    }
    </script>
    <?php
    return ob_get_clean();
}

// ===== PRICING PAGE SHORTCODE =====
add_shortcode('ldg_pricing_table', 'ldg_render_pricing_table');

function ldg_render_pricing_table($atts) {
    $atts = shortcode_atts(array(
        'style' => 'modern',
    ), $atts);
    
    $current_plan = is_user_logged_in() ? ldg_get_user_plan() : 'free';
    
    ob_start();
    ?>
    <div class="ldg-pricing-container">
        <style>
            .ldg-pricing-container {
                max-width: 1200px;
                margin: 40px auto;
                padding: 20px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .ldg-pricing-header {
                text-align: center;
                margin-bottom: 50px;
            }
            
            .ldg-pricing-header h2 {
                font-size: 36px;
                color: #2c3e50;
                margin-bottom: 15px;
            }
            
            .ldg-pricing-header p {
                font-size: 18px;
                color: #6c757d;
                max-width: 600px;
                margin: 0 auto;
            }
            
            .ldg-pricing-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 30px;
                margin-bottom: 40px;
            }
            
            .ldg-pricing-card {
                background: white;
                border: 2px solid #e1e8ed;
                border-radius: 16px;
                padding: 40px 30px;
                text-align: center;
                position: relative;
                transition: all 0.3s ease;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            }
            
            .ldg-pricing-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }
            
            .ldg-pricing-card.featured {
                border-color: #135eed;
                position: relative;
                transform: scale(1.05);
            }
            
            .ldg-pricing-card.featured::before {
                content: 'MOST POPULAR';
                position: absolute;
                top: -15px;
                left: 50%;
                transform: translateX(-50%);
                background: #135eed;
                color: white;
                padding: 8px 24px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                letter-spacing: 1px;
            }
            
            .ldg-pricing-card.current-plan {
                border-color: #28a745;
                background: #f8fff9;
            }
            
            .ldg-pricing-card.current-plan::after {
                content: 'CURRENT PLAN';
                position: absolute;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: bold;
            }
            
            .ldg-plan-name {
                font-size: 24px;
                font-weight: 600;
                margin-bottom: 10px;
                color: #2c3e50;
            }
            
            .ldg-plan-price {
                font-size: 48px;
                font-weight: 700;
                margin-bottom: 5px;
                color: #135eed;
            }
            
            .ldg-plan-period {
                color: #6c757d;
                margin-bottom: 30px;
                font-size: 16px;
            }
            
            .ldg-plan-features {
                list-style: none;
                padding: 0;
                margin: 30px 0;
                text-align: left;
            }
            
            .ldg-plan-features li {
                padding: 8px 0;
                display: flex;
                align-items: center;
                font-size: 16px;
            }
            
            .ldg-plan-features li::before {
                content: '✓';
                color: #28a745;
                font-weight: bold;
                margin-right: 12px;
                font-size: 18px;
            }
            
            .ldg-plan-button {
                width: 100%;
                padding: 16px 24px;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
            }
            
            .ldg-plan-button.primary {
                background: #135eed;
                color: white;
            }
            
            .ldg-plan-button.primary:hover {
                background: #0f4bc8;
                transform: translateY(-2px);
                text-decoration: none;
                color: white;
            }
            
            .ldg-plan-button.secondary {
                background: #f8f9fa;
                color: #6c757d;
                border: 2px solid #e1e8ed;
            }
            
            .ldg-plan-button.secondary:hover {
                background: #e9ecef;
                text-decoration: none;
                color: #6c757d;
            }
            
            .ldg-plan-button.current {
                background: #28a745;
                color: white;
                cursor: default;
            }
            
            .ldg-pricing-faq {
                margin-top: 60px;
                text-align: center;
            }
            
            .ldg-pricing-faq h3 {
                font-size: 24px;
                margin-bottom: 20px;
                color: #2c3e50;
            }
            
            .ldg-pricing-faq p {
                color: #6c757d;
                margin-bottom: 20px;
            }
            
            @media (max-width: 768px) {
                .ldg-pricing-grid {
                    grid-template-columns: 1fr;
                    gap: 20px;
                }
                
                .ldg-pricing-card.featured {
                    transform: none;
                }
                
                .ldg-plan-price {
                    font-size: 36px;
                }
            }
        </style>
        
        <div class="ldg-pricing-header">
            <h2>Choose Your Plan</h2>
            <p>Select the perfect plan for your legal document needs. All plans include professional templates and secure PDF downloads.</p>
        </div>
        
        <div class="ldg-pricing-grid">
            <!-- Free Plan -->
            <div class="ldg-pricing-card <?php echo $current_plan === 'free' ? 'current-plan' : ''; ?>">
                <div class="ldg-plan-name">Free</div>
                <div class="ldg-plan-price">$0</div>
                <div class="ldg-plan-period">forever</div>
                
                <ul class="ldg-plan-features">
                    <li>2 downloads per month</li>
                    <li>2 document templates</li>
                    <li>Basic email support</li>
                    <li>PDF downloads</li>
                </ul>
                
                <?php if ($current_plan === 'free'): ?>
                    <button class="ldg-plan-button current">Current Plan</button>
                <?php else: ?>
                    <a href="<?php echo home_url('/legal-documents/'); ?>" class="ldg-plan-button secondary">
                        Try Free Plan
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Pro Plan -->
            <div class="ldg-pricing-card featured <?php echo $current_plan === 'pro' ? 'current-plan' : ''; ?>">
                <div class="ldg-plan-name">Professional</div>
                <div class="ldg-plan-price">$19.99</div>
                <div class="ldg-plan-period">per month</div>
                
                <ul class="ldg-plan-features">
                    <li>25 downloads per month</li>
                    <li>5 document templates</li>
                    <li>Email support</li>
                    <li>PDF downloads</li>
                    <li>Priority queue</li>
                </ul>
                
                <?php if ($current_plan === 'pro'): ?>
                    <button class="ldg-plan-button current">Current Plan</button>
                <?php elseif (function_exists('wc_get_checkout_url')): ?>
                    <a href="<?php echo wc_get_checkout_url() . '?add-to-cart=' . LDG_PRO_PRODUCT_ID; ?>" class="ldg-plan-button primary">
                        Upgrade to Pro
                    </a>
                <?php else: ?>
                    <a href="#" onclick="alert('Please contact support to upgrade.')" class="ldg-plan-button primary">
                        Upgrade to Pro
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Enterprise Plan -->
            <div class="ldg-pricing-card <?php echo $current_plan === 'enterprise' ? 'current-plan' : ''; ?>">
                <div class="ldg-plan-name">Enterprise</div>
                <div class="ldg-plan-price">$39.99</div>
                <div class="ldg-plan-period">per month</div>
                
                <ul class="ldg-plan-features">
                    <li>Unlimited downloads</li>
                    <li>All 6 document templates</li>
                    <li>Priority support</li>
                    <li>Advanced features</li>
                    <li>API access</li>
                </ul>
                
                <?php if ($current_plan === 'enterprise'): ?>
                    <button class="ldg-plan-button current">Current Plan</button>
                <?php elseif (function_exists('wc_get_checkout_url')): ?>
                    <a href="<?php echo wc_get_checkout_url() . '?add-to-cart=' . LDG_ENTERPRISE_PRODUCT_ID; ?>" class="ldg-plan-button primary">
                        Upgrade to Enterprise
                    </a>
                <?php else: ?>
                    <a href="#" onclick="alert('Please contact support to upgrade.')" class="ldg-plan-button primary">
                        Upgrade to Enterprise
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="ldg-pricing-faq">
            <h3>Questions?</h3>
            <p>All plans include a 30-day money-back guarantee. You can upgrade, downgrade, or cancel anytime.</p>
            <p>Need help choosing? <a href="mailto:support@yoursite.com">Contact our team</a> for personalized recommendations.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ===== ADMIN SETTINGS PAGE =====
add_action('admin_menu', 'ldg_add_settings_page');
function ldg_add_settings_page() {
    add_menu_page(
        'Legal Documents',
        'Legal Documents',
        'manage_options',
        'legal-documents',
        'ldg_settings_page',
        'dashicons-media-document',
        30
    );
}

function ldg_settings_page() {
    // Handle form submissions for manual user upgrades
    if (isset($_POST['update_subscription'])) {
        check_admin_referer('ldg_update_subscription');
        
        $user_id = intval($_POST['user_id']);
        $new_plan = sanitize_text_field($_POST['plan']);
        
        // Remove all LDG roles first
        $user = get_user_by('id', $user_id);
        $user->remove_role('ldg_free_member');
        $user->remove_role('ldg_pro_member');
        $user->remove_role('ldg_enterprise_member');
        
        // Add new role
        $role_map = array(
            'free' => 'ldg_free_member',
            'pro' => 'ldg_pro_member',
            'enterprise' => 'ldg_enterprise_member'
        );
        
        if (isset($role_map[$new_plan])) {
            $user->add_role($role_map[$new_plan]);
            update_user_meta($user_id, 'subscription_plan', $new_plan);
            delete_user_meta($user_id, 'ldg_cached_plan'); // Clear cache
            echo '<div class="notice notice-success"><p>User subscription updated successfully!</p></div>';
        }
    }
    
    // Handle download reset
    if (isset($_POST['reset_downloads'])) {
        check_admin_referer('ldg_reset_downloads');
        
        $user_id = intval($_POST['user_id']);
        $month_key = 'ldg_downloads_' . date('Y_m');
        delete_user_meta($user_id, $month_key);
        
        echo '<div class="notice notice-success"><p>Downloads reset for this month!</p></div>';
    }
    
    // Get all users with LDG roles
    $users = get_users(array(
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'wp_capabilities',
                'value' => 'ldg_free_member',
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'wp_capabilities', 
                'value' => 'ldg_pro_member',
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'wp_capabilities',
                'value' => 'ldg_enterprise_member', 
                'compare' => 'LIKE'
            )
        )
    ));
    
    ?>
    <div class="wrap">
        <h1>Legal Document Generator Pro - Admin Panel</h1>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #135eed;">
            <h2>🎯 Configuration Instructions</h2>
            <p><strong>IMPORTANT:</strong> Update these Product IDs with your actual WooCommerce product IDs:</p>
            <div style="background: #f1f1f1; padding: 15px; border-radius: 5px; font-family: monospace;">
                <strong>Current Settings:</strong><br>
                LDG_PRO_PRODUCT_ID = <?php echo LDG_PRO_PRODUCT_ID; ?><br>
                LDG_ENTERPRISE_PRODUCT_ID = <?php echo LDG_ENTERPRISE_PRODUCT_ID; ?>
            </div>
            <p>Go to <strong>WooCommerce → Products</strong> to find your actual product IDs and update the constants at the top of this plugin file.</p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h2>📊 Quick Stats</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">
                <?php
                $plan_counts = array('free' => 0, 'pro' => 0, 'enterprise' => 0);
                foreach ($users as $user) {
                    $plan = ldg_get_user_plan($user->ID);
                    if (isset($plan_counts[$plan])) {
                        $plan_counts[$plan]++;
                    }
                }
                ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #6c757d;"><?php echo $plan_counts['free']; ?></div>
                    <div>Free Members</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #135eed;"><?php echo $plan_counts['pro']; ?></div>
                    <div>Pro Members</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo $plan_counts['enterprise']; ?></div>
                    <div>Enterprise Members</div>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold; color: #333;"><?php echo count($users); ?></div>
                    <div>Total Users</div>
                </div>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px;">
            <h2>👥 User Management</h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Current Plan</th>
                        <th>Downloads This Month</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        $plan = ldg_get_user_plan($user->ID);
                        $remaining = ldg_get_remaining_downloads($user->ID);
                        $limits = ldg_get_plan_limits($plan);
                        $used = $limits['downloads'] == -1 ? 0 : ($limits['downloads'] - $remaining);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td>
                            <span class="plan-badge-admin <?php echo $plan; ?>">
                                <?php echo ucfirst($plan); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($limits['downloads'] == -1): ?>
                                <span style="color: #28a745; font-weight: bold;">Unlimited</span>
                            <?php else: ?>
                                <?php echo $used; ?> / <?php echo $limits['downloads']; ?>
                                <small style="color: #666;">(<?php echo $remaining; ?> remaining)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- Subscription Update Form -->
                            <form method="post" style="display: inline-block; margin-right: 10px;">
                                <?php wp_nonce_field('ldg_update_subscription'); ?>
                                <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                <select name="plan" onchange="this.form.submit()">
                                    <option value="free" <?php selected($plan, 'free'); ?>>Free</option>
                                    <option value="pro" <?php selected($plan, 'pro'); ?>>Pro</option>
                                    <option value="enterprise" <?php selected($plan, 'enterprise'); ?>>Enterprise</option>
                                </select>
                                <input type="hidden" name="update_subscription" value="1">
                            </form>
                            
                            <!-- Reset Downloads Form -->
                            <?php if ($used > 0): ?>
                            <form method="post" style="display: inline-block;">
                                <?php wp_nonce_field('ldg_reset_downloads'); ?>
                                <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                <button type="submit" name="reset_downloads" class="button button-small" 
                                        onclick="return confirm('Reset downloads for this user this month?')">
                                    Reset Downloads
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h2>🔧 Shortcode Usage</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div>
                    <h3>Document Generator</h3>
                    <p><strong>All Templates:</strong></p>
                    <code>[legal_documents]</code>
                    
                    <p><strong>Single Template:</strong></p>
                    <code>[legal_documents template="rental"]</code>
                    
                    <p><strong>Custom Templates:</strong></p>
                    <code>[legal_documents templates="rental,employment,nda"]</code>
                </div>
                
                <div>
                    <h3>Pricing Page</h3>
                    <p><strong>Full Pricing Table:</strong></p>
                    <code>[ldg_pricing_table]</code>
                    
                    <p><strong>Create a page called "Pricing" and add the shortcode above.</strong></p>
                </div>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h2>🎯 Setup Checklist</h2>
            <ol style="font-size: 16px; line-height: 1.8;">
                <li>✅ Plugin activated and working</li>
                <li>⚠️ <strong>Update Product IDs</strong> with your WooCommerce product IDs</li>
                <li>⚠️ <strong>Create pricing page</strong> with <code>[ldg_pricing_table]</code></li>
                <li>⚠️ <strong>Test purchase flow</strong> with a small amount</li>
                <li>⚠️ <strong>Verify automatic upgrades</strong> work after payment</li>
                <li>⚠️ <strong>Check template access</strong> for different plans</li>
            </ol>
        </div>
    </div>
    
    <style>
    .plan-badge-admin {
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: bold;
        color: white;
    }
    .plan-badge-admin.free { background: #6c757d; }
    .plan-badge-admin.pro { background: #135eed; }
    .plan-badge-admin.enterprise { background: #28a745; }
    
    .wrap h2 {
        margin-bottom: 15px;
        color: #333;
    }
    
    .widefat th, .widefat td {
        padding: 12px;
    }
    
    code {
        background: #f1f1f1;
        padding: 4px 8px;
        border-radius: 4px;
        font-family: monospace;
    }
    </style>
    <?php
}

// ===== UTILITY FUNCTIONS =====

/**
 * Clear user plan cache (useful after manual changes)
 */
function ldg_clear_user_plan_cache($user_id) {
    delete_user_meta($user_id, 'ldg_cached_plan');
    delete_user_meta($user_id, 'ldg_plan_last_check');
}

/**
 * Get upgrade URL for specific plan
 */
function ldg_get_upgrade_url($plan) {
    if (!function_exists('wc_get_checkout_url')) {
        return home_url('/pricing/');
    }
    
    $product_id = ($plan === 'enterprise') ? LDG_ENTERPRISE_PRODUCT_ID : LDG_PRO_PRODUCT_ID;
    return wc_get_checkout_url() . '?add-to-cart=' . $product_id;
}

// ===== CUSTOM REGISTRATION & LOGIN FORMS (FIXED) =====
add_shortcode('ldg_register_form', 'ldg_render_register_form');
add_shortcode('ldg_login_form', 'ldg_render_login_form');

function ldg_render_register_form() {
    // Don't show form if already logged in, just show a message
    if (is_user_logged_in()) {
        ob_start();
        ?>
        <div class="ldg-auth-container">
            <div class="ldg-success-message">
                You are already logged in! 
                <a href="<?php echo home_url('/legal-documents/'); ?>">Go to Legal Documents</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    ob_start();
    ?>
    <div class="ldg-auth-container">
        <style>
            .ldg-auth-container {
                max-width: 400px;
                margin: 40px auto;
                padding: 40px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .ldg-auth-container h2 {
                text-align: center;
                color: #2c3e50;
                margin-bottom: 30px;
                font-size: 28px;
            }
            
            .ldg-auth-form input[type="text"],
            .ldg-auth-form input[type="email"],
            .ldg-auth-form input[type="password"] {
                width: 100%;
                padding: 12px 16px;
                margin-bottom: 20px;
                border: 2px solid #e1e8ed;
                border-radius: 8px;
                font-size: 16px;
                transition: border-color 0.3s;
                box-sizing: border-box;
            }
            
            .ldg-auth-form input:focus {
                outline: none;
                border-color: #135eed;
            }
            
            .ldg-auth-form label {
                display: block;
                margin-bottom: 8px;
                color: #495057;
                font-weight: 500;
            }
            
            .ldg-auth-button {
                width: 100%;
                padding: 14px;
                background: #135eed;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }
            
            .ldg-auth-button:hover {
                background: #0f4bc8;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(19, 94, 237, 0.3);
            }
            
            .ldg-auth-links {
                text-align: center;
                margin-top: 20px;
                color: #6c757d;
            }
            
            .ldg-auth-links a {
                color: #135eed;
                text-decoration: none;
            }
            
            .ldg-auth-links a:hover {
                text-decoration: underline;
            }
            
            .ldg-error-message {
                background: #f8d7da;
                color: #721c24;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .ldg-success-message {
                background: #d4edda;
                color: #155724;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .ldg-success-message a {
                color: #155724;
                font-weight: bold;
            }
        </style>
        
        <h2>Get Started Free</h2>
        
        <?php
        if (isset($_POST['ldg_register'])) {
            $username = sanitize_user($_POST['username']);
            $email = sanitize_email($_POST['email']);
            $password = $_POST['password'];
            $errors = array();
            
            if (empty($username)) $errors[] = 'Username is required';
            if (empty($email)) $errors[] = 'Email is required';
            if (empty($password)) $errors[] = 'Password is required';
            if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
            
            if (username_exists($username)) $errors[] = 'Username already exists';
            if (email_exists($email)) $errors[] = 'Email already registered';
            
            if (empty($errors)) {
                $user_id = wp_create_user($username, $password, $email);
                
                if (!is_wp_error($user_id)) {
                    // Auto login
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                    
                    // Check if there's a redirect URL parameter
                    $redirect_to = isset($_GET['redirect_to']) ? esc_url($_GET['redirect_to']) : home_url('/legal-documents/');
                    
                    // Use JavaScript redirect to ensure it works
                    ?>
                    <script type="text/javascript">
                        window.location.href = '<?php echo $redirect_to; ?>';
                    </script>
                    <?php
                    exit;
                } else {
                    $errors[] = $user_id->get_error_message();
                }
            }
            
            if (!empty($errors)) {
                echo '<div class="ldg-error-message">' . implode('<br>', $errors) . '</div>';
            }
        }
        ?>
        
        <form method="post" class="ldg-auth-form">
            <div>
                <label>Username</label>
                <input type="text" name="username" value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>" required>
            </div>
            
            <div>
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" required>
            </div>
            
            <div>
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" name="ldg_register" class="ldg-auth-button">
                Create Free Account
            </button>
        </form>
        
        <div class="ldg-auth-links">
            Already have an account? <a href="<?php echo home_url('/login/'); ?>">Login here</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function ldg_render_login_form() {
    // Don't show form if already logged in, just show a message
    if (is_user_logged_in()) {
        ob_start();
        ?>
        <div class="ldg-auth-container">
            <div class="ldg-success-message">
                You are already logged in! 
                <a href="<?php echo home_url('/legal-documents/'); ?>">Go to Legal Documents</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    ob_start();
    ?>
    <div class="ldg-auth-container">
        <h2>Welcome Back</h2>
        
        <?php
        if (isset($_POST['ldg_login'])) {
            $creds = array(
                'user_login' => $_POST['username'],
                'user_password' => $_POST['password'],
                'remember' => true
            );
            
            $user = wp_signon($creds, false);
            
            if (!is_wp_error($user)) {
                // Check if there's a redirect URL parameter
                $redirect_to = isset($_GET['redirect_to']) ? esc_url($_GET['redirect_to']) : home_url('/legal-documents/');
                
                // Use JavaScript redirect to ensure it works
                ?>
                <script type="text/javascript">
                    window.location.href = '<?php echo $redirect_to; ?>';
                </script>
                <?php
                exit;
            } else {
                echo '<div class="ldg-error-message">Invalid username or password</div>';
            }
        }
        ?>
        
        <form method="post" class="ldg-auth-form">
            <div>
                <label>Username or Email</label>
                <input type="text" name="username" required>
            </div>
            
            <div>
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" name="ldg_login" class="ldg-auth-button">
                Login to Your Account
            </button>
        </form>
        
        <div class="ldg-auth-links">
            Don't have an account? <a href="<?php echo home_url('/get-started/'); ?>">Register here</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ===== WATERMARK FUNCTIONALITY =====
// Add this to the existing JavaScript in your main shortcode
add_action('wp_footer', 'ldg_add_watermark_script');
function ldg_add_watermark_script() {
    if (!is_page()) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Function to add watermarks to preview
        function addWatermarks() {
            var preview = document.getElementById('ldg-preview');
            if (!preview) return;
            
            // Remove existing watermarks
            $('.ldg-watermark').remove();
            
            // Get preview dimensions
            var previewHeight = preview.scrollHeight;
            var watermarkCount = Math.ceil(previewHeight / 400); // One watermark every 400px
            
            // Add watermarks
            for (var i = 0; i < watermarkCount; i++) {
                var watermark = $('<div class="ldg-watermark">CONFIDENTIAL DRAFT</div>');
                watermark.css({
                    'position': 'absolute',
                    'top': (150 + (i * 400)) + 'px',
                    'left': '50%',
                    'transform': 'translateX(-50%) rotate(-45deg)',
                    'font-size': '48px',
                    'color': 'rgba(255, 0, 0, 0.2)',
                    'font-weight': 'bold',
                    'pointer-events': 'none',
                    'z-index': '100',
                    'user-select': 'none',
                    'font-family': 'Arial, sans-serif'
                });
                $('#ldg-preview').append(watermark);
            }
        }
        
        // Add watermarks on initial load
        setTimeout(addWatermarks, 500);
        
        // Re-add watermarks when content changes
        var observer = new MutationObserver(function(mutations) {
            addWatermarks();
        });
        
        if (document.getElementById('ldg-preview')) {
            observer.observe(document.getElementById('ldg-preview'), {
                childList: true,
                subtree: true
            });
        }
        
        // Override the download function to remove watermarks
        var originalDownload = window.ldgDownloadPDF;
        window.ldgDownloadPDF = function() {
            // Remove watermarks before download
            $('.ldg-watermark').hide();
            
            // Call original download function
            originalDownload.call(this);
            
            // Show watermarks again after a delay
            setTimeout(function() {
                $('.ldg-watermark').show();
            }, 1000);
            
            // Show success message
            if (!$('.ldg-download-success').length) {
                var successMsg = $('<div class="ldg-download-success">✅ Document downloaded without watermark!</div>');
                successMsg.css({
                    'position': 'fixed',
                    'bottom': '20px',
                    'right': '20px',
                    'background': '#28a745',
                    'color': 'white',
                    'padding': '15px 25px',
                    'border-radius': '8px',
                    'z-index': '9999',
                    'font-weight': 'bold',
                    'box-shadow': '0 4px 15px rgba(0,0,0,0.2)'
                });
                $('body').append(successMsg);
                
                setTimeout(function() {
                    successMsg.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        };
    });
    </script>
    <style>
    #ldg-preview {
        position: relative;
    }
    
    .ldg-watermark {
        text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        letter-spacing: 8px;
        white-space: nowrap;
    }
    </style>
    <?php
}
?>
